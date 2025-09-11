<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\colleges;
use Illuminate\Validation\ValidationException;
use Throwable;

class CollegesController extends Controller
{
    /**
     * Get all colleges (excluding archived by default)
     */
    public function getColleges(Request $request)
    {
        try {
            $includeArchived = $request->query('include_archived', false);
            $perPage = $request->query('per_page', 10); // default 10 per page

            $query = colleges::with('courses'); // eager load courses

            if (!$includeArchived) {
                $query->where('is_archive', 0);
            }

            $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Colleges list ordered by creation date.',
                'data'      => $paginated->items(),
                'meta'      => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to fetch colleges.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Create a new college with courses
     */
    public function createCollege(Request $request)
    {
        try {
            $validated = $request->validate([
                'college_name' => 'required|string|max:255|unique:colleges,college_name',
                'abbreviation' => 'required|string|max:50|unique:colleges,abbreviation',
                'description'  => 'nullable|string|max:500',
                'course_ids'   => 'nullable|array',
                'course_ids.*' => 'exists:courses,id',
            ]);

            $validated['is_archive'] = 0;

            $college = colleges::create($validated);

            // Attach courses if provided
            if (!empty($validated['course_ids'])) {
                $college->courses()->attach($validated['course_ids']);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'College created successfully.',
                'college' => $college->load('courses'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create college.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing college and sync courses
     */
    public function updateCollege(Request $request, $id)
    {
        try {
            $college = colleges::findOrFail($id);

            $validated = $request->validate([
                'college_name' => 'required|string|max:255|unique:colleges,college_name,' . $college->id,
                'abbreviation' => 'required|string|max:50|unique:colleges,abbreviation,' . $college->id,
                'description'  => 'nullable|string|max:500',
                'course_ids'   => 'nullable|array',
                'course_ids.*' => 'exists:courses,id',
            ]);

            $college->update($validated);

            // Sync courses (replace existing relations)
            if (isset($validated['course_ids'])) {
                $college->courses()->sync($validated['course_ids']);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'College updated successfully.',
                'college' => $college->load('courses'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update college.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Soft delete a colleges
     */
    public function deleteCollege($id)
    {
        try {
            $college = colleges::findOrFail($id);

            $college->is_archive = 1;
            $college->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'College archived successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive college.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted college
     */
    public function restoreCollege($id)
    {
        try {
            $college = colleges::findOrFail($id);

            $college->is_archive = 0;
            $college->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'College restored successfully.',
                'college' => $college,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to restore college.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
