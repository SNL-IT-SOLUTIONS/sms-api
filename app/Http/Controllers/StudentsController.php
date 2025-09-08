<?php

namespace App\Http\Controllers;

use App\Models\students;
use Illuminate\Support\Facades\Auth;
use App\Models\admissions;
use App\Models\exam_schedules;
use App\Models\courses;
use App\Models\campuses;
use App\Models\subjects;
use App\Models\payments;
use App\Models\enrollments;
use Luigel\Paymongo\Facades\Paymongo;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class StudentsController extends Controller
{
    public function getStudentsProfile()
    {
        try {
            $student = Auth::user(); // gets the currently logged in user

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            // If you want to eager load relations (like course, section, etc.)
            $profile = Students::with(['course', 'section']) // add your relationships here
                ->where('id', $student->id)
                ->first();

            return response()->json([
                'isSuccess' => true,
                'data' => $profile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function updateStudentsProfile(Request $request)
    {
        try {
            $student = Auth::user(); // logged in student

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            // get related admission
            $examSchedule = $student->examSchedule; // assuming relationship is set up
            if (!$examSchedule || !$examSchedule->admission) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Admission details not found.',
                ], 404);
            }

            $admission = $examSchedule->admission;

            // validate admission fields
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|email|max:255|unique:admissions,email,' . $admission->id,
                // add more fields you need
            ]);

            // update admission data instead of students
            $admission->update($request->only([
                'first_name',
                'last_name',
                'email',
                // add other fields
            ]));

            return response()->json([
                'isSuccess' => true,
                'message' => 'Profile updated successfully.',
                'data' => $admission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function getAssessmentBilling()
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Load latest enrollment
            $enrollment = \App\Models\enrollments::where('student_id', $student->id)
                ->latest()
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No enrollment record found.'
                ], 404);
            }

            // Validate misc fee for the school_year_id
            $miscFee = \App\Models\fees::where('school_year_id', $enrollment->school_year_id)
                ->where('type', 'miscellaneous') // adjust if your column name differs
                ->first();

            if (!$miscFee) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Miscellaneous fee not set for this school year.'
                ], 422);
            }

            // Payments
            $payments = payments::where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $totalPaid = $payments->sum('paid_amount');
            $totalFee = (float) $enrollment->original_tuition_fee; // includes misc + tuition
            $remainingBalance = (float) $enrollment->total_tuition_fee; // what’s left

            // Subjects
            $subjects = $student->subjects->map(function ($sub) {
                return [
                    'code' => $sub->subject_code,
                    'name' => $sub->subject_name,
                    'units' => $sub->units
                ];
            });

            $assessment = [
                'student_name'   => $student->examSchedule?->admission?->first_name . ' ' . $student->examSchedule?->admission?->last_name,
                'student_number' => $student->student_number,
                'course'         => $student->course->course_name ?? null,
                'campus'         => $student->examSchedule?->admission?->campus?->campus_name ?? null,

                'subjects' => [
                    'list'        => $subjects,
                    'total_units' => $student->subjects->sum('units')
                ],

                'billing' => [
                    [
                        'description' => 'Miscellaneous Fee',
                        'amount'      => number_format((float) $miscFee->amount, 2)
                    ],
                    [
                        'description' => 'Tuition Fee',
                        'amount'      => number_format((float) $enrollment->tuition_fee, 2)
                    ],
                    [
                        'description' => 'Total Fee',
                        'amount'      => number_format($totalFee, 2)
                    ],
                    [
                        'description' => 'Total Paid',
                        'amount'      => number_format($totalPaid, 2)
                    ],
                    [
                        'description' => 'Remaining Balance',
                        'amount'      => number_format($remainingBalance, 2)
                    ]
                ]
            ];

            return response()->json([
                'isSuccess' => true,
                'bill'      => $assessment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }



    //WORKING
    public function transactionHistory()
    {
        try {
            // Get logged-in student
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            // Fetch all payments for this student
            $payments = payments::where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($payments->isEmpty()) {
                return response()->json([
                    'isSuccess' => true,
                    'transactions' => [],
                    'message' => 'No transactions found.'
                ]);
            }

            // Map payments into a clean response format
            $transactions = $payments->map(function ($p) {
                return [
                    'transaction_id'   => $p->id,
                    'receipt_no'       => $p->receipt_no,
                    'amount'           => number_format($p->amount, 2),
                    'paid_amount'      => number_format($p->paid_amount, 2),
                    'remaining_balance' => number_format($p->remaining_balance, 2),
                    'payment_method'   => ucfirst($p->payment_method),
                    'status'           => ucfirst($p->status),
                    'remarks'          => $p->remarks,
                    'paid_at'          => $p->paid_at ? $p->paid_at->format('Y-m-d H:i:s') : null,
                    'created_at'       => $p->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'transactions' => $transactions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }

    //SCHEDULE
    public function getMySchedule()
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Load schedules through section
            $schedules = $student->schedules;

            if ($schedules->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No schedules found for this student.'
                ]);
            }

            // Format response
            $formatted = $schedules->map(function ($sched) {
                return [
                    'subject_code' => $sched->subject->subject_code ?? null,
                    'subject_name' => $sched->subject->subject_name ?? null,
                    'day' => $sched->day,
                    'time' => $sched->time,
                    'room' => $sched->room->room_name ?? null,
                    'teacher' => $sched->teacher?->first_name . ' ' . $sched->teacher?->last_name,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Student schedule retrieved successfully.',
                'schedules' => $formatted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve schedule.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getMyGrades()
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // Load student subjects with pivot data (grades)
            $student->load('subjects');

            if ($student->subjects->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No grades found for this student.'
                ]);
            }

            // Format response
            $grades = $student->subjects->map(function ($sub) {
                return [
                    'subject_code'   => $sub->subject_code,
                    'subject_name'   => $sub->subject_name,
                    'units'          => $sub->units,
                    'final_rating'   => $sub->pivot->final_rating ?? null,
                    'remarks'        => $sub->pivot->remarks ?? null,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Student grades retrieved successfully.',
                'grades' => $grades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve grades.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function enrollNow(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            //  Load student record
            $student = students::where('admission_id', $user->admission_id)->first();
            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student record not found.'
                ], 404);
            }

            //  Check unpaid enrollments
            $hasUnpaid = enrollments::where('student_id', $student->id)
                ->where('payment_status', 'Unpaid')
                ->exists();

            if ($hasUnpaid) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'You cannot enroll while you still have an unpaid enrollment.'
                ], 400);
            }

            //  Validate subjects
            $validated = $request->validate([
                'subjects'   => 'required|array|min:1',
                'subjects.*' => 'exists:subjects,id'
            ]);

            $subjects = subjects::whereIn('id', $validated['subjects'])->get();
            if ($subjects->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No valid subjects found.'
                ], 400);
            }

            //  Curriculum check
            $curriculum = DB::table('curriculums')
                ->where('course_id', $student->course_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$curriculum) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No curriculum assigned for this course.'
                ], 400);
            }

            //  Fee computation (units + misc fees)
            $totalUnits = $subjects->sum('units');
            $unitRate   = 200;
            $unitsFee   = $totalUnits * $unitRate;
            $tuitionFee = $unitsFee;

            //  Get all active misc fees for the student's school year ONLY
            $miscFees = DB::table('fees')
                ->where('is_active', 1)
                ->where('is_archived', 0)
                ->where('school_year_id', $student->academic_year_id) // 
                ->get();

            //  If no misc fees for this school year, stop enrollment
            if ($miscFees->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No applicable fees found for your school year. Please contact the registrar.'
                ], 400);
            }

            $miscFeeTotal = $miscFees->sum('default_amount');
            $totalFee     = $tuitionFee + $miscFeeTotal;

            //  Determine enrollment_status
            $hasAllRequirements = $student->has_form137
                && $student->has_form138
                && $student->has_good_moral
                && $student->has_birth_certificate
                && $student->has_certificate_of_completion;

            $enrollmentStatus = $hasAllRequirements ? 'Officially Enrolled' : 'Unofficial Enrolled';

            //  Unique reference number
            do {
                $referenceNumber = mt_rand(1000000, 9999999);
            } while (enrollments::where('reference_number', $referenceNumber)->exists());

            $transactions = 'Enrollment';

            // Create enrollment
            $enrollment = enrollments::create([
                'student_id'           => $student->id,
                'school_year_id'       => $student->academic_year_id,
                'tuition_fee'          => $tuitionFee,
                'misc_fee'             => $miscFeeTotal,
                'original_tuition_fee' => $totalFee,
                'total_tuition_fee'    => $totalFee,
                'payment_status'       => 'Unpaid',
                'transaction'          => $transactions,
                'reference_number'     => $referenceNumber,
                'created_by'           => $student->id
            ]);

            // Insert misc fees into pivot (enrollment_fees)
            foreach ($miscFees as $fee) {
                DB::table('enrollment_fees')->insert([
                    'enrollment_id' => $enrollment->id,
                    'fee_id'        => $fee->id,
                    'amount'        => $fee->default_amount,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            //  Sync subjects to pivot (student_subjects with school_year_id)
            $pivotData = [];
            foreach ($validated['subjects'] as $subjectId) {
                $pivotData[$subjectId] = ['school_year_id' => $student->academic_year_id];
            }
            $student->subjects()->sync($pivotData);

            //  Update student record
            $student->update([
                'curriculum_id' => $curriculum->id,
                'is_assess'     => 1
            ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Enrollment successful. Pending payment.',
                'data'      => [
                    'enrollment'        => $enrollment,
                    'curriculum_id'     => $curriculum->id,
                    'subjects'          => $subjects,
                    'total_units'       => $totalUnits,
                    'units_fee'         => $unitsFee,
                    'misc_fees'         => $miscFees,
                    'misc_fee_total'    => $miscFeeTotal,
                    'tuition_fee'       => $tuitionFee,
                    'total_amount'      => $totalFee,
                    'reference_number'  => $referenceNumber,
                    'enrollment_status' => $enrollmentStatus,
                    'payment_status'    => 'pending',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }


    //HELPERS
    public function getEnrollmentFees()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            //  Load student record
            $student = students::where('admission_id', $user->admission_id)->first();
            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student record not found.'
                ], 404);
            }

            // Get misc fees for the exact school year of the student
            $fees = DB::table('fees')
                ->select('id', 'fee_name', 'default_amount', 'school_year_id')
                ->where('is_active', 1)
                ->where('is_archived', 0)
                ->where('school_year_id', $student->academic_year_id) // 🔥 fixed
                ->get();

            if ($fees->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No applicable enrollment fees found for this student\'s school year.'
                ], 400);
            }

            $total = $fees->sum('default_amount');

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Applicable fees retrieved successfully.',
                'data'      => [
                    'fees'  => $fees,
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage()
            ], 500);
        }
    }


    //PAYMENT
    public function payOnline(Request $request)
    {
        $student = auth()->user();
        $amount = $request->amount * 100; // PayMongo uses centavos

        $checkout = Paymongo::checkout()->create([
            'line_items' => [[
                'name' => 'Tuition Payment',
                'amount' => $amount,
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => ['gcash', 'card'],
            'success_url' => url('/payment/success'),
            'cancel_url'  => url('/payment/cancel'),
            'description' => "Payment for student #{$student->student_number}",
            'metadata' => [
                'student_id' => $student->id, // 💡 no regex needed later
            ]
        ]);
        return response()->json([
            'isSuccess' => true,
            'checkout_url' => $checkout->checkout_url
        ]);
    }


    public function handleWebhook(Request $request)
    {
        $data = $request->all();

        if (
            isset($data['data']['attributes']['status']) &&
            $data['data']['attributes']['status'] === 'paid'
        ) {
            $attributes = $data['data']['attributes'];

            // ✅ Get student from metadata
            $studentId = $attributes['metadata']['student_id'] ?? null;

            if ($studentId) {
                payments::create([
                    'student_id'       => $studentId,
                    'amount'           => $attributes['amount'] / 100, // total transaction amount
                    'paid_amount'      => $attributes['amount'] / 100, // full paid for now
                    'remaining_balance' => 0, // or calculate if you’re tracking balance
                    'payment_method'   => $attributes['payment_method_used'] ?? 'unknown',
                    'status'           => 'success',
                    'receipt_no'       => 'RCPT-' . strtoupper(uniqid()),
                    'remarks'          => 'Online Payment via PayMongo',
                    'paid_at'          => now(),
                    'reference_no'     => $attributes['id'], // from PayMongo
                    'received_by'      => 'System' // since auto from webhook
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
