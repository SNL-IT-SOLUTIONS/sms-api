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
        try {
            $perPage = $request->input('per_page', 10); // ✅ default 10 per page
            $query = fees::with('schoolYear')
                ->where('is_archived', 0);

            // 🎯 Active status filter
            if ($request->has('is_active') && $request->is_active !== null) {
                $query->where('is_active', $request->is_active);
            }

            // 🔍 Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('fee_name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // 📌 Order newest first
            $fees = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // ✅ Format response
            $formattedFees = $fees->getCollection()->map(function ($fee) {
                return [
                    'id'             => $fee->id,
                    'fee_name'       => $fee->fee_name,
                    'description'    => $fee->description,
                    'default_amount' => $fee->default_amount,
                    'is_active'      => $fee->is_active,
                    'created_at'     => $fee->created_at,
                    'updated_at'     => $fee->updated_at,
                    'schoolyear'     => $fee->schoolYear ? $fee->schoolYear->school_year : null,
                    'semester'       => $fee->schoolYear ? $fee->schoolYear->semester : null,
                ];
            });

            return response()->json([
                'isSuccess'  => true,
                'fees'       => $formattedFees,
                'pagination' => [
                    'current_page' => $fees->currentPage(),
                    'per_page'     => $fees->perPage(),
                    'total'        => $fees->total(),
                    'last_page'    => $fees->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Failed to retrieve fees.',
                'error'     => $e->getMessage()
            ], 500);
        }
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
