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
            $query = SectionSubjectSchedule::with([
                'section',
                'subject',
                'teacher',
                'room.building.campus'
            ]);

            // ✅ Filtering
            if ($request->has('section_id')) {
                $query->where('section_id', $request->section_id);
            }

            if ($request->has('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('building_id')) {
                $query->where('building_id', $request->building_id);
            }

            if ($request->has('room_id')) {
                $query->where('room_id', $request->room_id);
            }

            if ($request->has('campus_id')) {
                $query->where('campus_id', $request->campus_id);
            }

            // ✅ Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('subject', function ($q2) use ($search) {
                        $q2->where('subject_name', 'like', "%$search%");
                    })
                        ->orWhereHas('section', function ($q2) use ($search) {
                            $q2->where('section_name', 'like', "%$search%");
                        })
                        ->orWhereHas('room', function ($q2) use ($search) {
                            $q2->where('room_name', 'like', "%$search%");
                        });
                });
            }

            // ✅ Pagination
            $perPage   = $request->query('per_page', 5);
            $paginated = $query->where('is_archived', 0)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($paginated->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'No schedules found.'
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Schedules list ordered by creation date.',
                'schedules' => $paginated->items(),
                'meta'      => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve schedules.',
                'error'     => $e->getMessage()
            ]);
        }
    }





    public function assignSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'section_id'   => 'required|exists:sections,id',
                'subject_id'   => 'required|exists:subjects,id',
                'day'          => 'required|string|max:20',
                'start_time'   => 'required|date_format:H:i',
                'end_time'     => 'required|date_format:H:i|after:start_time',
                'building_id'  => 'required|exists:campus_buildings,id',
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
                'school_year_id' => 'required|exists:school_years,id',
            ]);

            // ⚡ Extra custom validation rules
            $day = $validated['day'];
            $start = $validated['start_time'];
            $end = $validated['end_time'];

            // 1. Check if room is free
            $roomConflict = SectionSubjectSchedule::where('room_id', $validated['room_id'])
                ->where('day', $day)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_time', [$start, $end])
                        ->orWhereBetween('end_time', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                        });
                })
                ->exists();

            if ($roomConflict) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Room is already booked for this time slot.'
                ], 422);
            }

            // 2. Check if teacher is free
            if (!empty($validated['teacher_id'])) {
                $teacherConflict = SectionSubjectSchedule::where('teacher_id', $validated['teacher_id'])
                    ->where('day', $day)
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('start_time', [$start, $end])
                            ->orWhereBetween('end_time', [$start, $end])
                            ->orWhere(function ($q2) use ($start, $end) {
                                $q2->where('start_time', '<=', $start)
                                    ->where('end_time', '>=', $end);
                            });
                    })
                    ->exists();
            }

            // 3. Check if section is free
            $sectionConflict = SectionSubjectSchedule::where('section_id', $validated['section_id'])
                ->where('day', $day)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_time', [$start, $end])
                        ->orWhereBetween('end_time', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                        });
                })
                ->exists();

            if ($sectionConflict) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'This section already has a schedule during this time.'
                ], 422);
            }

            // 4. Optional: Prevent duplicate section+subject+day
            $duplicate = SectionSubjectSchedule::where('section_id', $validated['section_id'])
                ->where('subject_id', $validated['subject_id'])
                ->where('day', $day)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'This subject is already scheduled for this section on this day.'
                ], 422);
            }

            // 🚀 Save schedule
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


    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = SectionSubjectSchedule::findOrFail($id);

            $validated = $request->validate([
                'section_id'   => 'sometimes|exists:sections,id',
                'subject_id'   => 'sometimes|exists:subjects,id',
                'day'          => 'sometimes|string|max:20',
                'start_time'   => 'sometimes|date_format:H:i',
                'end_time'     => 'sometimes|date_format:H:i|after:start_time',
                'building_id'  => 'sometimes|exists:campus_buildings,id',
                'room_id' => [
                    'sometimes',
                    'exists:building_rooms,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value && $request->building_id) {
                            $room = building_rooms::find($value);
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
                            $building = campus_buildings::find($request->building_id);
                            if ($building && $building->campus_id != $value) {
                                $fail('The selected building does not belong to the selected campus.');
                            }
                        }
                    }
                ],
                'teacher_id' => 'sometimes|exists:accounts,id',
                'school_year_id' => 'sometimes|exists:school_years,id'
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
