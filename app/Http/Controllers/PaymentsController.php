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
                'message' => 'Invalid payment amount'
            ], 422);
        }

        // Total tuition due (current remaining tuition)
    $totalDue = $student->tuition_fee;          // current remaining tuition
$paidAmount = min((float)$request->input('amount'), $totalDue); // clamp to avoid overpayment
$remainingBalanceBeforePayment = $totalDue; // this is what you store as 'amount'

$paymentStatus = ($paidAmount >= $totalDue) ? 'paid' : 'partial';

$payment = payments::create([
    'student_id'     => $student->id,
    'amount'         => $remainingBalanceBeforePayment, // reference
    'paid_amount'    => $paidAmount,                    // cash given
    'payment_method' => 'cash',
    'status'         => $paymentStatus,
    'receipt_no'     => 'RCPT-' . str_pad($student->payments()->count() + 1, 6, '0', STR_PAD_LEFT),
    'remarks'        => $request->input('remarks', 'Payment'),
    'paid_at'        => now(),
    'received_by'    => auth()->id()
]);

// Update student tuition fee
$student->tuition_fee -= $paidAmount;
if ($student->tuition_fee < 0) $student->tuition_fee = 0;
$student->payment_status = $student->tuition_fee == 0 ? 'paid' : 'partial';
$student->save();


        return response()->json([
            'isSuccess'   => true,
            'message'     => 'Payment confirmed and receipt generated',
            'paid_amount' => $paidAmount,
            'status'      => $student->payment_status,
            'receipt'     => $payment->receipt_no,
            'remaining_balance' => $student->tuition_fee
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message'   => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}



}
