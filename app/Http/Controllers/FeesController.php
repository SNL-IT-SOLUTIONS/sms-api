<?php

namespace App\Http\Controllers;

use App\Models\fees;
use Illuminate\Http\Request;

class FeesController extends Controller
{


    public function __construct()
    {
        // ✅ Enforce Sanctum authentication on every method in this controller
        $this->middleware('auth:sanctum');
    }

    public function getFees(Request $request)
    {
        $query = fees::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
            $query->where('is_archived', 0);
        }

        $fees = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'isSuccess' => true,
            'fees'      => $fees
        ]);
    }

    public function createFees(Request $request)
    {
        $request->validate([
            'fee_name'       => 'required|string|max:255',
            'description'    => 'nullable|string',
            'default_amount' => 'required|numeric|min:0',
        ]);

        $fee = fees::create([
            'fee_name'       => $request->fee_name,
            'description'    => $request->description,
            'default_amount' => $request->default_amount,
            'is_active'      => 1,
        ]);

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Fee created successfully',
            'fees'      => $fee
        ], 201);
    }

    public function updateFees(Request $request, $id)
    {
        $request->validate([
            'fee_name'       => 'sometimes|string|max:255|uneque:fees,fee_name,' . $id,
            'description'    => 'nullable|string',
            'default_amount' => 'sometimes|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);

        $fee = fees::find($id);

        if (!$fee) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Fee not found'
            ], 404);
        }

        $fee->update($request->only([
            'fee_name',
            'description',
            'default_amount',
            'is_active'
        ]));


        return response()->json([
            'isSuccess' => true,
            'message'   => 'Fee updated successfully',
            'fees'      => $fee
        ]);
    }

    public function deleteFees($id)
    {
        $fee = fees::find($id);

        if (!$fee) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Fee not found'
            ], 404);
        }

        // ✅ Instead of deleting, mark as archived
        $fee->is_archived = 1;
        $fee->save();

        return response()->json([
            'isSuccess' => true,
            'message'   => 'Fee archived successfully'
        ]);
    }
}
