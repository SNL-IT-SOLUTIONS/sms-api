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
            'school_logo'       => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
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

        // Get existing or prepare new record
        $schoolInfo = SchoolInfo::first();

        // Handle uploaded school logo
        $logoPath = $this->saveFileToPublic($request, 'school_logo', 'school_logo');

        if ($logoPath) {
            $validated['school_logo'] = $logoPath;
        }

        if (!$schoolInfo) {
            // Create only once
            $schoolInfo = SchoolInfo::create($validated);
        } else {
            // Update existing
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

        if ($schoolInfo && $schoolInfo->school_logo) {
            $schoolInfo->school_logo = asset($schoolInfo->school_logo);
        }

        return response()->json([
            'isSuccess' => true,
            'message' => 'School information retrieved.',
            'data' => $schoolInfo
        ]);
    }

    private function saveFileToPublic(Request $request, $field, $prefix)
    {
        if ($request->hasFile($field)) {
            $file = $request->file($field);
            $directory = public_path('admission_files');

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = $prefix . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);

            return 'admission_files/' . $filename;
        }

        return null;
    }
}
