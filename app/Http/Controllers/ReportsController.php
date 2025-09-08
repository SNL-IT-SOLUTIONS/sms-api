<?php

namespace App\Http\Controllers;

use App\Models\exam_schedules;
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
}
