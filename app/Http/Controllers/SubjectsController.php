<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Models\subjects;

class SubjectsController extends Controller
{
    public function getSubjects(Request $request)
    {
        try {
            // Paginate subjects - only non-archived, 5 per page
            $subjects = subjects::with('gradeLevel') // <-- assumes you have gradeLevel relation
                ->where('is_archived', 0)
                ->paginate(5);

            // Map the paginated data
            $formattedSubjects = $subjects->getCollection()->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'subject_code' => $subject->subject_code,
                    'subject_name' => $subject->subject_name,
                    'units' => $subject->units,
                    'grade_level_id' => $subject->grade_level_id,
                    'grade_level_name' => $subject->gradeLevel ? $subject->gradeLevel->grade_level : null,
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
                'grade_level_id' => 'required|exists:grade_levels,id', // <-- changed
            ]);

            $subject = subjects::create([
                'subject_code' => $request->subject_code,
                'subject_name' => $request->subject_name,
                'units' => $request->units,
                'grade_level_id' => $request->grade_level_id, // <-- changed
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
                'units' => 'required|numeric|min:0',
                'grade_level_id' => 'required|exists:grade_levels,id', // <-- changed
            ]);

            $subject = subjects::find($id);

            if (!$subject) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Subject not found.'
                ]);
            }

            $subject->update([
                'subject_code' => $request->subject_code,
                'subject_name' => $request->subject_name,
                'units' => $request->units,
                'grade_level_id' => $request->grade_level_id, // <-- changed
            ]);

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
            if (!$user || $user->role_name !== 'admin') { // <-- simplified, since you said you don’t have user_types
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
                ->pluck('subject_name', 'id')
                ->map(function ($subject_name, $id) {
                    return [
                        'id' => $id,
                        'subject_name' => $subject_name,
                    ];
                })
                ->values(); // Reset array keys for clean indexing

            return response()->json([
                'isSuccess' => true,
                'subject' => $subjects,
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
