<?php

namespace App\Http\Controllers;

use App\Models\payments;
use App\Models\students;
use Barryvdh\DomPDF\Facade\Pdf; // Assuming you have a PDF facade set up

use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * Confirm Payment and Generate Receipt
     */
   public function confirmPayment(Request $request, $studentId)
{
    try {
        $student = students::findOrFail($studentId);
        $paidAmount = (float) $request->input('amount');

        if (!$paidAmount || $paidAmount <= 0) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Invalid payment amount'
            ], 422);
        }

        // Current tuition due before payment
        $totalDue = $student->tuition_fee;

        // Prevent overpayment
        $paidAmount = min($paidAmount, $totalDue);

        // Remaining balance after payment
        $outstandingBalance = $totalDue - $paidAmount;

        // Payment status (fully paid or partial)
        $paymentStatus = ($outstandingBalance <= 0) ? 'paid' : 'partial';

        // Create payment record
        $payment = payments::create([
            'student_id'        => $student->id,
            'amount'            => $totalDue,           // tuition before payment
            'paid_amount'       => $paidAmount,         // amount actually paid
            'payment_method'    => 'cash',
            'status'            => $paymentStatus,
            'receipt_no'        => 'RCPT-' . str_pad($student->payments()->count() + 1, 6, '0', STR_PAD_LEFT),
            'remarks'           => $request->input('remarks', 'Payment'),
            'paid_at'           => now(),
            'received_by'       => auth()->id(),
            'remaining_balance' => $outstandingBalance  // 👈 outstanding balance stored in DB
        ]);

        // Update student record
        $student->tuition_fee = $outstandingBalance;
        $student->payment_status = $paymentStatus;
        $student->save();

        // Return response
        return response()->json([
            'isSuccess'         => true,
            'message'           => 'Payment confirmed and receipt generated',
            'paid_amount'       => $paidAmount,
            'status'            => $student->payment_status,
            'receipt'           => $payment->receipt_no,
            'remaining_balance' => $outstandingBalance
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message'   => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}



public function getAssessmentBilling()
{
    try {
        // Get the logged-in student
        $student = auth()->user();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        // Load relationships
        $student->load(['admission','course', 'campus', 'subjects']);

        // Get all payments made by this student
        $payments = payments::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPaid = $payments->sum('paid_amount');
        $totalFee = $student->misc_fee + $student->units_fee;
        $remainingBalance = $totalFee - $totalPaid;

        // Prepare subjects array
        $subjects = $student->subjects->map(function($sub) {
            return [
                'code' => $sub->subject_code,
                'name' => $sub->subject_name,
                'units' => $sub->units
            ];
        });

        // Prepare assessment billing data
        $assessment = [
            'student_name' => $student->first_name . ' ' . $student->last_name,
            'student_number' => $student->student_number,
            'course' => $student->course->course_name ?? null,
            'campus' => $student->examSchedule?->admission?->campus?->campus_name ?? null,

            'subjects' => [
                'list' => $subjects,
                'total_units' => $student->subjects->sum('units')
            ],
            'billing' => [
                [
                    'description' => 'Miscellaneous Fee',
                    'amount' => number_format($student->misc_fee, 2)
                ],
                [
                    'description' => 'Units Fee',
                    'amount' => number_format($student->units_fee, 2)
                ],
                [
                    'description' => 'Total Fee',
                    'amount' => number_format($totalFee, 2)
                ],
                [
                    'description' => 'Total Paid',
                    'amount' => number_format($totalPaid, 2)
                ],
                [
                    'description' => 'Remaining Balance',
                    'amount' => number_format($remainingBalance, 2)
                ]
            ]
        ];

        return response()->json([
            'isSuccess' => true,
            'data' => $assessment
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}





}
