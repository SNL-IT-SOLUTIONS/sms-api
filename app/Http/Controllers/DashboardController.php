<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\school_years as AcademicYear;
use App\Models\students as Student;
use App\Models\accounts as Account;
use App\Models\courses as Course;

class DashboardController extends Controller
{
    public function dashboardOverview()
    {
        $currentAY = AcademicYear::orderBy('id', 'desc')->first();

        return response()->json([
            "school_year" => $currentAY->school_year ?? 'N/A',
            "semester"     => $currentAY->semester ?? 'N/A',

            // -----------------------------
            // STUDENTS
            // -----------------------------
            "students" => [
                "total" => Student::count(),

                // Using grade_level_id instead of year_level
                "grade_levels" => [
                    "year_1" => Student::where('grade_level_id', 9)->count(),
                    "year_2" => Student::where('grade_level_id', 10)->count(),
                    "year_3" => Student::where('grade_level_id', 11)->count(),
                    "year_4" => Student::where('grade_level_id', 12)->count(),
                ]
            ],

            // -----------------------------
            // EMPLOYEES  (USING ACCOUNTS TABLE)
            // -----------------------------
            "employees" => [
                "total"         => Account::count(),
                "faculty"       => Account::where('user_type_id', 10)->count(),
                "administrator" => Account::where('user_type_id', 3)->count(),
                "dean"          => Account::where('user_type_id', 11)->count(),
                "others"        => Account::whereNotIn('user_type_id', [3, 10, 11])->count(),
            ],

            // -----------------------------
            // PROGRAMS  (COURSES)
            // -----------------------------
            "programs" => [
                "total"     => Course::count(),
                "active"    => Course::where('is_archived', 0)->count(),
                "archived"  => Course::where('is_archived', 1)->count(),
            ],

            // -----------------------------
            // INSTITUTION OVERVIEW
            // -----------------------------
            "institution_overview" => $this->getInstitutionOverview(7)
        ]);
    }

    //HELPERS
    private function getInstitutionOverview($days = 7)
    {
        $overview = [];

        for ($i = $days - 1; $i >= 0; $i--) {

            $date = now()->subDays($i)->toDateString();

            $overview[] = [
                "date" => $date,
                "students" => Student::whereDate('created_at', '<=', $date)->count(),
                "employees" => Account::whereDate('created_at', '<=', $date)->count(),
                "programs" => Course::whereDate('created_at', '<=', $date)->count(),
            ];
        }

        return $overview;
    }
}
