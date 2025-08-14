<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\courses;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class CoursesController extends Controller
{
    public function addCourse(Request $request)
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
                'course_name' => 'required|string|max:100',
                'course_type' => 'required|string|max:50',
                'course_code' => 'required|string|max:10|unique:courses,course_code',
                'strand' => 'nullable|string|max:50',
                'course_description' => 'nullable|string|max:255',
                'course_units' => 'required|integer|min:3',
            ]);

            // Create a new course
            $course = courses::create([
                'course_name' => $validated['course_name'],
                'course_code' => $validated['course_code'],
                'course_type' => $validated['course_type'],
                'strand' => $validated['strand'] ?? null,
                'course_description' => $validated['course_description'] ?? null,
                'course_units' => $validated['course_units'],
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Course added successfully.',
                'course' => $course,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to add course.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCourses()
    {
        try {
            
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }
             
    
            // Retrieve only non-archived courses
            $courses = courses::where('is_archived', 0)->get();

            return response()->json([
                'isSuccess' => true,
                'courses' => $courses,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve courses.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    

    public function updateCourse(Request $request, $id)
    {
        try {
            $user = Auth::user();
            // Validate the request data
            $validated = $request->validate([
                'course_name' => 'sometimes|required|string|max:100',
                'course_type' => 'sometimes|required|string|max:50',
                'course_code' => 'sometimes|required|string|max:10|unique:courses,course_code,' . $id,
                'strand' => 'sometimes|nullable|string|max:50',
                'course_description' => 'sometimes|nullable|string|max:255',
                'course_units' => 'sometimes|required|integer|min:3',
            ]);
            // Find the course by ID
            $course = courses::findOrFail($id);
            // Update the course details
            $course->update([
                'course_name' => $validated['course_name'] ?? $course->course_name,
                'course_code' => $validated['course_code'] ?? $course->course_code,
                'course_type' => $validated['course_type'] ?? $course->course_type,
                'strand' => $validated['strand'] ?? $course->strand,
                'course_description' => $validated['course_description'] ?? $course->course_description,
                'course_units' => $validated['course_units'] ?? $course->course_units,
            ]);
            return response()->json([
                'isSuccess' => true,
                'message' => 'Course updated successfully.',
                'course' => $course,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Course not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update course.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteCourse($id)
    {
        try {
            // Find the course by ID
             $user = Auth::user();
            $course = courses::findOrFail($id);
            // Archive the course
            $course->update(['is_archived' => 1]);
            return response()->json([
                'isSuccess' => true,
                'message' => 'Course archived successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Course not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive course.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function restoreCourse($id)
    {
        try {
             $user = Auth::user();
            // Find the course by ID
            $course = courses::findOrFail($id);
            // Restore the course
            $course->update(['is_archived' => 0]);
            return response()->json([
                'isSuccess' => true,
                'message' => 'Course restored successfully.',
                'course' => $course,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Course not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to restore course.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCourseSubjects($id)
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Load course with curriculums and their subjects
        $course = courses::with(['curriculums.subjects'])->findOrFail($id);

        $curriculumSubjects = [];

        foreach ($course->curriculums as $curriculum) {
            $curriculumSubjects[] = [
                'curriculum_name' => $curriculum->curriculum_name,
                'curriculum_description' => $curriculum->curriculum_description,
                'subjects' => $curriculum->subjects
            ];
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'Subjects grouped by curriculum retrieved successfully.',
            'course_name' => $course->course_name,
            'curriculums' => $curriculumSubjects
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve subjects.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getCourseCurriculums($id)
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $course = courses::with('curriculums')->findOrFail($id);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Curriculums retrieved successfully.',
            'course_name' => $course->course_name,
            'curriculums' => $course->curriculums
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve curriculums.',
            'error' => $e->getMessage()
        ], 500);
    }
}




}
