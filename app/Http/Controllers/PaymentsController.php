<?php

namespace App\Http\Controllers;

use App\Models\payments;
use App\Models\students;
use Barryvdh\DomPDF\Facade\Pdf; // Assuming you have a PDF facade set up
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


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

    // public function createIntent(Request $request)
    // {
    //     $response = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
    //         ->post('https://api.paymongo.com/v1/payment_intents', [
    //             'data' => [
    //                 'attributes' => [
    //                     'amount' => $request->amount, // e.g. 10000 = ₱100
    //                     'payment_method_allowed' => ['card'],
    //                     'currency' => 'PHP',
    //                 ]
    //             ]
    //         ]);

    //     return $response->json();
    // }


public function testPayMongoPayment(Request $request)
{
    $student = auth()->user();
    $amount = intval($request->amount * 100); // PayMongo expects integer centavos

    // 1️⃣ Create Payment Intent
    $intentResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
        ->post('https://api.paymongo.com/v1/payment_intents', [
            'data' => [
                'attributes' => [
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'payment_method_allowed' => ['card'],
                    'description' => "Payment for student #{$student->student_number}",
                    'metadata' => [
                        'student_id' => (string) $student->id
                    ]
                ]
            ]
        ]);

    $paymentIntent = $intentResponse->json();

    // ✅ Check if Payment Intent creation failed
    if (!isset($paymentIntent['data'])) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Payment Intent creation failed',
            'response' => $paymentIntent
        ], 400);
    }

    $paymentIntentId = $paymentIntent['data']['id'];

   $billingName = $student->examSchedule->admission?->first_name . ' ' . $student->examSchedule->admission?->last_name;
$billingEmail = $student->examSchedule->admission?->email;

$paymentMethodResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
    ->post('https://api.paymongo.com/v1/payment_methods', [
        'data' => [
            'attributes' => [
                'type' => 'card',
                'details' => [
                    'card_number' => '4343434343434345', // PayMongo test card
                    'exp_month' => 12,
                    'exp_year' => 25,
                    'cvc' => '123'
                ],
                'billing' => [
                    'name' => $billingName,
                    'email' => $billingEmail // ✅ required
                ]
            ]
        ]
    ]);

    $paymentMethod = $paymentMethodResponse->json();

    if (!isset($paymentMethod['data'])) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Payment Method creation failed',
            'response' => $paymentMethod
        ], 400);
    }

    $paymentMethodId = $paymentMethod['data']['id'];

    // 3️⃣ Attach Payment Method to Payment Intent
    $attachResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
        ->post("https://api.paymongo.com/v1/payment_intents/{$paymentIntentId}/attach", [
            'data' => [
                'attributes' => [
                    'payment_method' => $paymentMethodId
                ]
            ]
        ]);

    $attachedIntent = $attachResponse->json();

    // 4️⃣ Record payment in DB (simulate successful payment)
    payments::create([
        'student_id'       => $student->id,
        'amount'           => $amount / 100,
        'paid_amount'      => $amount / 100,
        'remaining_balance'=> 0,
        'payment_method'   => 'card',
        'status'           => 'success',
        'receipt_no'       => 'RCPT-' . strtoupper(uniqid()),
        'remarks'          => 'Test Online Payment via PayMongo',
        'paid_at'          => now(),
        'reference_no'     => $paymentIntentId,
        'received_by'      => 'System'
    ]);

    return response()->json([
        'isSuccess' => true,
        'payment_intent' => $attachedIntent
    ]);
}



}
