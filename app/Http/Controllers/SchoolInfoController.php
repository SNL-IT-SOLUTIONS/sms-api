<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolInfo;

class SchoolInfoController extends Controller
{
    public function saveSchoolInfo(Request $request)
    {
        // Validate incoming fields
        $validated = $request->validate([
            'school_name'       => 'required|string|max:255',
            'school_logo'       => 'nullable|string',
            'slogan'            => 'nullable|string|max:255',
            'address'           => 'required|string|max:255',
            'city'              => 'nullable|string|max:100',
            'province'          => 'nullable|string|max:100',
            'postal_code'       => 'nullable|string|max:10',
            'contact_number'    => 'nullable|string|max:50',
            'telephone_number'  => 'nullable|string|max:50',
            'email'             => 'nullable|email|max:150',
            'website'           => 'nullable|string|max:255',
        ]);

        // Fetch existing row OR create a new blank one
        $schoolInfo = SchoolInfo::first();

        if (!$schoolInfo) {
            // Create only once — after that, always update
            $schoolInfo = SchoolInfo::create($validated);
        } else {
            // Update existing record
            $schoolInfo->update($validated);
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'School information saved successfully.',
            'data' => $schoolInfo
        ]);
    }

    public function getSchoolInfo()
    {
        $schoolInfo = SchoolInfo::first();

        return response()->json([
            'isSuccess' => true,
            'message' => 'School information retrieved.',
            'data' => $schoolInfo
        ]);
    }
}
