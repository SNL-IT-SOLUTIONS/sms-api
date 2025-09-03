<?php

namespace App\Http\Controllers;

use App\Models\payments;
use App\Models\students;
use Barryvdh\DomPDF\Facade\Pdf; // Assuming you have a PDF facade set up
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;


class PaymentsController extends Controller
{
    /**
     * Confirm Payment and Generate Receipt
     */
    public function confirmPayment(Request $request, $studentId)
    {
        try {
            // ✅ Validate input
            $validated = $request->validate([
                'amount'         => 'required|numeric|min:1',
                'payment_method' => 'nullable|string|in:cash,card,gcash,bank_transfer',
                'remarks'        => 'nullable|string|max:255',
            ]);

            // ✅ Fetch student safely
            $student = students::with(['examSchedule.applicant.course', 'examSchedule.applicant.campus', 'subjects'])
                ->findOrFail($studentId);

            // ✅ Payment calculation
            $totalPaid = $student->payments()->sum('paid_amount');
            $totalDue  = (float) $student->total_amount;
            $outstandingBalance = $totalDue - $totalPaid;

            if ($outstandingBalance <= 0) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No outstanding balance. Student is already fully paid.'
                ], 400);
            }

            $paidAmount = min($validated['amount'], $outstandingBalance);
            $newOutstanding = $outstandingBalance - $paidAmount;
            $paymentStatus = ($newOutstanding <= 0) ? 'paid' : 'partial';

            // ✅ Generate unique receipt number
            $receiptNo = 'RCPT-' . str_pad(
                $student->payments()->lockForUpdate()->count() + 1,
                6,
                '0',
                STR_PAD_LEFT
            );

            // ✅ Create payment record
            $payment = payments::create([
                'student_id'        => $student->id,
                'amount'            => $totalDue,
                'paid_amount'       => $paidAmount,
                'school_year_id'    => $student->academic_year_id,
                'payment_method'    => $validated['payment_method'] ?? 'cash',
                'status'            => $paymentStatus,
                'receipt_no'        => $receiptNo,
                'remarks'           => $validated['remarks'] ?? 'Payment',
                'paid_at'           => now(),
                'received_by'       => auth()->id(),
                'remaining_balance' => $newOutstanding,
            ]);

            // ✅ Update student status safely
            $student->payment_status = $paymentStatus;
            $student->save();

            // ✅ Generate Receipt PDF
            $receiptDir = storage_path('app/receipts');
            if (!file_exists($receiptDir)) {
                mkdir($receiptDir, 0777, true);
            }

            $pdfPath = $receiptDir . "/receipt_{$student->student_number}.pdf";

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', [
                'studentNumber'      => $student->student_number,
                'courseName'         => $student->examSchedule?->applicant?->course?->course_name ?? '—',
                'campusName'         => $student->examSchedule?->applicant?->campus?->campus_name ?? '—',
                'firstName'          => $student->examSchedule?->applicant?->first_name ?? '',
                'lastName'           => $student->examSchedule?->applicant?->last_name ?? '',
                'subjects'           => $student->subjects()->get(['subject_code', 'subject_name', 'units']),
                'totalUnits'         => $student->subjects()->sum('units') ?? 0,

                // Payment details
                'receiptNo'          => $receiptNo,
                'paidAt'             => $payment->paid_at,
                'tuitionFee'         => number_format((float) $student->tuition_fee, 2),
                'miscFee'            => number_format((float) $student->misc_fee, 2),
                'unitsFee'           => number_format((float) $student->units_fee, 2),
                'paidAmount'         => number_format($student->payments()->sum('paid_amount'), 2),
                'remainingBalance'   => number_format($newOutstanding, 2),
            ]);
            $pdf->save($pdfPath);

            // ✅ Send Email with Receipt
            $email = $student->examSchedule?->applicant?->email;
            if ($email) {
                Mail::send([], [], function ($message) use ($email, $student, $pdfPath) {
                    $message->to($email)
                        ->subject("Official Receipt - {$student->student_number}")
                        ->attach($pdfPath, [
                            'as'   => "Receipt_{$student->student_number}.pdf",
                            'mime' => 'application/pdf',
                        ])
                        ->setBody('Attached is your official receipt. Thank you for your payment!', 'text/html');
                });
            }

            // ✅ Response
            return response()->json([
                'isSuccess'         => true,
                'message'           => 'Payment confirmed. Receipt generated and emailed.',
                'paid_amount'       => $paidAmount,
                'status'            => $paymentStatus,
                'receipt'           => $receiptNo,
                'remaining_balance' => $newOutstanding,
                'total_amount'      => $totalDue,
                'pdf_url'           => url("storage/receipts/receipt_{$student->student_number}.pdf")
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

            if ($request->has('academic_year_id') || $request->has('school_year_id')) {
                $schoolYearId = $request->academic_year_id ?? $request->school_year_id;
                $query->where('school_year_id', $schoolYearId);
            }
            // ✅ Optional search by student name, student_id, receipt_no, or status
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

            // ✅ Format results
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
                    'school_year_id'     => $payment->school_year_id, // ✅ included in response
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


    public function printProcessPayments(Request $request)
    {
        try {
            $studentId = $request->get('student_id'); // ✅ optional param

            // 🔎 Fetch students with relationships
            $query = students::with([
                'examSchedule.applicant.gradeLevel',
                'examSchedule.applicant.course',
                'examSchedule.applicant.campus',
                'section',
                'payments'
            ])
                ->where('is_enrolled', 1)
                ->orderBy('academic_year_id', 'asc');

            // 🎯 If a specific student ID is provided → filter
            if ($studentId) {
                $query->where('id', $studentId);
            }

            $students = $query->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => $studentId
                        ? "No records found for student ID {$studentId}"
                        : "No enrolled students found."
                ], 404);
            }

            // 📂 Group by academic year
            // 📂 Group by school_year_id (year + semester combined)
            $groupedByYearSemester = $students->groupBy('academic_year_id');

            $results = [];

            foreach ($groupedByYearSemester as $academicYearId => $studentsGroup) {
                $studentsData = [];

                // 🔎 Fetch school year + semester info
                $academicYear = DB::table('school_years')
                    ->where('id', $academicYearId)
                    ->select('id', 'school_year', 'semester', 'is_active', 'is_archived')
                    ->first();

                foreach ($studentsGroup as $student) {
                    $examSchedule = $student->examSchedule;
                    $admission    = $examSchedule?->applicant;

                    // 💰 Payment info per semester
                    $totalPaid = $student->payments()
                        ->where('school_year_id', $academicYearId) // <-- filter per semester
                        ->sum('paid_amount');

                    $outstandingBalance = max($student->total_amount - $totalPaid, 0);

                    $studentsData[] = [
                        'id'                 => $student->id,
                        'student_number'     => $student->student_number,
                        'status'             => $student->enrollment_status,
                        'payment_status'     => $student->payment_status,
                        'grade_level'        => $admission?->gradeLevel?->grade_level,
                        'course'             => $admission?->course?->course_name,
                        'campus'             => $admission?->campus?->campus_name,
                        'tuition_fee'        => $student->tuition_fee,
                        'misc_fee'           => $student->misc_fee,
                        'units_fee'          => $student->units_fee,
                        'total_amount'       => $student->total_amount,
                        'total_paid'         => $totalPaid,
                        'outstanding_balance' => $outstandingBalance,
                        'exam' => [
                            'exam_id'     => $examSchedule?->id,
                            'exam_date'   => $examSchedule?->exam_date,
                            'exam_status' => $examSchedule?->exam_status,
                            'exam_score'  => $examSchedule?->exam_score,
                        ],
                        'applicant' => [
                            'applicant_id' => $admission?->id,
                            'first_name'   => $admission?->first_name,
                            'last_name'    => $admission?->last_name,
                            'email'        => $admission?->email,
                            'contact'      => $admission?->contact_number,
                        ],
                        'section' => [
                            'section_id'   => $student->section?->id,
                            'section_name' => $student->section?->section_name,
                        ],
                    ];
                }

                $results[] = [
                    'academic_year' => $academicYear ? [
                        'id'          => $academicYear->id,
                        'school_year' => $academicYear->school_year,
                        'semester'    => $academicYear->semester,
                        'is_active'   => $academicYear->is_active,
                        'is_archived' => $academicYear->is_archived,
                    ] : null,
                    'students' => $studentsData,
                ];
            }


            // 🎯 Response for frontend (ready to print)
            return response()->json([
                'isSuccess' => true,
                'printpayments'      => $results,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
