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
            $faculty = Auth::user();

            if (!$faculty) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized. Please log in first.',
                ], 401);
            }

            $facultyId = $faculty->id;

            $schedules = DB::table('section_subject_schedule as sss')
                ->join('sections as sec', 'sec.id', '=', 'sss.section_id')
                ->join('subjects as sub', 'sub.id', '=', 'sss.subject_id')
                ->leftJoin('building_rooms as br', 'br.id', '=', 'sss.room_id')
                ->leftJoin('courses as c', 'c.id', '=', 'sec.course_id')
                ->leftJoin('students as st', 'st.section_id', '=', 'sec.id')
                ->select(
                    'sub.subject_code',
                    'sub.subject_name',
                    'sss.id as schedule_id',
                    'sec.section_name',
                    'sss.day',
                    'sss.start_time',
                    'sss.end_time',
                    'br.room_name',
                    'c.course_name',
                    'c.course_code',
                    DB::raw('COUNT(st.id) as enrolled_students'),
                    DB::raw('sub.units as faculty_credit')
                )
                ->where('sss.teacher_id', $facultyId)
                ->groupBy(
                    'sub.subject_code',
                    'sub.subject_name',
                    'sss.id',
                    'sec.section_name',
                    'sss.day',
                    'sss.start_time',
                    'sss.end_time',
                    'br.room_name',
                    'c.course_name',
                    'c.course_code',
                    'sub.units'
                )
                ->orderBy('sss.day')
                ->orderBy('sss.start_time')
                ->get();

            $formatted = $schedules->map(function ($s) {
                return [
                    'subject'           => $s->subject_name,
                    'specialization'    => $s->subject_code,
                    'day'               => $s->day,
                    'start_time'          =>  $s->start_time,
                    'end_time'            => $s->end_time,
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

    public function submitGrade(Request $request)
    {
        try {
            $facultyId = Auth::id();

            if (!$facultyId) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Unauthorized.'
                ], 401);
            }

            $validated = $request->validate([
                'student_id'   => 'required|exists:student_subjects,student_id',
                'subject_id'   => 'required|exists:student_subjects,subject_id',
                'final_rating' => 'required|numeric|min:1|max:5',
                'remarks'      => 'nullable|string|max:50'
            ]);

            DB::table('student_subjects')
                ->where('student_id', $validated['student_id'])
                ->where('subject_id', $validated['subject_id'])
                ->update([
                    'final_rating' => $validated['final_rating'],
                    'remarks'      => $validated['remarks'] ?? $this->getCollegeRemarks($validated['final_rating']),
                    'updated_at'   => now()
                ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Grade submitted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to submit grade.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }
}
