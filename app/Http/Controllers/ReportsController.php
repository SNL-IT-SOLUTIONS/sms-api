<?php

namespace App\Http\Controllers;

use App\Models\exam_schedules;
use App\Models\students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function getPassedExamReport()
    {
        try {
            //  Fetch all passed students with relationships
            $passedStudents = exam_schedules::with([
                'admission',
                'room',
                'building',
                'campus',

            ])
                ->where('exam_status', 'passed')
                ->get([
                    'id',
                    'admission_id',
                    'test_permit_no',
                    'exam_date',
                    'exam_time_from',
                    'exam_time_to',
                    'academic_year',
                    'exam_score',
                    'room_id',
                    'building_id',
                    'campus_id'
                ]);

            //  Count total passed
            $totalPassed = $passedStudents->count();

            return response()->json([
                'isSuccess'    => true,
                'message'      => 'Exam report generated successfully',
                'total_passed' => $totalPassed,
                'students'     => $passedStudents
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to generate exam report',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }


    public function getEnrolledStudentsReport()
    {
        try {
            // ✅ Fetch all enrolled students with admission details
            $enrolledStudents = Students::with([
                'examSchedule',
                'course',
                'curriculum',
                'section',
                'gradeLevel',
                'academicYear',
                'admission:id,first_name,last_name,middle_name,suffix,email,contact_number'
            ])
                ->where('is_enrolled', 1)
                ->get([
                    'id',
                    'student_number',
                    'admission_id',
                    'profile_img',
                    'course_id',
                    'curriculum_id',
                    'grade_level_id',
                    'section_id',
                    'academic_year_id',
                    'payment_status',
                    'enrollment_status'
                ]);

            // ✅ Count total enrolled
            $totalEnrolled = $enrolledStudents->count();

            return response()->json([
                'isSuccess'      => true,
                'message'        => 'Enrolled students report generated successfully',
                'total_enrolled' => $totalEnrolled,
                'students'       => $enrolledStudents
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to generate enrolled students report',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    public function getReconsiderExamReport()
    {
        try {
            // ✅ Fetch all students with reconsider exam status
            $reconsideredStudents = DB::table('exam_schedules')
                ->join('admissions', 'exam_schedules.admission_id', '=', 'admissions.id')
                ->where('exam_schedules.exam_status', 'reconsider')
                ->get([
                    'exam_schedules.id as exam_schedule_id',
                    'exam_schedules.test_permit_no',
                    'exam_schedules.exam_date',
                    'exam_schedules.exam_time_from',
                    'exam_schedules.exam_time_to',
                    'exam_schedules.academic_year',
                    'exam_schedules.exam_score',
                    'exam_schedules.room_id',
                    'exam_schedules.building_id',
                    'exam_schedules.campus_id',

                    // 👇 Admission details
                    'admissions.id as admission_id',
                    'admissions.first_name',
                    'admissions.last_name',
                    'admissions.middle_name',
                    'admissions.gender',
                    'admissions.email',
                    'admissions.contact_number',
                    'admissions.city',
                ]);

            // ✅ Count total reconsidered
            $totalReconsidered = $reconsideredStudents->count();

            return response()->json([
                'isSuccess'        => true,
                'message'          => 'Reconsider exam report generated successfully',
                'total_reconsider' => $totalReconsidered,
                'students'         => $reconsideredStudents
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
