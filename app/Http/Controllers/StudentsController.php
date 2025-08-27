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
            'student_name' => $student->examSchedule?->admission?->first_name . ' ' . $student->examSchedule?->admission?->last_name,
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
            'bill' => $assessment
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


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







}
    

