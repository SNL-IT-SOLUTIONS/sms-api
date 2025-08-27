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

}
