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
        $query = fees::with('schoolYear'); // ✅ eager load school year relation

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active)
                ->where('is_archived', 0);
        }

        $fees = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'isSuccess' => true,
            'fees'      => $fees->map(function ($fee) {
                return [
                    'id'             => $fee->id,
                    'fee_name'       => $fee->fee_name,
                    'description'    => $fee->description,
                    'default_amount' => $fee->default_amount,
                    'is_active'      => $fee->is_active,
                    'created_at'     => $fee->created_at,
                    'updated_at'     => $fee->updated_at,
                    'schoolyear'     => $fee->schoolYear ? $fee->schoolYear->year : null,
                    'semester'       => $fee->schoolYear ? $fee->schoolYear->semester : null,
                ];
            })
        ]);
    }

    public function createFees(Request $request)
    {
        $request->validate([
            'fee_name'       => 'required|string|max:255',
            'description'    => 'nullable|string',
            'school_year_id' => 'required|exists:school_years,id', // tie fee to a school year
            'default_amount' => 'required|numeric|min:0',
        ]);

        $fee = fees::create([
            'fee_name'       => $request->fee_name,
            'description'    => $request->description,
            'school_year_id' => $request->school_year_id,
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
            'fee_name'       => 'sometimes|string|max:255|unique:fees,fee_name,' . $id,
            'description'    => 'nullable|string',
            'school_year_id' => 'sometimes|exists:school_years,id',
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
            'school_year_id',
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
