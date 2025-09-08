<?php

namespace App\Http\Controllers;

use App\Models\student_subjects;
use App\Models\students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use App\Models\subjects;

class SubjectsController extends Controller
{
    public function getSubjects(Request $request)
    {
        try {
            $subjects = subjects::with(['gradeLevel', 'prerequisites'])
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
                    'subject_type' => $subject->subject_type,
                    'prerequisites' => $subject->prerequisites->map(function ($pre) {
                        return [
                            'id' => $pre->id,
                            'subject_code' => $pre->subject_code,
                            'subject_name' => $pre->subject_name,
                        ];
                    }),
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
                'subject_type' => 'required|string',
                'prerequisites' => 'array',   // optional
                'prerequisites.*' => 'exists:subjects,id',
            ]);

            $subject = subjects::create([
                'subject_code' => $request->subject_code,
                'subject_name' => $request->subject_name,
                'units' => $request->units,
                'grade_level_id' => $request->grade_level_id,
                'subject_type' => $request->subject_type,
            ]);

            if ($request->has('prerequisites')) {
                $subject->prerequisites()->sync($request->prerequisites);
            }


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
                'prerequisites' => 'array',
                'prerequisites.*' => 'exists:subjects,id',
            ]);

            $subject = subjects::find($id);

            if (!$subject) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Subject not found.'
                ]);
            }

            $updateData = $request->only([
                'subject_code',
                'subject_name',
                'units',
                'grade_level_id',
                'subject_type',
            ]);

            $subject->update($updateData);

            // Handle prerequisites
            if ($request->has('prerequisites')) {
                $subject->prerequisites()->sync($request->prerequisites);
            }

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


    public function saveGrades(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'final_rating' => 'required', // allow both numeric + string
            ]);

            $studentSubject = student_subjects::findOrFail($id);

            $finalRating = strtoupper($validated['final_rating']); // handle INC / DRP

            $remarks = null;

            if (is_numeric($finalRating)) {
                // Validate range if numeric
                if ($finalRating < 1 || $finalRating > 5) {
                    return response()->json([
                        'isSuccess' => false,
                        'message'   => 'Final rating must be between 1.0 and 5.0.',
                    ], 422);
                }

                $remarks = $finalRating <= 3.0 ? 'PASSED' : 'FAILED';
            } elseif (in_array($finalRating, ['INC', 'DRP'])) {
                // Special cases
                $remarks = $finalRating === 'INC' ? 'INCOMPLETE' : 'DROPPED';
            } else {
                return response()->json([
                    'isSuccess' => false,
                    'message'   => 'Invalid grade. Use numeric (1.0–5.0), INC, or DRP.',
                ], 422);
            }

            // Save
            $studentSubject->update([
                'final_rating' => is_numeric($finalRating) ? round($finalRating, 2) : $finalRating,
                'remarks'      => $remarks,
            ]);

            return response()->json([
                'isSuccess' => true,
                'message'   => 'Grade successfully saved!',
                'data'      => $studentSubject
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => $e->getMessage(),
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
