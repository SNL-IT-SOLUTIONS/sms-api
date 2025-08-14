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

use App\Models\accounts;

class AuthController extends Controller
{
   public function login(Request $request)
{
    try {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;
        $password = $request->password;

        // Try logging in as an account
        $user = accounts::with('userType')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            $token = $user->createToken('auth-token')->plainTextToken;

            $userData = $user->makeHidden(['password', 'created_at', 'updated_at']);
            $userData['role_name'] = $user->userType->role_name ?? null;

            Log::info('Logged in as account', ['user_id' => $user->id]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Logged in successfully as account',
                'token' => $token,
                'user' => $userData,
            ], 200);
        }

        // Try logging in as a student
        $student = students::where('student_number', $login)->first();

        if ($student && Hash::check($password, $student->password)) {
            $token = $student->createToken('auth-token')->plainTextToken;

            $studentData = $student->makeHidden(['password', 'created_at', 'updated_at']);

            Log::info('Logged in as student', ['student_id' => $student->id]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Logged in successfully as student',
                'token' => $token,
                'user' => $studentData,
            ], 200);
        }

        // If neither account nor student matched
        Log::warning('Login failed for user', ['login' => $login]);

        return response()->json([
            'isSuccess' => false,
            'message' => 'Invalid credentials.',
        ], 401);

    } catch (ValidationException $e) {
        return response()->json([
            'isSuccess' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('Login error', ['error' => $e->getMessage()]);
        return response()->json([
            'isSuccess' => false,
            'message' => 'Login failed.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


  public function forgotPassword(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = accounts::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Email not found.',
            ], 404);
        }

        // Generate a temporary password
        $tempPassword = Str::random(8);

        // Update the user's password with the hashed temporary password
        $user->update([
            'password' => Hash::make($tempPassword)
        ]);

        // Send email directly without Mailable class
        Mail::html("
            <h1>Password Reset</h1>
            <p>Hello {$user->name},</p>
            <p>Your temporary password is: <strong>{$tempPassword}</strong></p>
            <p>Please log in and change it immediately.</p>
        ", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Your Temporary Password');
        });

        return response()->json([
            'isSuccess' => true,
            'message' => 'A temporary password has been sent to your email.',
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
