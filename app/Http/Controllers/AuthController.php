<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use App\Models\students;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\admissions;

use App\Models\accounts;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'login'    => 'required|string',
                'password' => 'required|string',
            ]);

            $login    = $request->login;
            $password = $request->password;

            // 🔹 Try logging in as account
            $user = accounts::with('userType')
                ->where('email', $login)
                ->orWhere('username', $login)
                ->first();

            if ($user && Hash::check($password, $user->password)) {
                $token = $user->createToken('auth-token')->plainTextToken;

                $userData = $user->makeHidden(['password', 'created_at', 'updated_at'])->toArray();
                $userData['role_name'] = $user->userType->role_name ?? null;

                Log::info('Logged in as account', ['user_id' => $user->id]);

                return response()->json([
                    'isSuccess' => true,
                    'message'   => 'Logged in successfully as account',
                    'token'     => $token,
                    'user'      => $userData,
                ], 200);
            }

            // 🔹 Try logging in as student
            $student = students::with([
                'course:id,course_name,course_code',
                'section:id,section_name',
                'gradeLevel:id,grade_level,description',
                'academicYear:id,school_year,semester',
                'curriculum:id,curriculum_name',
                'admission:id,first_name,last_name'
            ])
                ->where('student_number', $login) // student login via student_number
                ->first();

            if ($student && Hash::check($password, $student->password ?? '')) {
                // create token for student
                $token = $student->createToken('auth-token')->plainTextToken;

                $studentData = [
                    'id'               => $student->id,
                    'student_number'   => $student->student_number,
                    'full_name'        => $student->admission
                        ? $student->admission->first_name . ' ' . $student->admission->last_name
                        : null, // ✅ added student name
                    'profile_img'      => $student->profile_img,
                    'student_status'   => $student->student_status,
                    'is_active'        => $student->is_active,
                    'enrollment_status' => $student->enrollment_status,
                    'payment_status'   => $student->payment_status,
                    'is_assess'        => $student->is_assess,
                    'is_enrolled'      => $student->is_enrolled,

                    // 👉 Transformed IDs
                    'academic_year'    => $student->academicYear->school_year ?? null,
                    'semester'         => $student->academicYear->semester ?? null, // ✅ optional
                    'grade_desc'       => $student->gradeLevel->description ?? null,
                    'course'           => $student->course->course_name ?? null,
                    'course_code'      => $student->course->course_code ?? null,
                    'section'          => $student->section->section_name ?? null,
                    'curriculum'       => $student->curriculum->curriculum_name ?? null,

                    'user_type'        => $student->user_type,
                ];

                Log::info('Logged in as student', ['student_id' => $student->id]);

                return response()->json([
                    'isSuccess' => true,
                    'message'   => 'Logged in successfully as student',
                    'token'     => $token,
                    'user'      => $studentData,
                ], 200);
            }

            // ❌ If both fail
            Log::warning('Login failed for user', ['login' => $login]);

            return response()->json([
                'isSuccess' => false,
                'message'   => 'Invalid credentials.',
            ], 401);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Validation failed.',
                'errors'    => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Login error', ['error' => $e->getMessage()]);
            return response()->json([
                'isSuccess' => false,
                'message'   => 'Login failed.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }




    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            // Try to find in accounts first
            $user = accounts::where('email', $request->email)->first();
            $table = 'accounts';

            // If not found, try admissions (students)
            if (!$user) {
                $user = admissions::where('email', $request->email)->first();
                $table = 'admissions';
            }

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            // Generate a temporary password
            $tempPassword = Str::random(8);

            // Update password depending on table
            $user->update([
                'password' => Hash::make($tempPassword)
            ]);

            // Send email
            Mail::html("
            <h1>Password Reset</h1>
            <p>Hello {$user->first_name} {$user->last_name},</p>
            <p>Your temporary password is: <strong>{$tempPassword}</strong></p>
            <p>Please log in and change it immediately.</p>
        ", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Temporary Password');
            });

            return response()->json([
                'isSuccess' => true,
                'message' => "A temporary password has been sent to your email ($table).",
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while processing your request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function logout(Request $request)
    {
        try {
            // Revoke the token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Logout failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
