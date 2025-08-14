<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\building_rooms;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class BuildingRoomsController extends Controller
{
    
  public function getRooms(Request $request)
{
    try {
        // Paginate with 5 per page
        $rooms = building_rooms::with(['building:id,building_name']) // load only id + name from building
            ->where('is_archived', 0) // only non-archived
            ->paginate(5);

        return response()->json([
            'isSuccess' => true,
            'rooms' => $rooms->items(),
            'pagination' => [
                'current_page' => $rooms->currentPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
                'last_page' => $rooms->lastPage(),
            ],
        ], 200);

    } catch (Throwable $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Failed to retrieve rooms.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

   public function createRoom(Request $request)
{
    try {
        $validated = $request->validate([
            'room_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('building_rooms', 'room_name')
                    ->where('building_id', $request->building_id)
            ],
            'building_id' => 'required|exists:campus_buildings,id',
            'description' => 'nullable|string|max:500',
            'room_size' => 'nullable|numeric|min:0',
        ]);
        
        $room = building_rooms::create($validated); 
        return response()->json([
            'isSuccess' => true,
            'room' => $room,
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
            'message' => 'Failed to create room.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function updateRoom(Request $request, $id)
{
    try {
        $room = building_rooms::findOrFail($id);

        $validated = $request->validate([
            'room_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('building_rooms', 'room_name')
                    ->where('building_id', $request->building_id ?? $room->building_id)
                    ->ignore($id)
            ],
            'building_id' => 'sometimes|required|exists:campus_buildings,id',
            'description' => 'nullable|string|max:500',
            'room_size' => 'nullable|numeric|min:0',
        ]);

        $room->update($validated);

        return response()->json([
            'isSuccess' => true,
            'room' => $room,
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Room not found.',
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
            'message' => 'Failed to update room.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function deleteRoom($id)
    {
        try {
            $room = building_rooms::findOrFail($id);
            $room->is_archived = 1; // mark as archived
            $room->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Room archived successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Room not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive room.',
                'error' => $e->getMessage(),

            ], 500);
        }
    }

    
}
