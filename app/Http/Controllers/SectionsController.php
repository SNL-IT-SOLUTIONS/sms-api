<?php

namespace App\Http\Controllers;

use App\Models\courses;
use App\Models\school_campus;
use Illuminate\Http\Request;
use App\Models\sections;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class SectionsController extends Controller
{
    public function getSections(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            // Paginate sections - only non-archived
            $sections = sections::with(['course', 'campus'])
                ->where('is_archived', 0)
                ->paginate(5);

            // Map after pagination
            $formattedSections = $sections->getCollection()->map(function ($section) {
                return [
                    'id' => $section->id,
                    'section_name' => $section->section_name,
                    'students_size' => $section->students_size,
                    'course' => $section->course ? [
                        'id' => $section->course->id,
                        'name' => $section->course->course_name,
                    ] : null,
                    'campus' => $section->campus ? [
                        'id' => $section->campus->id,
                        'campus_name' => $section->campus->campus_name,
                    ] : null,
                    'created_at' => $section->created_at,
                    'updated_at' => $section->updated_at,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'sections' => $formattedSections,
                'pagination' => [
                    'current_page' => $sections->currentPage(),
                    'per_page' => $sections->perPage(),
                    'total' => $sections->total(),
                    'last_page' => $sections->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve sections.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function addSection(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }
            // Validate the request data
            $validated = $request->validate([
                'section_name' => 'required|string|max:100',
                'students_size' => 'required|integer|min:1|max:100',
                'course_id' => 'required|exists:courses,id',
                'campus_id' => 'required|exists:school_campus,id',
            ]);

            // Check for duplicate section under same course
            $duplicate = sections::where('section_name', $validated['section_name'])
                ->where('course_id', $validated['course_id'])
                ->where('campus_id', $validated['campus_id'])
                ->first();

            if ($duplicate) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Section already exists under this course.',
                ], 409); //
            }

            $section = sections::create([
                'section_name' => $validated['section_name'],
                'students_size' => $validated['students_size'],
                'course_id' => $validated['course_id'],
                'campus_id' => $validated['campus_id'],
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Section added successfully.',
                'section' => $section,
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
                'message' => 'Failed to add section.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSection(Request $request, $id)
    {
        try {
            $section = sections::findOrFail($id);
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $validated = $request->validate([
                'section_name' => 'sometimes|string|max:100',
                'course_id' => 'sometimes|exists:courses,id',
                'campus_id' => 'sometimes|exists:school_campus,id',
                'students_size' => 'sometimes|integer|min:1|max:100',
            ]);

            // Check for duplicates excluding current section
            $duplicate = sections::where('section_name', $validated['section_name'] ?? $section->section_name)
                ->where('course_id', $validated['course_id'] ?? $section->course_id)
                ->where('campus_id', $validated['campus_id'] ?? $section->campus_id)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'A section with the same name already exists under this course and campus.',
                ], 409);
            }

            $currentEnrolled = $section->students()->count();

            if (isset($validated['students_size']) && $validated['students_size'] < $currentEnrolled) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Cannot set section size to {$validated['students_size']}, because there are already {$currentEnrolled} students enrolled.",
                ], 422);
            }

            $section->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Section updated successfully.',
                'section' => $section,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Section not found.',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update section.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function deleteSection($id)
    {
        try {
            $user = Auth::user();
            // Find the section by ID
            $section = sections::findOrFail($id);

            // Archive the section instead of deleting it
            $section->is_archived = 1;
            $section->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Section archived successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Section not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive section.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function restoreSection($id)
    {
        try {
            $user = Auth::user();
            // Find the section by ID
            $section = sections::findOrFail($id);

            // Restore the section
            $section->is_archived = 0;
            $section->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Section restored successfully.',
                'section' => $section,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Section not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to restore section.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //Dropdown for sections
    public function getSectionsDropdown()
    {
        try {

            $sections = sections::where('is_archived', 0)
                ->with(['course', 'schoolYear'])
                ->get()
                ->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_name' => $section->section_name,

                    ];
                });

            return response()->json([
                'isSuccess' => true,
                'sections' => $sections,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve sections dropdown.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCoursesDropdown()
    {
        try {
            $courses = courses::where('is_archived', 0)
                ->select('id', 'course_name')
                ->get();

            return response()->json([
                'isSuccess' => true,
                'courses' => $courses,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve courses dropdown.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
