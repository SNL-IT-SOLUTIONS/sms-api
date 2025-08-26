<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use App\Models\subjects;

class SubjectsController extends Controller
{
    public function getSubjects(Request $request)
    {
        try {
            $subjects = subjects::with('gradeLevel')
                ->where('is_archived', 0)
                ->paginate(5);

            $formattedSubjects = $subjects->getCollection()->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'subject_code' => $subject->subject_code,
                    'subject_name' => $subject->subject_name,
                    'units' => $subject->units,
                    'grade_level_id' => $subject->grade_level_id,
                    'grade_level_name' => $subject->gradeLevel ? $subject->gradeLevel->grade_level : null,
                    'subject_type' => $subject->subject_type, // <-- added
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'subjects' => $formattedSubjects,
                'pagination' => [
                    'current_page' => $subjects->currentPage(),
                    'per_page' => $subjects->perPage(),
                    'total' => $subjects->total(),
                    'last_page' => $subjects->lastPage(),
                ],
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve subjects.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function addSubject(Request $request)
    {
        try {
            $request->validate([
                'subject_code' => 'required|string|max:20|unique:subjects,subject_code',
                'subject_name' => 'required|string|max:255',
                'units' => 'required|numeric|min:0',
                'grade_level_id' => 'required|exists:grade_levels,id',
                'subject_type' => 'required|string', // <-- added validation
            ]);

            $subject = subjects::create([
                'subject_code' => $request->subject_code,
                'subject_name' => $request->subject_name,
                'units' => $request->units,
                'grade_level_id' => $request->grade_level_id,
                'subject_type' => $request->subject_type, // <-- added
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Subject added successfully.',
                'subject' => $subject
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to add subject.',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateSubject(Request $request, $id)
{
    try {
        $request->validate([
            'subject_code' => 'sometimes|string|max:20|unique:subjects,subject_code,' . $id,
            'subject_name' => 'sometimes|string|max:255',
            'units' => 'sometimes|numeric|min:0',
            'grade_level_id' => 'sometimes|exists:grade_levels,id',
            'subject_type' => 'sometimes',
        ]);

        $subject = subjects::find($id);

        if (!$subject) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Subject not found.'
            ]);
        }

        // Only update the fields provided in the request
        $updateData = $request->only([
            'subject_code',
            'subject_name',
            'units',
            'grade_level_id',
            'subject_type',
        ]);

        $subject->update($updateData);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Subject updated successfully.',
            'subject' => $subject
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to update subject.',
            'error' => $e->getMessage()
        ]);
    }
}


    public function deleteSubject($id)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role_name !== 'admin') {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $subject = subjects::findOrFail($id);
            $subject->delete();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Subject deleted successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to delete subject.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //Dropdown
    public function getSubjectsDropdown()
    {
        try {
            $subjects = subjects::where('is_archived', 0)
                ->get(['id', 'subject_name', 'subject_type'])
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'subject_name' => $subject->subject_name,
                        'subject_type' => $subject->subject_type, // <-- added
                    ];
                });

            return response()->json([
                'isSuccess' => true,
                'subjects' => $subjects,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve subjects.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
