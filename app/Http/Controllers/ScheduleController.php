<?php

namespace App\Http\Controllers;

use App\Models\SectionSubjectSchedule;
use App\Models\Account;
use App\Models\accounts;
use App\Models\building_rooms;
use App\Models\campus_buildings;
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
                'day'        => 'required|string|max:20',
                'time'       => 'required|string|max:50',
                'building_id' => 'required|exists:campus_buildings,id',
                'room_id' => [
                    'required',
                    'exists:building_rooms,id',
                    function ($attribute, $value, $fail) use ($request) {
                        $room = building_rooms::find($value);
                        if (!$room || $room->building_id != $request->building_id) {
                            $fail('The selected room does not belong to the selected building.');
                        }
                    }
                ],
                'campus_id' => [
                    'nullable',
                    'exists:school_campus,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value && $request->building_id) {
                            $building = campus_buildings::find($request->building_id);
                            if ($building && $building->campus_id != $value) {
                                $fail('The selected building does not belong to the selected campus.');
                            }
                        }
                    }
                ],
                'teacher_id' => 'nullable|exists:accounts,id',
            ]);

            $schedule = SectionSubjectSchedule::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Schedule successfully assigned.',
                'schedules' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to assign schedule.',
                'error'     => $e->getMessage()
            ]);
        }
    }

    // ✅ Update schedule
    // ✅ Update schedule
    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = SectionSubjectSchedule::findOrFail($id);

            $validated = $request->validate([
                'section_id' => 'sometimes|exists:sections,id',
                'subject_id' => 'sometimes|exists:subjects,id',
                'day'        => 'sometimes|string|max:20',
                'time'       => 'sometimes|string|max:50',
                'building_id' => 'sometimes|exists:campus_buildings,id',
                'room_id' => [
                    'sometimes',
                    'exists:building_rooms,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value && $request->building_id) {
                            $room = \App\Models\BuildingRoom::find($value);
                            if (!$room || $room->building_id != $request->building_id) {
                                $fail('The selected room does not belong to the selected building.');
                            }
                        }
                    }
                ],
                'campus_id' => [
                    'sometimes',
                    'exists:school_campus,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value && $request->building_id) {
                            $building = \App\Models\CampusBuilding::find($request->building_id);
                            if ($building && $building->campus_id != $value) {
                                $fail('The selected building does not belong to the selected campus.');
                            }
                        }
                    }
                ],
                'teacher_id' => 'sometimes|exists:accounts,id',
            ]);

            $schedule->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Schedule successfully updated.',
                'schedules' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to update schedule.',
                'error'     => $e->getMessage()
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
