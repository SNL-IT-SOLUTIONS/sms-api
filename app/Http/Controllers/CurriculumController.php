<?php

namespace App\Http\Controllers;

use App\Models\curriculums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CurriculumController extends Controller
{

public function getCurriculums(Request $request)
{
    try {
        // Always return 5 per page
        $curriculums = curriculums::with(['subjects', 'course'])
            ->paginate(5);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Curriculums retrieved successfully.',
            'curriculums' => $curriculums->items(),
            'pagination' => [
                'current_page' => $curriculums->currentPage(),
                'per_page' => $curriculums->perPage(),
                'total' => $curriculums->total(),
                'last_page' => $curriculums->lastPage(),
            ],
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve curriculums.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    // Store a new curriculum
   public function storecurriculum(Request $request)
{
    $validated = $request->validate([
        'curriculum_name' => 'required|string|max:255',
        'curriculum_description' => 'nullable|string',
        'course_id' => 'required|exists:courses,id',
        'subject_ids' => 'nullable|array',
        'subject_ids.*' => 'exists:subjects,id',
    ]);

    $curriculum = curriculums::create($validated);

    if (!empty($validated['subject_ids'])) {
        $curriculum->subjects()->attach($validated['subject_ids']);
    }

    return response()->json([
        'isSuccess' => true,
        'message' => 'Curriculum created successfully.',
        'curriculum' => $curriculum->load('subjects'),
    ]);
}




    // Show a specific curriculum with subjects
    public function showcurriculum($id)
    {
        $curriculum = curriculums::with(['course', 'subjects'])->findOrFail($id);

        return response()->json([
            'isSuccess' => true,
            'curriculum' => $curriculum,
        ]);
    }

    // Update a curriculum
    public function updatecurriculum(Request $request, $id)
{
    $curriculum = curriculums::findOrFail($id);

    // Validate inputs
    $validated = $request->validate([
        'curriculum_name' => 'sometimes|required|string|max:255',
        'curriculum_description' => 'nullable|string',
        'course_id' => 'sometimes|required|exists:courses,id',
        'subject_ids' => 'nullable|array',
        'subject_ids.*' => 'exists:subjects,id',
    ]);

    // Only update the actual fields of the curriculum table

    $curriculum->update([
        'curriculum_name' => $validated['curriculum_name'] ?? $curriculum->curriculum_name,
        'curriculum_description' => $validated['curriculum_description'] ?? $curriculum->curriculum_description,
        'course_id' => $validated['course_id'] ?? $curriculum->course_id,
    ]);
   

    // Sync subjects in pivot table if provided
    if (!empty($validated['subject_ids'])) {
        $curriculum->subjects()->sync($validated['subject_ids']);
    }

    return response()->json([
        'isSuccess' => true,
        'message' => 'Curriculum updated successfully.',
        'curriculum' => $curriculum->load('subjects'),
    ]);
}

    // Delete (archive) a curriculum
    public function destroycurriculum($id)
    {
        $curriculum = curriculums::findOrFail($id);
        $curriculum->delete();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Curriculum deleted successfully.',
        ]);
    }
}

