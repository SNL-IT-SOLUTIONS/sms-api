<?php

namespace App\Http\Controllers;
use App\Models\SectionSubjectSchedule;

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

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No schedules found.'
            ]);
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Schedules retrieved successfully.',
            'data' => $schedules
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
            'room' => 'nullable|string|max:100',
            'teacher_id' => 'nullable|exists:accounts,id',
        ]);

        $schedule = SectionSubjectSchedule::create($validated);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Schedule successfully assigned.',
            'data' => $schedule
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to assign schedule.',
            'error' => $e->getMessage()
        ]);
    }
}

}
