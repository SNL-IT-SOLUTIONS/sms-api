<?php

namespace App\Http\Controllers;
use App\Models\students;
use Illuminate\Support\Facades\Auth;
use App\Models\admissions;
use App\Models\exam_schedules;
use App\Models\courses;


use Illuminate\Http\Request;

class StudentsController extends Controller
{
   public function getStudentsProfile()
{
    try {
        $student = Auth::user(); // gets the currently logged in user

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }

        // If you want to eager load relations (like course, section, etc.)
        $profile = Students::with(['course', 'section']) // add your relationships here
            ->where('id', $student->id)
            ->first();

        return response()->json([
            'isSuccess' => true,
            'data' => $profile,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}




public function updateStudentsProfile(Request $request)
{
    try {
        $student = Auth::user(); // logged in student

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }

        // get related admission
        $examSchedule = $student->examSchedule; // assuming relationship is set up
        if (!$examSchedule || !$examSchedule->admission) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Admission details not found.',
            ], 404);
        }

        $admission = $examSchedule->admission;

        // validate admission fields
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|max:255|unique:admissions,email,' . $admission->id,
            // add more fields you need
        ]);

        // update admission data instead of students
        $admission->update($request->only([
            'first_name',
            'last_name',
            'email',
            // add other fields
        ]));

        return response()->json([
            'isSuccess' => true,
            'message' => 'Profile updated successfully.',
            'data' => $admission,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}

}
    

