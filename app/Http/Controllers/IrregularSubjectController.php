<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IrregularSubject;
use App\Models\student_subjects;
use App\Models\students;
use App\Models\enrollments;
use App\Models\payments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\IrregularSubjectFee;

class IrregularSubjectController extends Controller
{

    public function getCurriculumSubjects(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $student = students::where('admission_id', $user->admission_id)->first();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Authenticated user is not a student.',
                ], 403);
            }

            if (!$student->curriculum_id) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Student curriculum not found.',
                ], 404);
            }

            // Get IDs of subjects the student already has in student_subjects
            $takenSubjectIds = student_subjects::where('student_id', $student->id)
                ->pluck('subject_id')
                ->toArray();

            // Fetch curriculum subjects excluding already taken subjects
            $subjects = DB::table('curriculum_subject')
                ->join('subjects', 'curriculum_subject.subject_id', '=', 'subjects.id')
                ->join('school_years', 'subjects.school_year_id', '=', 'school_years.id')
                ->where('curriculum_subject.curriculum_id', $student->curriculum_id)
                ->whereNotIn('subjects.id', $takenSubjectIds) // <-- exclude taken
                ->select(
                    'subjects.id',
                    'subjects.subject_code',
                    'subjects.subject_name',
                    'subjects.units',
                    'subjects.subject_type',
                    'school_years.id as school_year_id',
                    'school_years.school_year',
                    'school_years.semester'
                )
                ->get();

            $grouped = $subjects->groupBy('school_year_id')->map(function ($subjects, $schoolYearId) {
                $first = $subjects->first();
                return [
                    'school_year_id' => $schoolYearId,
                    'school_year' => $first->school_year,
                    'semester' => $first->semester,
                    'subjects' => $subjects->values(),
                ];
            })->values();

            return response()->json([
                'isSuccess' => true,
                'curriculum_id' => $student->curriculum_id,
                'subjects_by_school_year' => $grouped
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve curriculum subjects.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function addSubject(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $student = students::where('admission_id', $user->admission_id)->first();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authenticated user is not a student.'
            ], 403);
        }

        if (!$student->curriculum_id) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Student curriculum not found.'
            ], 404);
        }

        // Validate multiple subjects
        $validated = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
            'remarks' => 'nullable|string|max:255'
        ]);

        $addedSubjects = [];
        $errors = [];

        foreach ($validated['subject_ids'] as $subjectId) {

            // Check subject belongs to curriculum
            $isInCurriculum = DB::table('curriculum_subject')
                ->where('curriculum_id', $student->curriculum_id)
                ->where('subject_id', $subjectId)
                ->exists();

            if (!$isInCurriculum) {
                $errors[] = "Subject ID $subjectId is not part of the student’s curriculum.";
                continue;
            }

            // Check if student already took the subject in any school year **and is not dropped**
            $taken = DB::table('student_subjects')
                ->where('student_id', $student->id)
                ->where('subject_id', $subjectId)
                ->where(function ($query) {
                    $query->whereNull('remarks') // not dropped
                        ->orWhere('remarks', '<>', 'Dropped'); // or any other remarks
                })
                ->exists();

            if ($taken) {
                $errors[] = "Subject ID $subjectId is already taken in a previous or current school year.";
                continue;
            }

            // Check if already added as irregular
            $irregular = IrregularSubject::where('student_id', $student->id)
                ->where('subject_id', $subjectId)
                ->whereNull('remarks')
                ->exists();

            if ($irregular) {
                $errors[] = "Subject ID $subjectId is already added as irregular.";
                continue;
            }

            // Add the subject
            $irregularSubject = IrregularSubject::create([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'status' => 'pending',
                'remarks' => $validated['remarks'] ?? null,
                'school_year_id' => $student->academic_year_id,
                'final_rating' => null,
                'remarks' => null,
            ]);

            // Calculate fee
            $units = DB::table('subjects')->where('id', $subjectId)->value('units');
            $perUnitRate = 200; // fixed rate
            $feeAmount = $units * $perUnitRate;

            // Create fee record
            IrregularSubjectFee::create([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'units' => $units,
                'fee' => $feeAmount,
                'school_year_id' => $student->academic_year_id,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            $addedSubjects[] = $irregularSubject;
        }

        return response()->json([
            'isSuccess' => true,
            'added_subjects' => $addedSubjects,
            'errors' => $errors,
        ], 201);
    }



    //DROP SUBJECT  
    public function getPendingGrades(Request $request)
    {
        try {
            $student = auth()->user();

            if (!$student) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            // Build query to get only subjects with NULL final_rating
            $query = DB::table('student_subjects as ss')
                ->join('subjects as s', 'ss.subject_id', '=', 's.id')
                ->join('school_years as sy', 'ss.school_year_id', '=', 'sy.id')
                ->where('ss.student_id', $student->id)
                ->whereNull('ss.final_rating')
                ->select(
                    'ss.id as id',
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

            $pendingGrades = $query->get();

            if ($pendingGrades->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No pending grades found for this student.'
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Pending grades retrieved successfully.',
                'grades'    => $pendingGrades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve pending grades.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    public function dropSubject(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $student = students::where('admission_id', $user->admission_id)->first();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authenticated user is not a student.'
            ], 403);
        }

        $subject = student_subjects::where('id', $id)
            ->where('student_id', $student->id)
            ->first();

        if (!$subject) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Subject not found or does not belong to you.'
            ], 404);
        }

        if (!is_null($subject->final_rating)) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Cannot drop a subject that already has a final rating.'
            ], 400);
        }

        $request->validate([
            'remarks' => 'required|string|max:255', // ✅ Validate reason
        ]);

        // Check if a pending request already exists
        $existingRequest = DB::table('subject_drop_requests')
            ->where('student_subject_id', $subject->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'You already have a pending drop request for this subject.'
            ], 400);
        }

        DB::transaction(function () use ($subject, $student, $request) {
            // Create pending drop request with reason
            DB::table('subject_drop_requests')->insert([
                'student_subject_id' => $subject->id,
                'student_id'         => $student->id,
                'status'             => 'pending',
                'remarks'            => $request->remarks, // Save reason here
                'requested_by'       => $student->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Update remarks in student_subjects
            $subject->remarks = 'Pending Drop';
            $subject->save();
        });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Drop request submitted successfully. Subject marked as "Pending Drop".'
        ], 200);
    }



    public function approveDrop($requestId)
    {
        $request = DB::table('subject_drop_requests')->where('id', $requestId)->first();
        if (!$request) return response()->json(['message' => 'Request not found'], 404);

        DB::transaction(function () use ($request) {
            student_subjects::where('id', $request->student_subject_id)
                ->update(['remarks' => 'Dropped']);

            DB::table('subject_drop_requests')
                ->where('id', $request->id)
                ->update(['status' => 'approved']);
        });

        return response()->json(['message' => 'Drop request approved']);
    }

    public function rejectDrop($requestId)
    {
        $request = DB::table('subject_drop_requests')->where('id', $requestId)->first();
        if (!$request) {
            return response()->json(['message' => 'Request not found'], 404);
        }

        DB::transaction(function () use ($request) {
            // Update the drop request status to 'rejected'
            DB::table('subject_drop_requests')
                ->where('id', $request->id)
                ->update(['status' => 'rejected']);
        });

        return response()->json(['message' => 'Drop request rejected']);
    }




    //REGISTRAR SIDE

    public function getPendings(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $student = students::where('admission_id', $user->admission_id)->first();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Authenticated user is not a student.'
            ], 403);
        }

        // Fetch pending irregular subjects
        $pendings = IrregularSubject::with('subject')
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->whereNull('remarks')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'pendings' => $pendings
        ], 200);
    }




    public function approveSubject($id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        // Find the irregular subject
        $irregularSubject = IrregularSubject::find($id);

        if (!$irregularSubject) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Irregular subject not found.'
            ], 404);
        }

        // Only allow approval if status is pending
        if ($irregularSubject->status !== 'pending') {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Subject is already processed.'
            ], 400);
        }

        $studentId = $irregularSubject->student_id;
        $subjectId = $irregularSubject->subject_id;

        // ❗ Check if subject already exists in student_subjects
        $existing = student_subjects::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->first();

        // CASE 1: Student already passed/graded → DO NOT ALLOW
        if ($existing && $existing->final_rating !== null) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Student has already completed this subject.'
            ], 400);
        }

        // CASE 2: Student dropped before → revive instead of creating new duplicate
        if ($existing && $existing->remarks === 'Dropped') {
            $existing->remarks = null;
            $existing->final_rating = null;
            $existing->school_year_id = $irregularSubject->school_year_id;
            $existing->save();

            $irregularSubject->status = 'approved';
            $irregularSubject->remarks = null;
            $irregularSubject->save();

            do {
                $referenceNumber = mt_rand(1000000, 9999999);
            } while (enrollments::where('reference_number', $referenceNumber)->exists());

            // 🔹 Add to enrollments directly
            $enrollment = enrollments::create([
                'transaction'       => 'Irregular Subject',
                'reference_number'  => $referenceNumber,
                'student_id'        => $studentId,
                'school_year_id'    => $irregularSubject->school_year_id,
                'grade_level_id'    => null, // optional
                'tuition_fee'       => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'misc_fee'          => 0,
                'original_tuition_fee' => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'total_tuition_fee' => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'payment_status'    => 'Unpaid',
                'created_by'        => $user->id,
                'updated_by'        => $user->id,
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Subject approved and added to enrollments successfully.',
                'data' => $enrollment
            ], 200);
        }

        // CASE 3: No record exists → create new one
        if (!$existing) {
            $studentSubject = student_subjects::create([
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'school_year_id' => $irregularSubject->school_year_id,
                'final_rating' => null,
                'remarks' => null,
            ]);

            $irregularSubject->status = 'approved';
            $irregularSubject->remarks = null;
            $irregularSubject->save();

            do {
                $referenceNumber = mt_rand(1000000, 9999999);
            } while (enrollments::where('reference_number', $referenceNumber)->exists());


            // 🔹 Add to enrollments directly
            $enrollment = enrollments::create([
                'transaction'       => 'Irregular Subject',
                'reference_number'  => $referenceNumber,
                'student_id'        => $studentId,
                'school_year_id'    => $irregularSubject->school_year_id,
                'grade_level_id'    => null, // optional
                'tuition_fee'       => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'misc_fee'          => 0,
                'original_tuition_fee' => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'total_tuition_fee' => DB::table('subjects')->where('id', $subjectId)->value('units') * 200,
                'payment_status'    => 'Unpaid',
                'created_by'        => $user->id,
                'updated_by'        => $user->id,
            ]);
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Subject approved and added to enrollments successfully.',
            'data' => $enrollment ?? null
        ], 200);
    }





    public function rejectSubject(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        // Validate that a reason is provided
        $validated = $request->validate([
            'remarks' => 'required|string|max:255'
        ]);

        // Find the irregular subject
        $irregularSubject = IrregularSubject::where('id', $id)
            ->first();

        if (!$irregularSubject) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Irregular subject not found.'
            ], 404);
        }
        // Update status to rejected with remarks
        $irregularSubject->status = 'rejected';
        $irregularSubject->remarks = $validated['remarks'];
        $irregularSubject->save();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Irregular subject request rejected successfully.',
            'data' => $irregularSubject
        ], 200);
    }
}
