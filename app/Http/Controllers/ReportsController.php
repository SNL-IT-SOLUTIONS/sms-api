<?php

namespace App\Http\Controllers;

use App\Models\exam_schedules;
use App\Models\students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function getPassedExamReport(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            $query = exam_schedules::with([
                'admission.course',
                'room',
                'building',
                'campus',
                'academicYear',
            ])->where('exam_status', 'passed');

            // 🔍 Search (student name or test permit no)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('admission', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('applicant_numer', 'like', "%$search%");
                    })
                        ->orWhere('test_permit_no', 'like', "%$search%");
                });
            }

            // 🎯 Filters

            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }
            if ($request->has('campus_id')) {
                $query->where('campus_id', $request->campus_id);
            }
            if ($request->filled('academic_program_id')) {
                $query->whereHas('admission', function ($q) use ($request) {
                    $q->where('academic_program_id', $request->academic_program_id);
                });
            }
            // 🏆 Ranking: Order by score descending
            $query->orderByDesc('exam_score');

            $passedStudents = $query->paginate($perPage, ['*'], 'page', $page);

            // 🚀 Add rank field manually
            $students = $passedStudents->getCollection()->map(function ($student, $index) use ($perPage, $page) {
                $student->rank = ($page - 1) * $perPage + ($index + 1);
                return $student;
            });

            return response()->json([
                'isSuccess'    => true,
                'message'      => 'Exam report generated successfully',
                'total_passed' => $passedStudents->total(),
                'students'     => $students,
                'pagination'   => [
                    'total'        => $passedStudents->total(),
                    'per_page'     => $passedStudents->perPage(),
                    'current_page' => $passedStudents->currentPage(),
                    'last_page'    => $passedStudents->lastPage(),
                ],

                'academic_year' => $students->first()->academicYear->school_year ?? null,
                'semester'      => $students->first()->academicYear->semester ?? null,
                'course_name'   => $students->first()->admission->course->course_name ?? null, // 👈 added here

            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to generate exam report',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }



    public function getEnrolledStudentsReport(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            $query = students::with([
                'examSchedule',
                'course',
                'curriculum',
                'section',
                'gradeLevel',
                'academicYear',
                'admission:id,first_name,last_name,middle_name,suffix,email,contact_number'
            ])->where('is_enrolled', 1);

            // 🔍 Search (student number or name)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('student_number', 'like', "%$search%")
                    ->orWhereHas('admission', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%");
                    });
            }

            // 🎯 Filters
            if ($request->has('course_id')) {
                $query->where('course_id', $request->course_id);
            }
            if ($request->has('grade_level_id')) {
                $query->where('grade_level_id', $request->grade_level_id);
            }
            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            $enrolledStudents = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'isSuccess'      => true,
                'message'        => 'Enrolled students report generated successfully',
                'total_enrolled' => $enrolledStudents->total(),
                'students'       => $enrolledStudents->items(),
                'pagination'     => [
                    'total'        => $enrolledStudents->total(),
                    'per_page'     => $enrolledStudents->perPage(),
                    'current_page' => $enrolledStudents->currentPage(),
                    'last_page'    => $enrolledStudents->lastPage(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to generate enrolled students report',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }


    public function getReconsiderExamReport(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 5);
            $page    = $request->get('page', 1);

            $query = exam_schedules::with([
                'admission.course',
                'room',
                'building',
                'campus',
                'academicYear',
            ])->where('exam_status', 'reconsider');

            // 🔍 Search (student name or test permit no)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('admission', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('applicant_number', 'like', "%$search%");
                    })
                        ->orWhere('test_permit_no', 'like', "%$search%");
                });
            }

            // 🎯 Filters
            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }
            if ($request->filled('campus_id')) {
                $query->where('campus_id', $request->campus_id);
            }
            if ($request->filled('academic_program_id')) {
                $query->whereHas('admission', function ($q) use ($request) {
                    $q->where('academic_program_id', $request->academic_program_id);
                });
            }

            // 📊 Paginate results
            $reconsideredStudents = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'isSuccess'          => true,
                'message'            => 'Reconsider exam report generated successfully',
                'total_reconsider'   => $reconsideredStudents->total(),
                'students'           => $reconsideredStudents->items(),
                'pagination'         => [
                    'total'        => $reconsideredStudents->total(),
                    'per_page'     => $reconsideredStudents->perPage(),
                    'current_page' => $reconsideredStudents->currentPage(),
                    'last_page'    => $reconsideredStudents->lastPage(),
                ],
                'academic_year' => $reconsideredStudents->first()->academicYear->school_year ?? null,
                'semester'      => $reconsideredStudents->first()->academicYear->semester ?? null,
                'course_name'   => $reconsideredStudents->first()->admission->course->course_name ?? null,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to generate reconsider exam report',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }
}
