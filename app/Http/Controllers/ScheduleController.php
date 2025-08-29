<?php

namespace App\Http\Controllers;

use App\Models\SectionSubjectSchedule;
use App\Models\Account;
use App\Models\accounts;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function getSchedule(Request $request)
    {
        try {
            $query = SectionSubjectSchedule::with(['section', 'subject', 'teacher']);

            if ($request->has('section_id')) {
                $query->where('section_id', $request->section_id);
            }

            if ($request->has('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            $schedules = $query->where('is_archived', 0)->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No schedules found.'
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Schedules retrieved successfully.',
                'schedules' => $schedules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve schedules.',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function assignSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'section_id' => 'required|exists:sections,id',
                'subject_id' => 'required|exists:subjects,id',
                'day' => 'required|string|max:20',
                'time' => 'required|string|max:50',
                'room_id' => 'nullable|exists:building_rooms,id',
                'teacher_id' => 'nullable|exists:accounts,id',
            ]);

            $schedule = SectionSubjectSchedule::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Schedule successfully assigned.',
                'schedules' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to assign schedule.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ✅ Update schedule
    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = SectionSubjectSchedule::findOrFail($id);

            $validated = $request->validate([
                'section_id' => 'sometimes|exists:sections,id',
                'subject_id' => 'sometimes|exists:subjects,id',
                'day' => 'sometimes|string|max:20',
                'time' => 'sometimes|string|max:50',
                'room_id' => 'n ullable|exists:building_rooms,id',
                'teacher_id' => 'nullable|exists:accounts,id',
            ]);

            $schedule->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Schedule successfully updated.',
                'schedules' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update schedule.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ✅ Soft delete (archive)
    public function deleteSchedule($id)
    {
        try {
            $schedule = SectionSubjectSchedule::findOrFail($id);
            $schedule->update(['is_archived' => 1]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Schedule archived successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive schedule.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // ✅ Dropdown for Faculty only (user_type_id = 10)
    public function getFacultyDropdown()
    {
        try {
            $faculty = accounts::where('user_type_id', 10)
                ->where('is_archived', 0)
                ->select('id', 'given_name', 'surname')
                ->get()
                ->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'name' => trim($f->given_name . ' ' . $f->surname)
                    ];
                });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Faculty list retrieved successfully.',
                'faculty' => $faculty
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve faculty list.',
                'error' => $e->getMessage()
            ]);
        }
    }
}
