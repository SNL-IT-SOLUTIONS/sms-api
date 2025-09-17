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
use App\Models\fees;



use Illuminate\Http\Request;

class StudentsController extends Controller
{
    public function studentDashboard(Request $request)
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // 🎯 Allow filtering by school_year_id
            if ($request->has('school_year_id') && !empty($request->school_year_id)) {
                $currentSchoolYear = DB::table('school_years')
                    ->where('id', $request->school_year_id)
                    ->first();
            } else {
                // Default → active school year
                $currentSchoolYear = DB::table('school_years')
                    ->where('is_active', 1)
                    ->first();
            }

            if (!$currentSchoolYear) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No school year found.'
                ], 404);
            }

            // ✅ Enrolled units
            $enrolledUnits = DB::table('student_subjects as ss')
                ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                ->where('ss.student_id', $student->id)
                ->where('ss.school_year_id', $currentSchoolYear->id)
                ->sum('s.units');

            // ✅ GWA
            $grades = DB::table('student_subjects as ss')
                ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                ->where('ss.student_id', $student->id)
                ->where('ss.school_year_id', $currentSchoolYear->id)
                ->whereNotNull('ss.final_rating')
                ->select('ss.final_rating', 's.units')
                ->get();

            $totalUnits = 0;
            $weightedSum = 0;
            $gwa = null;

            foreach ($grades as $grade) {
                if ($grade->final_rating !== null) {
                    $weightedSum += $grade->final_rating * $grade->units;
                    $totalUnits += $grade->units;
                }
            }

            if ($totalUnits > 0) {
                $gwa = round($weightedSum / $totalUnits, 2);
            }

            // ✅ Enrollment & Outstanding balance
            $latestEnrollment = enrollments::where('student_id', $student->id)
                ->where('school_year_id', $currentSchoolYear->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $outstandingBalance = 0;
            if ($latestEnrollment) {
                $totalPaid = payments::where('student_id', $student->id)
                    ->where('school_year_id', $currentSchoolYear->id)
                    ->sum('paid_amount');

                $outstandingBalance = $latestEnrollment->total_tuition_fee;
            }

            $paymentStatus = $latestEnrollment->payment_status ?? 'Not Enrolled';

            return response()->json([
                'isSuccess' => true,
                'dashboard' => [
                    'enrolled_units' => $enrolledUnits,
                    'gwa' => $gwa,
                    'outstanding_balance' => number_format($outstandingBalance, 2),
                    'payment_status' => $paymentStatus,
                    'school_year' => $currentSchoolYear->school_year,
                    'semester' => $currentSchoolYear->semester
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve dashboard data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getCOR()
    {
        try {
            $authStudent = auth()->user();

            if (!$authStudent) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            // ✅ Student + Admission info with gender & curriculum
            $student = DB::table('students as s')
                ->join('admissions as a', 'a.id', '=', 's.admission_id')
                ->join('sections as sec', 'sec.id', '=', 's.section_id')
                ->leftJoin('courses as c', 'c.id', '=', 'a.academic_program_id')
                ->leftJoin('grade_levels as gl', 'gl.id', '=', 's.grade_level_id')
                ->leftJoin('school_years as sy', 'sy.id', '=', 's.academic_year_id')
                ->leftJoin('curriculums as cur', 'cur.id', '=', 's.curriculum_id')
                ->select(
                    's.id as student_id',
                    's.student_number',
                    DB::raw("CONCAT(a.first_name, ' ', a.last_name) as student_name"),
                    'a.gender',
                    'sec.section_name',
                    'c.course_name as course',
                    'gl.grade_level as grade_level',
                    'sy.school_year',
                    'cur.curriculum_name',
                    's.curriculum_id'
                )
                ->where('s.id', $authStudent->id)
                ->first();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Student not found.'
                ], 404);
            }

            // ✅ Subjects grouped by school year + semester (fixed with LEFT JOIN)
            $subjects = DB::table('student_subjects as ss')
                ->join('subjects as subj', 'subj.id', '=', 'ss.subject_id')
                ->join('students as stu', 'stu.id', '=', 'ss.student_id')
                ->leftJoin('section_subject_schedule as sched', function ($join) {
                    $join->on('sched.subject_id', '=', 'ss.subject_id')
                        ->on('sched.section_id', '=', 'stu.section_id');
                })
                ->leftJoin('accounts as t', 't.id', '=', 'sched.teacher_id')
                ->join('school_years as sy', 'sy.id', '=', 'ss.school_year_id')
                ->select(
                    'sy.school_year',
                    'sy.semester',
                    'subj.id as subject_id',
                    'subj.subject_code',
                    'subj.subject_name',
                    'subj.units',
                    DB::raw("IFNULL(sched.day, 'TBA') as schedule_day"),
                    DB::raw("IFNULL(CONCAT(sched.start_time, ' - ', sched.end_time), 'TBA') as schedule_time"),
                    DB::raw("IFNULL(CONCAT(t.given_name, ' ', t.surname), 'TBA') as teacher_name"),
                    'ss.final_rating',
                    'ss.remarks'
                )
                ->where('ss.student_id', $authStudent->id)
                ->orderBy('sy.school_year')
                ->orderBy('sy.semester')
                ->get()
                ->groupBy(function ($row) {
                    return $row->school_year . ' - ' . $row->semester;
                });
            $fees = DB::table('enrollments')
                ->where('student_id', $authStudent->id)
                ->orderBy('created_at', 'desc') // latest enrollment
                ->select('tuition_fee', 'misc_fee', 'total_tuition_fee', 'payment_status')
                ->first();

            // ✅ Total units
            $totalUnits = collect($subjects)->flatten()->sum('units');

            return response()->json([
                'isSuccess' => true,
                'message' => 'COR retrieved successfully.',
                'student'  => $student,
                'subjects_by_term' => $subjects,
                'total_units' => $totalUnits,
                'fees' => $fees
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve COR.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function getStudentProfile()
    {
        try {
            $student = Auth::user(); // currently logged-in student

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Not authenticated.',
                ], 401);
            }

            // ✅ Only fetch needed fields
            $profile = Students::select([
                'id',
                'student_number',
                'profile_img',
                'student_status',
                'section_id',
                'course_id',
                'academic_year_id',
                'grade_level_id',
                'enrollment_status',
                'payment_status',
                'is_active',
                'curriculum_id',
                'admission_id'
            ])
                ->with([
                    'course:id,course_name,course_code',
                    'section:id,section_name',
                    'admission' => function ($q) {
                        $q->select([
                            'id',
                            'first_name',
                            'middle_name',
                            'last_name',
                            'suffix',
                            'gender',
                            'birthdate',
                            'email',
                            'contact_number',
                            'province',
                            'city',
                            'barangay',
                            'guardian_name',
                            'guardian_contact',
                            'mother_name',
                            'mother_contact',
                            'father_name',
                            'father_contact'
                        ]);
                    }
                ])
                ->where('id', $student->id)
                ->first();

            if (!$profile) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student profile not found.',
                ], 404);
            }

            return response()->json([
                'isSuccess' => true,
                'data'      => $profile,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function updateStudentProfile(Request $request)
    {
        try {
            $student = Auth::user(); // currently logged-in student

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Not authenticated.',
                ], 401);
            }

            // ✅ Validation rules
            $validated = $request->validate([
                'profile_img'       => 'sometimes|nullable|string|max:255',
                'student_status'    => 'sometimes|nullable|string|max:50',
                'enrollment_status' => 'sometimes|nullable|string|max:50',
                'payment_status'    => 'sometimes|nullable|string|max:50',

                // Admissions side
                'first_name'       => 'sometimes|required|string|max:100',
                'middle_name'      => 'sometimes|nullable|string|max:100',
                'last_name'        => 'sometimes|required|string|max:100',
                'suffix'           => 'sometimes|nullable|string|max:20',
                'gender'           => 'sometimes|required|string|in:male,female',
                'birthdate'        => 'sometimes|nullable|date',
                'email'            => 'sometimes|required|email|max:150',
                'contact_number'   => 'sometimes|nullable|string|max:20',
                'province'         => 'sometimes|nullable|string|max:150',
                'city'             => 'sometimes|nullable|string|max:150',
                'barangay'         => 'sometimes|nullable|string|max:150',

                // Guardian info
                'guardian_name'    => 'sometimes|nullable|string|max:150',
                'guardian_contact' => 'sometimes|nullable|string|max:20',
                'mother_name'      => 'sometimes|nullable|string|max:150',
                'mother_contact'   => 'sometimes|nullable|string|max:20',
                'father_name'      => 'sometimes|nullable|string|max:150',
                'father_contact'   => 'sometimes|nullable|string|max:20',
            ]);

            // ✅ Update student
            $studentModel = Students::findOrFail($student->id);
            $studentModel->update([
                'profile_img'      => $validated['profile_img']      ?? $studentModel->profile_img,
                'student_status'   => $validated['student_status']   ?? $studentModel->student_status,
                'enrollment_status' => $validated['enrollment_status'] ?? $studentModel->enrollment_status,
                'payment_status'   => $validated['payment_status']   ?? $studentModel->payment_status,
            ]);

            // ✅ Update admission record
            $admission = $studentModel->admission;
            if ($admission) {
                $admission->update([
                    'first_name'       => $validated['first_name'],
                    'middle_name'      => $validated['middle_name']   ?? $admission->middle_name,
                    'last_name'        => $validated['last_name'],
                    'suffix'           => $validated['suffix']        ?? $admission->suffix,
                    'gender'           => $validated['gender'],
                    'birthdate'        => $validated['birthdate']     ?? $admission->birthdate,
                    'email'            => $validated['email'],
                    'contact_number'   => $validated['contact_number'] ?? $admission->contact_number,
                    'province'         => $validated['province']      ?? $admission->province,
                    'city'             => $validated['city']          ?? $admission->city,
                    'barangay'         => $validated['barangay']      ?? $admission->barangay,
                    'guardian_name'    => $validated['guardian_name'] ?? $admission->guardian_name,
                    'guardian_contact' => $validated['guardian_contact'] ?? $admission->guardian_contact,
                    'mother_name'      => $validated['mother_name']   ?? $admission->mother_name,
                    'mother_contact'   => $validated['mother_contact'] ?? $admission->mother_contact,
                    'father_name'      => $validated['father_name']   ?? $admission->father_name,
                    'father_contact'   => $validated['father_contact'] ?? $admission->father_contact,
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Profile updated successfully',
                'data'      => $this->getStudentProfile()->getData()->data // reuse the profile getter for updated data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Error updating profile: ' . $e->getMessage(),
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
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            // ✅ Load latest enrollment for this student
            $enrollment = enrollments::where('student_id', $student->id)
                ->latest()
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No enrollment record found.'
                ], 404);
            }

            // ✅ Fetch misc fee for the same school_year_id
            $miscFee = fees::where('school_year_id', $enrollment->school_year_id)->first();

            if (!$miscFee) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Miscellaneous fee not set for this school year.'
                ], 422);
            }

            // ✅ Payments history (filter per SY)
            $payments = payments::where('student_id', $student->id)
                ->where('school_year_id', $enrollment->school_year_id)
                ->orderBy('created_at', 'desc')
                ->get();

            // 👉 Use "amount" instead of "paid_amount"
            $totalPaid = $payments->sum('paid_amount');

            // ✅ Subjects (from relationship)
            $subjects = $student->subjects->map(function ($sub) {
                return [
                    'code'  => $sub->subject_code,
                    'name'  => $sub->subject_name,
                    'units' => $sub->units
                ];
            });

            // ✅ Assessment summary
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
                        'amount'      => number_format((float) $enrollment->misc_fee, 2)
                    ],
                    [
                        'description' => 'Tuition Fee',
                        'amount'      => number_format((float) $enrollment->tuition_fee, 2)
                    ],
                    [
                        'description' => 'Original Total Fee',
                        'amount'      => number_format((float) $enrollment->original_tuition_fee, 2)
                    ],

                    [
                        'description' => 'Total Paid',
                        'amount'      => number_format($totalPaid, 2)
                    ],
                    [
                        'description' => 'Remaining Balance',
                        'amount'      => number_format((float) $enrollment->total_tuition_fee, 2)
                    ]
                ],

                'payment_history' => $payments->map(function ($p) {
                    return [
                        'transaction' => $p->transaction,
                        'amount'      => number_format((float) $p->amount, 2),
                        'method'      => $p->payment_method,
                        'reference'   => $p->reference_no,
                        'receipt_no'  => $p->receipt_no,
                        'remarks'     => $p->remarks,
                        'paid_at'     => $p->paid_at,
                        'status'      => $p->status,
                    ];
                })
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
    public function getMySchedule(Request $request)
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            $schoolYearId = $request->input('school_year_id');

            // ✅ Query schedules with school year filtering + include school year
            $schedules = DB::table('student_subjects as ss')
                ->join('section_subject_schedule as sched', 'sched.subject_id', '=', 'ss.subject_id')
                ->leftJoin('subjects as subj', 'subj.id', '=', 'sched.subject_id')
                ->leftJoin('building_rooms as r', 'r.id', '=', 'sched.room_id')
                ->leftJoin('accounts as t', 't.id', '=', 'sched.teacher_id')
                ->leftJoin('school_years as sy', 'sy.id', '=', 'ss.school_year_id')
                ->where('ss.student_id', $student->id)
                ->when($schoolYearId, function ($q) use ($schoolYearId) {
                    $q->where('ss.school_year_id', $schoolYearId);
                })
                ->select(
                    'subj.subject_code',
                    'subj.subject_name',
                    'sched.day',
                    'sched.start_time',
                    'sched.end_time',
                    'r.room_name as room',
                    DB::raw("CONCAT(t.given_name, ' ', t.surname) as teacher"),
                    'sy.id as school_year_id',
                    DB::raw("CONCAT(sy.school_year, ' ', sy.semester)as school_year") // <- adjust if your column is different (like year_start/year_end)

                )
                ->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No schedules found for this student.'
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Student schedule retrieved successfully.',
                'schedules' => $schedules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve schedule.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }




    public function getMyGrades(Request $request)
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            // ✅ Build query with join on pivot + school_years
            $query = DB::table('student_subjects as ss')
                ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                ->join('school_years as sy', 'ss.school_year_id', '=', 'sy.id')
                ->where('ss.student_id', $student->id)
                ->select(
                    's.subject_code',
                    's.subject_name',
                    's.units',
                    'ss.final_rating',
                    'ss.remarks',
                    'ss.school_year_id',
                    DB::raw("CONCAT(sy.school_year, ' - ', sy.semester) as school_year_name")
                );

            if ($request->has('school_year_id')) {
                $query->where('ss.school_year_id', $request->school_year_id);
            }

            $grades = $query->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No grades found for this student.'
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Student grades retrieved successfully.',
                'grades'    => $grades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve grades.',
                'error'     => $e->getMessage()
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

            // 🔽 Load student record
            $student = students::where('admission_id', $user->admission_id)->first();
            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Student record not found.'
                ], 404);
            }

            // 🔽 Check unpaid enrollments
            $hasUnpaid = enrollments::where('student_id', $student->id)
                ->where('payment_status', 'Unpaid')
                ->exists();

            if ($hasUnpaid) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'You cannot enroll while you still have an unpaid enrollment.'
                ], 400);
            }

            // ✅ Determine school_year_id automatically
            $lastEnrollment = enrollments::where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastEnrollment) {
                // 🔽 Continuing student → get next semester
                $nextSchoolYear = DB::table('school_years')
                    ->where('id', '>', $lastEnrollment->school_year_id)
                    ->orderBy('id')
                    ->first();

                if (!$nextSchoolYear) {
                    return response()->json([
                        'isSuccess' => false,
                        'message'   => 'No next semester available. Contact registrar.'
                    ], 400);
                }

                $schoolYearId = $nextSchoolYear->id;

                // 🔽 GRADES CHECK
                $hasFailed = DB::table('student_subjects')
                    ->where('student_id', $student->id)
                    ->where(function ($q) {
                        $q->whereNull('final_rating')   // INC / no grade
                            ->orWhere('final_rating', '>', 3.0); // Failed
                    })
                    ->exists();

                if ($hasFailed) {
                    return response()->json([
                        'isSuccess' => false,
                        'message'   => 'You cannot enroll next semester because you have failing/INC grades.'
                    ], 400);
                }
            } else {
                // 🔽 Freshman / first-time enrollees
                if (!$student->academic_year_id) {
                    return response()->json([
                        'isSuccess' => false,
                        'message'   => 'No academic year assigned. Please contact registrar.'
                    ], 400);
                }

                // Use the academic_year_id from approveStudent
                $schoolYearId = $student->academic_year_id;
            }

            // 🔽 Promotion Check: After 2 semesters → bump grade level
            $currentGrade = $student->grade_level_id;

            if ($currentGrade) {
                $completedSemesters = enrollments::where('student_id', $student->id)
                    ->where('grade_level_id', $currentGrade)
                    ->count();

                if ($completedSemesters >= 2) {
                    $nextGrade = DB::table('grade_levels')
                        ->where('id', '>', $currentGrade)
                        ->orderBy('id')
                        ->first();

                    if ($nextGrade) {
                        $student->update([
                            'grade_level_id' => $nextGrade->id
                        ]);
                        $currentGrade = $nextGrade->id;
                    }
                }
            }

            // 🔽 Update student’s current academic year
            $student->update(['academic_year_id' => $schoolYearId]);

            // 🔽 Validate subjects
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

            // 🔽 PREREQUISITE CHECK
            $studentHasRecords = DB::table('student_subjects')
                ->where('student_id', $student->id)
                ->exists();

            $failedPrereqs = [];
            foreach ($subjects as $subject) {
                foreach ($subject->prerequisites as $prereq) {
                    $hasPassed = DB::table('student_subjects')
                        ->where('student_id', $student->id)
                        ->where('subject_id', $prereq->id)
                        ->where('final_rating', '<=', 3.0)
                        ->exists();

                    if (!$studentHasRecords && $subject->prerequisites->count() > 0) {
                        $failedPrereqs[] = [
                            'subject' => $subject->subject_name,
                            'prerequisite' => $prereq->subject_name,
                            'reason' => 'New enrolee cannot take advanced subject without prerequisites.'
                        ];
                    }

                    if ($studentHasRecords && !$hasPassed) {
                        $failedPrereqs[] = [
                            'subject' => $subject->subject_name,
                            'prerequisite' => $prereq->subject_name,
                            'reason' => 'Prerequisite not passed.'
                        ];
                    }
                }
            }

            if (!empty($failedPrereqs)) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Some prerequisites are not satisfied.',
                    'failed'    => $failedPrereqs
                ], 400);
            }

            // 🔽 Curriculum check
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

            // 🔽 Fee computation
            $totalUnits = $subjects->sum('units');
            $unitRate   = 200;
            $unitsFee   = $totalUnits * $unitRate;
            $tuitionFee = $unitsFee;

            $miscFees = DB::table('fees')
                ->where('is_active', 1)
                ->where('is_archived', 0)
                ->where('school_year_id', $schoolYearId)
                ->get();

            if ($miscFees->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No applicable fees found for this school year. Please contact the registrar.'
                ], 400);
            }

            $miscFeeTotal = $miscFees->sum('default_amount');
            $totalFee     = $tuitionFee + $miscFeeTotal;

            // 🔽 Enrollment status
            $hasAllRequirements = $student->has_form137
                && $student->has_form138
                && $student->has_good_moral
                && $student->has_birth_certificate
                && $student->has_certificate_of_completion;

            $enrollmentStatus = $hasAllRequirements ? 'Officially Enrolled' : 'Unofficial Enrolled';

            // 🔽 Reference number
            do {
                $referenceNumber = mt_rand(1000000, 9999999);
            } while (enrollments::where('reference_number', $referenceNumber)->exists());

            // 🔽 Create enrollment
            $enrollment = enrollments::create([
                'student_id'           => $student->id,
                'school_year_id'       => $schoolYearId,
                'grade_level_id'       => $currentGrade, // ✅ include grade level here
                'tuition_fee'          => $tuitionFee,
                'misc_fee'             => $miscFeeTotal,
                'original_tuition_fee' => $totalFee,
                'total_tuition_fee'    => $totalFee,
                'payment_status'       => 'Unpaid',
                'transaction'          => 'Enrollment',
                'reference_number'     => $referenceNumber,
                'created_by'           => $student->id
            ]);

            foreach ($miscFees as $fee) {
                DB::table('enrollment_fees')->insert([
                    'enrollment_id' => $enrollment->id,
                    'fee_id'        => $fee->id,
                    'amount'        => $fee->default_amount,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // 🔽 Sync subjects
            $pivotData = [];
            foreach ($validated['subjects'] as $subjectId) {
                $pivotData[$subjectId] = ['school_year_id' => $schoolYearId];
            }
            $student->subjects()->syncWithoutDetaching($pivotData);

            // 🔽 Update student
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
                    'grade_level_id'    => $currentGrade
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
