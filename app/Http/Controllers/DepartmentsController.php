<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\departments;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Throwable;


class DepartmentsController extends Controller
{
     // Get all departments (non-archived)
    public function getDepartments(Request $request)
{
    try {
        // Items per page (default 10 if not provided)
        $perPage = $request->input('per_page', 5);

        // Paginate departments that are not archived
        $departments = departments::where('is_archive', 0)
            ->paginate($perPage);

        return response()->json([
            'isSuccess' => true,
            'departments' => $departments->items(),
            'pagination' => [
                'current_page' => $departments->currentPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
                'last_page' => $departments->lastPage(),
            ],
        ], 200);
    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve departments.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    // Add a new department
    public function addDepartment(Request $request)
{
    $validated = $request->validate([
        'department_name' => 'required|string|max:100',
        'description' => 'nullable|string',
    ]);

    // Automatically generate abbreviation from department name
    $validated['abbreviation'] = strtoupper(
        implode('', array_map(fn($word) => $word[0], explode(' ', $validated['department_name'])))
    );

    $department = departments::create($validated);

    return response()->json([
        'isSuccess' => true,
        'message' => 'Department created successfully',
        'department' => $department
    ]);
}

    // Update a department
    public function updateDepartment(Request $request, $id)
    {
        $department = departments::findOrFail($id);

        $validated = $request->validate([
            'department_name' => 'required|string|max:100',
            'abbreviation' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_archive' => 'boolean',
        ]);

        $department->update($validated);

        return response()->json([
            'isSuccess' => true,
            'message' => 'Department updated successfully',
            'department' => $department
        ]);
    }

    // Delete (soft delete by setting is_archive to true)
    public function deleteDepartment($id)
    {
        $department = departments::findOrFail($id);
        $department->is_archive = true;
        $department->save();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Department archived successfully'
        ]);
    }
}

