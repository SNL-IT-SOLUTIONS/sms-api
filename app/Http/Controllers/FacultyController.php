<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SectionSubjectSchedule;
use App\Models\Accounts;
use Illuminate\Support\Facades\Auth;

class FacultyController extends Controller
{

    /**
     * Get all schedules for a specific faculty (teacher).
     */
    public function getMySchedules(Request $request)
    {
        try {
            // Get currently logged in user
            $facultyId = Auth::id();

            if (!$facultyId) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized. Please log in first.',
                ], 401);
            }

            $schedules = DB::table('section_subject_schedule as sss')
                ->join('sections as sec', 'sec.id', '=', 'sss.section_id')
                ->join('subjects as sub', 'sub.id', '=', 'sss.subject_id')
                ->leftJoin('building_rooms as br', 'br.id', '=', 'sss.room_id')
                ->leftJoin('courses as c', 'c.id', '=', 'sec.course_id')
                ->leftJoin('enrollments as en', 'en.section_id', '=', 'sec.id')
                ->select(
                    'sub.subject_code',
                    'sub.subject_name',
                    'sss.id as schedule_id',
                    'sec.section_name',
                    'sss.day',
                    'sss.time',
                    'br.room_name',
                    'c.course_name',
                    'c.course_code',
                    DB::raw('COUNT(en.id) as enrolled_students'),
                    DB::raw('sub.units as faculty_credit')
                )
                ->where('sss.teacher_id', $facultyId)
                ->groupBy(
                    'sub.subject_code',
                    'sub.subject_name',
                    'sss.id',
                    'sec.section_name',
                    'sss.day',
                    'sss.time',
                    'br.room_name',
                    'c.course_name',
                    'c.course_code',
                    'sub.units'
                )
                ->orderBy('sss.day')
                ->orderBy('sss.time')
                ->get();

            $formatted = $schedules->map(function ($s) {
                return [
                    'subject'           => $s->subject_name,
                    'specialization'    => $s->subject_code,
                    'schedule'          => $s->day . ' ' . $s->time,
                    'room'              => $s->room_name ?? '--',
                    'section'           => $s->section_name,
                    'course'            => $s->course_name ?? '--',
                    'schedule_id'       => $s->schedule_id,
                    'faculty_credit'    => $s->faculty_credit,
                    'enrolled_students' => $s->enrolled_students,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'schedules' => $formatted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch schedules.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get grade summary for classes handled by the faculty.
     */
    public function getGradeSummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'faculty_id' => 'required|exists:accounts,id',
            ]);

            $summary = DB::table('grade_scores as gs')
                ->join('grade_items as gi', 'gi.id', '=', 'gs.grade_item_id')
                ->join('subjects as sub', 'sub.id', '=', 'gi.subject_id')
                ->join('sections as sec', 'sec.id', '=', 'gi.section_id')
                ->select(
                    'sub.subject_name',
                    'sec.section_name',
                    DB::raw('AVG(gs.score) as avg_score'),
                    DB::raw('MIN(gs.score) as min_score'),
                    DB::raw('MAX(gs.score) as max_score'),
                    DB::raw('SUM(CASE WHEN gs.score >= 75 THEN 1 ELSE 0 END) as passed'),
                    DB::raw('SUM(CASE WHEN gs.score < 75 THEN 1 ELSE 0 END) as failed')
                )
                ->where('gi.faculty_id', $validated['faculty_id'])
                ->groupBy('sub.subject_name', 'sec.section_name')
                ->get();

            return response()->json([
                'isSuccess' => true,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to fetch grade summary.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
