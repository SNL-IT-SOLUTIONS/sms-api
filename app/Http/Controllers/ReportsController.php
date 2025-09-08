<?php

namespace App\Http\Controllers;

use App\Models\exam_schedules;
use App\Models\students;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function getPassedExamReport()
    {
        try {
            // ✅ Fetch all passed students with relationships
            $passedStudents = exam_schedules::with([
                'admission',   // admission details
                'room',        // room details
                'building',    // building details
                'campus',      // campus details

            ])
                ->where('exam_status', 'passed')
                ->get([
                    'id',
                    'admission_id',
                    'test_permit_no',
                    'exam_date',
                    'exam_time_from',
                    'exam_time_to',
                    'academic_year', // make sure FK matches your table
                    'exam_score',
                    'room_id',
                    'building_id',
                    'campus_id'
                ]);

            // ✅ Count total passed
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
}
