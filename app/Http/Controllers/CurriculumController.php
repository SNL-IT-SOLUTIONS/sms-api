<?php

namespace App\Http\Controllers;

use App\Models\curriculums;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurriculumController extends Controller
{

    public function getCurriculums(Request $request)
    {
        try {
            $perPage = 5;

            $query = curriculums::with(['subjects', 'course']);

            // 🔍 Search filter (by name or description)
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('curriculum_name', 'LIKE', "%{$search}%")
                        ->orWhere('curriculum_description', 'LIKE', "%{$search}%");
                });
            }

            // 🎓 Filter by course_id
            if ($request->has('course_id') && !empty($request->course_id)) {
                $query->where('course_id', $request->course_id);
            }

            $curriculums = $query->paginate($perPage);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Curriculums retrieved successfully.',
                'curriculums' => $curriculums->items(),
                'pagination' => [
                    'current_page' => $curriculums->currentPage(),
                    'per_page'     => $curriculums->perPage(),
                    'total'        => $curriculums->total(),
                    'last_page'    => $curriculums->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve curriculums.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }


    public function storecurriculum(Request $request)
    {
        $validated = $request->validate([
            'curriculum_name'        => 'required|string|max:255',
            'curriculum_description' => 'nullable|string',
            'course_id'              => 'required|exists:courses,id',
            'school_year_id'         => 'required|exists:school_years,id', // ✅ added
            'subject_ids'            => 'nullable|array',
            'subject_ids.*'          => 'exists:subjects,id',
        ]);

        $curriculum = curriculums::create([
            'curriculum_name'        => $validated['curriculum_name'],
            'curriculum_description' => $validated['curriculum_description'] ?? null,
            'course_id'              => $validated['course_id'],
            'school_year_id'         => $validated['school_year_id'], // ✅ save school year
        ]);

        if (!empty($validated['subject_ids'])) {
            $curriculum->subjects()->attach($validated['subject_ids']);
        }

        return response()->json([
            'isSuccess'  => true,
            'message'    => 'Curriculum created successfully.',
            'curriculum' => $curriculum->load('subjects'),
        ]);
    }

    public function updatecurriculum(Request $request, $id)
    {
        $curriculum = curriculums::findOrFail($id);

        $validated = $request->validate([
            'curriculum_name'        => 'sometimes|required|string|max:255',
            'curriculum_description' => 'nullable|string',
            'course_id'              => 'sometimes|required|exists:courses,id',
            'school_year_id'         => 'sometimes|required|exists:school_years,id', // ✅ added
            'subject_ids'            => 'nullable|array',
            'subject_ids.*'          => 'exists:subjects,id',
        ]);

        // Update only the fields that exist
        $curriculum->update([
            'curriculum_name'        => $validated['curriculum_name'] ?? $curriculum->curriculum_name,
            'curriculum_description' => $validated['curriculum_description'] ?? $curriculum->curriculum_description,
            'course_id'              => $validated['course_id'] ?? $curriculum->course_id,
            'school_year_id'         => $validated['school_year_id'] ?? $curriculum->school_year_id, // ✅ allow updating
        ]);

        // Sync subjects in pivot table if provided
        if (!empty($validated['subject_ids'])) {
            $curriculum->subjects()->sync($validated['subject_ids']);
        }

        return response()->json([
            'isSuccess'  => true,
            'message'    => 'Curriculum updated successfully.',
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
