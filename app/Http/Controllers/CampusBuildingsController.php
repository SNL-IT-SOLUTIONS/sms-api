<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\campus_buildings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class CampusBuildingsController extends Controller
{
public function getBuildingsDropdown()
{
    try {
        // Fetch non-archived buildings with campus name
        $buildings = campus_buildings::with(['campus:id,campus_name'])
            ->where('is_archived', 0)
            ->get()
            ->map(function($building) {
                return [
                    'id' => $building->id,
                    'building_name' => $building->building_name,
                ];
            });

        return response()->json([
            'isSuccess' => true,
            'options' => $buildings
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve buildings.',
            'error' => $e->getMessage(),
        ], 500);
    }
}




public function getBuildings(Request $request)
{
    try {
        $user=auth()->user();

        // Force per page to 5 (or use query param if you want it dynamic)
        $perPage = 5;

        $buildings = campus_buildings::with(['campus:id,campus_name']) // load only id + name from campus
            ->where('is_archived', 0) // only non-archived
            ->paginate($perPage);

        return response()->json([
            'isSuccess' => true,
            'buildings' => $buildings->items(),
            'pagination' => [
                'current_page' => $buildings->currentPage(),
                'per_page' => $buildings->perPage(),
                'total' => $buildings->total(),
                'last_page' => $buildings->lastPage(),
            ],
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve buildings.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

   public function createBuilding(Request $request)
{
    try {
        $validated = $request->validate([
            'building_name' => 'required|string|max:255|unique:campus_buildings,building_name',
            'description' => 'nullable|string|max:500',
            'campus_id' => 'required|exists:school_campus,id',
        ]);

        $building = campus_buildings::create($validated);

        return response()->json([
            'isSuccess' => true,
            'building' => $building,
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
            'message' => 'Failed to create building.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function updateBuilding(Request $request, $id)
{
    try {
        $building = campus_buildings::findOrFail($id);

        $validated = $request->validate([
            'building_name' => 'sometimes|string|max:255|unique:campus_buildings,building_name,' . $id,
            'description' => 'nullable|string|max:500',
            'campus_id' => 'sometimes|exists:school_campus,id',
        ]);

        $building->update($validated);

        return response()->json([
            'isSuccess' => true,
            'building' => $building,
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Building not found.',
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
            'message' => 'Failed to update building.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function deleteBuilding($id)
    {
          try {
        $building = campus_buildings::findOrFail($id);

        // Change status instead of deleting
        $building->is_archived = '1';
        $building->save();

        return response()->json([
            'isSuccess' => true,
            'message' => 'Building archived successfully.',
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Building not found.',
        ], 404);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to archive building.',
            'error' => $e->getMessage(),
        ], 500);
    }
    }
}
