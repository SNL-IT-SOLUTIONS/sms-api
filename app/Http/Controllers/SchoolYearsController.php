<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\school_years;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SchoolYearsController extends Controller
{
public function getSchoolYears(Request $request)
{
    try {
        // Paginate school years - only non-archived
        $schoolYears = school_years::where('is_archived', 0)
            ->paginate(5);

        return response()->json([
            'isSuccess' => true,
            'schoolYears' => $schoolYears->items('school_year', 'semester','created_at'),
            'pagination' => [
                'current_page' => $schoolYears->currentPage(),
                'per_page' => $schoolYears->perPage(),
                'total' => $schoolYears->total(),
                'last_page' => $schoolYears->lastPage(),
            ],
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve school years.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function createSchoolYear(Request $request)
    {
        try {
             $user = Auth::user();
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'school_year' => 'required|string|max:255',
                'semester' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Create a new school year
            $schoolYear = school_years::create([
                'school_year' => $request->school_year,
                'semester' => $request->semester,
            ]);

            return response()->json([
                'isSuccess' => true,
                'schoolYear' => $schoolYear,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create school year.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSchoolYear(Request $request, $id)
    {
        try {
            // Validate the request data
             $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'school_year' => 'sometimes|string|max:255',
                'semester' => 'sometimes|string|max:50',
                
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Find the school year by ID
            $schoolYear = school_years::findOrFail($id);

            // Update the school year
            $schoolYear->update($request->only(['school_year', 'semester']));

            return response()->json([
                'isSuccess' => true,
                'schoolYear' => $schoolYear,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update school year.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSchoolYear($id)
    {
        try {
             $user = Auth::user();
            // Find the school year by ID
            $schoolYear = school_years::findOrFail($id);

            // Archive the school year
            $schoolYear->update(['is_archived' => 1]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'School year archived successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive school year.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function restoreSchoolYear($id)
    {
        try {
            // Find the school year by 
             $user = Auth::user();
            $schoolYear = school_years::findOrFail($id);

            // Restore the school year
            $schoolYear->update(['is_archived' => 0]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'School year restored successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to restore school year.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
