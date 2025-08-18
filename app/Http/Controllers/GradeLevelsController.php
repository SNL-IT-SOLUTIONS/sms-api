<?php

namespace App\Http\Controllers;

use App\Models\grade__levels;
use Illuminate\Http\Request;
use App\Models\grade_levels;

class GradeLevelsController extends Controller
{
    public function getgradeLevels()
{
    try {
        $gradeLevels = grade__levels::all();

        return response()->json([
            'isSuccess' => true,
            'gradelevels' => $gradeLevels
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to fetch year levels.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function creategradeLevels(Request $request)
{
    try {
        $validated = $request->validate([
            'grade_level' => 'required|numeric|max:255|unique:grade_levels,grade_level',
            'description' => 'nullable|string|max:255',
        ]);

        $gradeLevel = grade__levels::create($validated);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Year level created successfully.',
            'gradelevel' => $gradeLevel
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to create year level.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updategradeLevels(Request $request, $id)
{
    try {
        $gradeLevel = grade__levels::findOrFail($id);

        $validated = $request->validate([
            'grade_level' => 'required|string|max:255|unique:grade_levels,grade_level,' . $gradeLevel->id,
            'description' => 'nullable|string|max:255',
        ]);

        $gradeLevel->update($validated);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Year level updated successfully.',
            'gradelevel' => $gradeLevel
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to update year level.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function deletegradeLevels($id)
{
    try {
        $gradeLevel = grade__levels::findOrFail($id);
        $gradeLevel->delete();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Year level deleted successfully.'
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to delete year level.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}   

