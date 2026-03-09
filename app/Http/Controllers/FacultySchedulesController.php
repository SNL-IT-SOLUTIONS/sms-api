<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\accounts;
use App\Models\building_rooms;
use App\Models\SectionSubjectSchedule;
use Illuminate\Support\Facades\DB;


class FacultySchedulesController extends Controller
{
    public function getTeachers(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search  = $request->input('search');

            $teachers = accounts::where('user_type_id', 10)
                ->where('is_archived', 0);

            if ($search) {
                $teachers->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
                });
            }

            $teachers = $teachers->paginate($perPage);

            $formattedTeachers = $teachers->getCollection()->map(function ($teacher) {
                return [
                    'teacher_id'   => $teacher->id,
                    'given_name'   => $teacher->given_name,
                    'surname'    => $teacher->surname,
                    'full_name'    => $teacher->given_name . ' ' . $teacher->surname,
                    'email'        => $teacher->email ?? null,
                    'is_archived'    => $teacher->is_archived ?? 0,
                ];
            });

            return response()->json([
                'isSuccess'  => true,
                'data'       => $formattedTeachers,
                'pagination' => [
                    'current_page' => $teachers->currentPage(),
                    'per_page'     => $teachers->perPage(),
                    'total'        => $teachers->total(),
                    'last_page'    => $teachers->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve teachers.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    public function getTeacherById($id)
    {
        $teacher = accounts::where('user_type_id', '10')
            ->where('is_archived', 0)
            ->find($id);

        if (!$teacher) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Teacher not found.'
            ], 404);
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Teacher retrieved successfully.',
            'teacher' => $teacher
        ]);
    }

    public function getSchedulesByTeacherId(Request $request, $teacherId)
    {
        $teacher = accounts::where('user_type_id', 10)
            ->where('is_archived', 0)
            ->find($teacherId);

        if (!$teacher) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Teacher not found.'
            ], 404);
        }

        $sectionId = $request->input('section_id');
        $roomId = $request->input('room_id');

        $schedules = $teacher->schedules()
            ->with(['section', 'subject', 'room'])
            ->where('is_archived', 0)

            ->when($sectionId, function ($query) use ($sectionId) {
                $query->where('section_id', $sectionId);
            })

            ->when($roomId, function ($query) use ($roomId) {
                $query->where('room_id', $roomId);
            })

            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->map(function ($sched) {
                return [
                    'id'           => $sched->id,
                    'day'          => $sched->day,
                    'start_time'   => $sched->start_time,
                    'end_time'     => $sched->end_time,

                    'section_id'   => $sched->section_id,
                    'section_name' => $sched->section->section_name ?? null,

                    'subject_id'   => $sched->subject_id,
                    'subject_name' => $sched->subject->subject_name ?? null,

                    'room_id'      => $sched->room_id,
                    'room_name'    => $sched->room->room_name ?? null,
                ];
            });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Schedules retrieved successfully.',
            'teacher' => [
                'id'   => $teacher->id,
                'name' => $teacher->given_name . ' ' . $teacher->surname,
            ],
            'schedules' => $schedules
        ]);
    }

    //DROPDOWN

    public function getRoomDropdown()
    {
        try {

            $rooms = building_rooms::where('is_archived', 0)
                ->select('id', 'room_name')
                ->orderBy('room_name', 'asc')
                ->get();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Rooms retrieved successfully.',
                'data' => $rooms
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve rooms.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
