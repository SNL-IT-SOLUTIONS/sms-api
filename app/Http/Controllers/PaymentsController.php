<?php

namespace App\Http\Controllers;

use App\Models\payments;
use App\Models\students;
use Barryvdh\DomPDF\Facade\Pdf; // Assuming you have a PDF facade set up
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;


class PaymentsController extends Controller
{
    /**
     * Confirm Payment and Generate Receipt
     */
    public function confirmPayment(Request $request, $studentId)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'amount'         => 'required|numeric|min:1',
                'payment_method' => 'nullable|string|in:cash,card,gcash,bank_transfer',
                'remarks'        => 'nullable|string|max:255',
            ]);

            // Fetch student safely
            $student = students::findOrFail($studentId);

            // Calculate how much has already been paid
            $totalPaid = $student->payments()->sum('paid_amount');
            $totalDue  = (float) $student->total_amount;
            $outstandingBalance = $totalDue - $totalPaid;

            if ($outstandingBalance <= 0) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No outstanding balance. Student is already fully paid.'
                ], 400);
            }

            // Cap payment so no overpayment
            $paidAmount = min($validated['amount'], $outstandingBalance);

            // Recalculate outstanding balance
            $newOutstanding = $outstandingBalance - $paidAmount;
            $paymentStatus = ($newOutstanding <= 0) ? 'paid' : 'partial';

            // Generate receipt number (atomic to avoid duplicates)
            $receiptNo = 'RCPT-' . str_pad(
                $student->payments()->lockForUpdate()->count() + 1,
                6,
                '0',
                STR_PAD_LEFT
            );

            // Create payment record
            $payment = payments::create([
                'student_id'        => $student->id,
                'amount'            => $totalDue,   // original total due
                'paid_amount'       => $paidAmount,
                'payment_method'    => $validated['payment_method'] ?? 'cash',
                'status'            => $paymentStatus,
                'receipt_no'        => $receiptNo,
                'remarks'           => $validated['remarks'] ?? 'Payment',
                'paid_at'           => now(),
                'received_by'       => auth()->id(),
                'remaining_balance' => $newOutstanding,
            ]);

            // Update student payment status (but keep total_amount intact!)
            $student->update([
                'payment_status' => $paymentStatus,
            ]);

            // Response
            return response()->json([
                'isSuccess'         => true,
                'message'           => 'Payment confirmed and receipt generated.',
                'paid_amount'       => $paidAmount,
                'status'            => $paymentStatus,
                'receipt'           => $payment->receipt_no,
                'remaining_balance' => $newOutstanding,
                'total_amount'      => $totalDue, // still show original total
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Validation failed',
                'errors'    => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function testPayMongoPayment(Request $request)
    {
        try {
            $student = auth()->user();
            $paidAmount = (float) $request->input('amount');

            if (!$paidAmount || $paidAmount <= 0) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Invalid payment amount'
                ], 422);
            }

            // Cap payment to remaining balance (from total_amount now 👇)
            $totalDue = $student->total_amount;
            $paidAmount = min($paidAmount, $totalDue);
            $remainingBalance = $totalDue - $paidAmount;
            $paymentStatus = ($remainingBalance <= 0) ? 'paid' : 'partial';

            // Create PayMongo Payment Intent
            $amountCentavos = intval($paidAmount * 100);
            $intentResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
                ->post('https://api.paymongo.com/v1/payment_intents', [
                    'data' => [
                        'attributes' => [
                            'amount' => $amountCentavos,
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
            if (!isset($paymentIntent['data'])) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Payment Intent creation failed',
                    'response'  => $paymentIntent
                ], 400);
            }

            $paymentIntentId = $paymentIntent['data']['id'];

            // Create Payment Method (Test Card)
            $billingName = $student->examSchedule->admission?->first_name . ' ' .
                $student->examSchedule->admission?->last_name;
            $billingEmail = $student->examSchedule->admission?->email;

            $paymentMethodResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
                ->post('https://api.paymongo.com/v1/payment_methods', [
                    'data' => [
                        'attributes' => [
                            'type' => 'card',
                            'details' => [
                                'card_number' => '4343434343434345',
                                'exp_month' => 12,
                                'exp_year'  => 25,
                                'cvc'       => '123'
                            ],
                            'billing' => [
                                'name'  => $billingName,
                                'email' => $billingEmail
                            ]
                        ]
                    ]
                ]);

            $paymentMethod = $paymentMethodResponse->json();
            if (!isset($paymentMethod['data'])) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Payment Method creation failed',
                    'response'  => $paymentMethod
                ], 400);
            }

            $paymentMethodId = $paymentMethod['data']['id'];

            // Attach Payment Method to Intent
            $attachResponse = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
                ->post("https://api.paymongo.com/v1/payment_intents/{$paymentIntentId}/attach", [
                    'data' => [
                        'attributes' => [
                            'payment_method' => $paymentMethodId
                        ]
                    ]
                ]);

            $attachedIntent = $attachResponse->json();

            // Record payment in DB
            $payment = payments::create([
                'student_id'        => $student->id,
                'amount'            => $totalDue,           // balance before this payment
                'paid_amount'       => $paidAmount,         // amount paid now
                'remaining_balance' => $remainingBalance,   // balance after payment
                'payment_method'    => 'card',
                'status'            => $paymentStatus,
                'receipt_no'        => 'RCPT-' . str_pad($student->payments()->count() + 1, 6, '0', STR_PAD_LEFT),
                'remarks'           => 'PayMongo',
                'paid_at'           => now(),
                'reference_no'      => $paymentIntentId,
                'received_by'       => 'system'
            ]);

            // Update student record
            $student->total_amount   = $remainingBalance;  // 👈 update balance
            $student->payment_status = $paymentStatus;
            $student->save();

            return response()->json([
                'isSuccess'         => true,
                'message'           => 'Payment confirmed via PayMongo',
                'paid_amount'       => $paidAmount,
                'status'            => $student->payment_status,
                'receipt'           => $payment->receipt_no,
                'remaining_balance' => $remainingBalance,
                'payment_intent'    => $attachedIntent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getAllPayments(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            // Load payments with student + admission relationship
            $query = payments::with(['student.examSchedule.admission'])
                ->orderBy('created_at', 'desc');

            // Optional search by student name, student_id, receipt_no, or status
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student.examSchedule.admission', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                    })
                        ->orWhere('student_id', $search)
                        ->orWhere('receipt_no', 'like', "%$search%")
                        ->orWhere('status', 'like', "%$search%");
                });
            }

            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            // Format results
            $data = $payments->map(function ($payment) {
                $student = $payment->student;
                $examSchedule = $student->examSchedule ?? null;
                $admission = $examSchedule?->admission ?? null;

                $studentName = $admission
                    ? "{$admission->first_name} {$admission->last_name}"
                    : 'N/A';

                return [
                    'id'                 => $payment->id,
                    'student_id'         => $payment->student_id,
                    'student_name'       => $studentName,
                    'amount_billed'      => $payment->amount,
                    'paid_amount'        => $payment->paid_amount,
                    'remaining_balance'  => $payment->remaining_balance,
                    'latest_balance'     => $student->total_amount,
                    'payment_method'     => $payment->payment_method,
                    'status'             => $payment->status,
                    'reference_no'       => $payment->reference_no,
                    'remarks'            => $payment->remarks,
                    'receipt_no'         => $payment->receipt_no,
                    'paid_at'            => $payment->paid_at,
                    'received_by'        => $payment->received_by,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'data'      => $data,
                'pagination' => [
                    'total'        => $payments->total(),
                    'per_page'     => $payments->perPage(),
                    'current_page' => $payments->currentPage(),
                    'last_page'    => $payments->lastPage(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
