<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\accounts;
use Illuminate\Support\Facades\DB;

class GradeListController extends Controller
{
    public function getTeacherStudentsWithGrades(Request $request, $id)
    {
        // 🔹 Teacher info
        $teacher = DB::table('accounts')
            ->where('id', $id)
            ->select('id', 'given_name', 'surname')
            ->first();

        if (!$teacher) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Teacher not found'
            ], 404);
        }

        //  Base query
        $query = DB::table('student_subjects as ss')
            ->join('subjects as sub', 'sub.id', '=', 'ss.subject_id')
            ->join('students as s', 's.id', '=', 'ss.student_id')
            ->join('admissions as ad', 'ad.id', '=', 's.admission_id')
            ->join('sections as sec', 'sec.id', '=', 's.section_id')
            ->join('section_subject_schedule as sss', function ($join) {
                $join->on('sss.subject_id', '=', 'ss.subject_id')
                    ->on('sss.section_id', '=', 's.section_id');
            })
            ->where('sss.teacher_id', $id);

        // Filter by SECTION
        if ($request->filled('section_id')) {
            $query->where('s.section_id', $request->section_id);
        }

        // Filter by ACADEMIC / SCHOOL YEAR (CORRECT SOURCE)
        if ($request->filled('academic_year_id')) {
            $query->where('s.academic_year_id', $request->academic_year_id);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sec.section_name', 'LIKE', "%{$search}%")
                    ->orWhere('ad.last_name', 'LIKE', "%{$search}%")
                    ->orWhere('ad.first_name', 'LIKE', "%{$search}%")
                    ->orWhere('sub.subject_name', 'LIKE', "%{$search}%")
                    ->orWhere('s.student_number', 'LIKE', "%{$search}%");
            });
        }

        $students = $query
            ->select(
                's.student_number',
                DB::raw("
                CONCAT(
                    ad.first_name, ' ',
                    IFNULL(ad.middle_name, ''), ' ',
                    ad.last_name
                ) as student_name
            "),
                'sec.id as section_id',
                'sec.section_name',
                'sub.subject_name',
                'ss.final_rating',
                'ss.remarks'
            )
            ->orderBy('sec.section_name')
            ->orderBy('ad.last_name')
            ->orderBy('ad.first_name')
            ->get();

        return response()->json([
            'isSuccess' => true,
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->given_name . ' ' . $teacher->surname
            ],
            'data' => $students
        ], 200);
    }
}
