<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\accounts;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AccountsController extends Controller
{

    // Method to register a new account
    public function getusers(Request $request)
    {
        try {
            $query = accounts::with('userType'); // Eager load userType

            // Search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('given_name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Filter by user_type
            if ($request->filled('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            // Apply pagination and hide field
            $users = $query->paginate(5)->through(function ($user) {
                return $user->makeHidden(['is_verified']);
            });

            return response()->json([
                'isSuccess' => true,
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve users.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function createUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                // Personal Information
                'username' => 'unique:accounts,username',
                'surname' => 'nullable|string|max:50',
                'given_name' => 'nullable|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'user_type_id' => 'nullable|exists:user_types,id',
                'suffix' => 'nullable|string|max:10',
                'date_of_birth' => 'nullable|date',
                'place_of_birth' => 'nullable|string|max:100',
                'gender' => 'nullable|string|max:10',
                'civil_status' => 'nullable|string|max:20',
                'department_id' => 'nullable|exists:departments,id',

                // Address & Contact Info
                'street_address' => 'nullable|string|max:255',
                'province' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'barangay' => 'nullable|string|max:100',
                'nationality' => 'nullable|string|max:50',
                'religion' => 'nullable|string|max:50',
                'ethnic_affiliation' => 'nullable|string|max:50',
                'telephone_number' => 'nullable|string|max:15',
                'mobile_number' => 'nullable|string|max:15',

                // Account Info
                'email' => [
                    'nullable',
                    'email',
                    'max:100',
                    function ($attribute, $value, $fail) {
                        if (
                            DB::table('accounts')->where('email', $value)->exists() ||
                            DB::table('admissions')->where('email', $value)->exists()
                        ) {
                            $fail('The email has already been taken.');
                        }
                    },
                ],

                'is_4ps_member' => 'nullable|string',
                'is_insurance_member' => 'nullable|string',
                'is_vaccinated' => 'nullable|string',
                'is_indigenous' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validatedData = $validator->validated();

            // 🔑 Generate password
            $plainPassword = Str::random(8);

            $validatedData['password'] = Hash::make($plainPassword);
            $validatedData['is_admitted'] = 0;

            // 📌 Auto-generate username: snl.{surname}
            if (!empty($validatedData['surname'])) {
                $usernameBase = 'snl.' . Str::lower(Str::slug($validatedData['surname'], ''));

                // ensure uniqueness
                $username = $usernameBase;
                $counter = 1;
                while (accounts::where('username', $username)->exists()) {
                    $username = $usernameBase . $counter;
                    $counter++;
                }

                $validatedData['username'] = $username;
            }

            // 📝 Save account
            $account = accounts::create($validatedData);

            // 📧 Send welcome mail
            if (!empty($account->email)) {
                Mail::raw("Welcome! Your username is: {$account->username}\nYour password is: $plainPassword", function ($message) use ($account) {
                    $message->to($account->email)
                        ->subject('Your Account Details');
                });
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'User created successfully.',
                'account' => $account,
                'plain_password' => $plainPassword, // Optional
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create admission.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function createAdminAccount(Request $request)
    {
        try {
            $user = auth()->user();

            // ✅ Only allow Super Admin
            if (!$user || $user->usertype !== 'super_admin') {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Only super admins can create admin accounts.'
                ], 403);
            }

            // Validate input fields
            $validator = Validator::make($request->all(), [
                'surname' => 'required|string|max:50',
                'given_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'suffix' => 'nullable|string|max:10',
                'email' => 'required|email|max:100|unique:accounts,email',
                'mobile_number' => 'required|string|max:15',
                'gender' => 'nullable|string|max:10',
                'date_of_birth' => 'nullable|date',
                'user_type' => 'nullable|string|in:admin,staff',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validatedData = $validator->validated();

            // Generate a default random password
            $plainPassword = Str::random(8);
            $validatedData['password'] = Hash::make($plainPassword);

            // Default usertype to 'admin' if not provided
            $validatedData['usertype'] = $validatedData['user_type'] ?? 'admin';

            // Mark as verified
            $validatedData['is_verified'] = 1;
            $validatedData['verification_code'] = null;

            // Create the account
            $account = accounts::create($validatedData);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Admin account created successfully.',
                'account' => $account,
                'default_password' => $plainPassword
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create admin account.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function createInstructor(Request $request)
    {
        try {
            $user = auth()->user();

            // Restrict access to admin and super admin only
            if (!in_array($user->user_type, ['admin', 'super admin'])) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized access. Only admin or super admin can create an instructor.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'surname' => 'required|string|max:50',
                'given_name' => 'required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'suffix' => 'nullable|string|max:10',

                'email' => 'required|email|max:100|unique:accounts,email',
                'mobile_number' => 'required|string|max:15',

                'gender' => 'nullable|string|in:Male,Female,Other',
                'date_of_birth' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Generate a random password
            $plainPassword = Str::random(8);

            // Prepare data for insertion
            $validated['password'] = Hash::make($plainPassword);
            $validated['user_type'] = 'instructor';
            $validated['is_verified'] = 1;
            $validated['verification_code'] = null;

            // Create the instructor account
            $instructor = accounts::create($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Instructor account created successfully.',
                'account' => $instructor,
                'default_password' => $plainPassword
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create instructor.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'verification_code' => 'required|string',
            ]);

            // Find user by code
            $user = accounts::where('verification_code', $validated['verification_code'])->first();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Invalid or expired verification code.',
                ], 400);
            }

            $user->is_verified = true;
            $user->verification_code = null;
            $user->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Account verified successfully.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Verification failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to get the user profile
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            return response()->json([
                'isSuccess' => true,
                'user' => $user->makeHidden(['password', 'verification_code', 'is_verified', 'created_at', 'updated_at']),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve user profile.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to change the user profile
    public function updateProfile(Request $request)
    {
        try {
            /** @var \App\Models\accounts $account */
            $account = auth()->user();

            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|required|string|max:50',
                'surname' => 'sometimes|required|string|max:50',
                'given_name' => 'sometimes|required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'suffix' => 'nullable|string|max:10',
                'date_of_birth' => 'sometimes|required|date',
                'place_of_birth' => 'sometimes|required|string|max:100',
                'gender' => 'sometimes|required|string|max:10',
                'civil_status' => 'sometimes|required|string|max:20',

                // Dropdown inputs
                'street_address' => 'sometimes|required|string|max:255',
                'province' => 'sometimes|required|string|max:100',
                'city' => 'sometimes|required|string|max:100',
                'barangay' => 'sometimes|required|string|max:100',

                'nationality' => 'sometimes|required|string|max:50',
                'religion' => 'sometimes|required|string|max:50',
                'ethnic_affiliation' => 'nullable|string|max:50',
                'telephone_number' => 'nullable|string|max:15',
                'mobile_number' => 'sometimes|required|string|max:15',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:100',
                    Rule::unique('accounts', 'email')->ignore($account->id),
                ],

                // New aligned types from createUser
                'is_4ps_member' => 'sometimes|required|boolean',
                'is_insurance_member' => 'sometimes|required|boolean',
                'is_vaccinated' => 'sometimes|required|boolean',
                'is_indigenous' => 'sometimes|required|boolean',

                // Extra info fields
                'internet_connectivity' => 'sometimes|required|string|max:50',
                'learning_modality' => 'sometimes|required|string|max:50',
                'digital_literacy' => 'sometimes|required|string|max:50',
                'device' => 'sometimes|required|string|max:50',

                // Image
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();

            // ✅ Handle file upload
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('profile_pictures'), $filename);
                $validated['profile_picture'] = 'profile_pictures/' . $filename;
            }

            $account->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Profile updated successfully.',
                'accounts' => $account,
                'profile_picture_url' => $account->profile_picture
                    ? asset($account->profile_picture)
                    : null,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update profile.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $account = accounts::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|required|string|max:50',
                'surname' => 'sometimes|required|string|max:50',
                'user_type_id' => 'sometimes|required|exists:user_types,id',
                'given_name' => 'sometimes|required|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'middle_initial' => 'nullable|string|max:5',
                'suffix' => 'nullable|string|max:10',
                'date_of_birth' => 'sometimes|required|date',
                'place_of_birth' => 'sometimes|required|string|max:100',
                'gender' => 'sometimes|required|string|max:10',
                'civil_status' => 'sometimes|required|string|max:20',

                'street_address' => 'sometimes|required|string|max:255',
                'province' => 'sometimes|required|string|max:100',
                'city' => 'sometimes|required|string|max:100',
                'barangay' => 'sometimes|required|string|max:100',

                'nationality' => 'sometimes|required|string|max:50',
                'religion' => 'sometimes|required|string|max:50',
                'ethnic_affiliation' => 'nullable|string|max:50',
                'telephone_number' => 'nullable|string|max:15',
                'mobile_number' => 'sometimes|required|string|max:15',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:100',
                    Rule::unique('accounts', 'email')->ignore($id),
                ],

                'is_4ps_member' => 'sometimes|required|boolean',
                'is_insurance_member' => 'sometimes|required|boolean',
                'is_vaccinated' => 'sometimes|required|boolean',
                'is_indigenous' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();

            // Optional: Cast boolean fields if needed
            foreach (['is_4ps_member', 'is_insurance_member', 'is_vaccinated', 'is_indigenous'] as $boolField) {
                if (array_key_exists($boolField, $validated)) {
                    $validated[$boolField] = (bool) $validated[$boolField];
                }
            }

            $account->update($validated);

            return response()->json([
                'isSuccess' => true,
                'message' => 'User updated successfully.',
                'accounts' => $account,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to update User.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function changePassword(Request $request)
    {
        try {
            $user = $request->user(); // Authenticated user

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            // Check current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Current password is incorrect.',
                ], 400);
            }

            // Update password
            $user->password = Hash::make($validated['new_password']);
            $user->save();

            return response()->json([
                'isSuccess' => true,
                'message' => 'Password changed successfully.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Password change failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to delete the user account
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user(); // Authenticated user
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }
            // Archive the user instead of deleting
            $user->is_archive = 1;
            $user->save();
            return response()->json([
                'isSuccess' => true,
                'message' => 'Account archived successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to archive account.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to restore the user account
    public function restoreAccount(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }
            // Restore the user account
            $user->is_archive = 0;
            $user->save();
            return response()->json([
                'isSuccess' => true,
                'message' => 'Account restored successfully.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to restore account.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function adminCreateAccount(Request $request)
    {
        try {
            // Authenticate admin first (optional if already protected by middleware)
            $admin = $request->user();
            if (!$admin || $admin->user_type !== 'admin') {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Only admins can create accounts.'
                ], 403);
            }

            // Validate input
            $validated = $request->validate([
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'required|email|unique:accounts,email',
                'gender' => 'required|string',
                'contact_number' => 'required|max:15',
                'user_type' => 'required|exists:user_types,id',
            ]);

            // Generate verification code and temporary password
            $verificationCode = Str::upper(Str::random(6));
            $tempPassword = Str::random(8); // You may email this to the user

            // Create account
            $user = accounts::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($tempPassword),
                'gender' => $validated['gender'],
                'contact_number' => $validated['contact_number'],
                'user_type' => $validated['user_type'],
                'verification_code' => $verificationCode,
            ])->makeHidden(['password', 'created_at', 'updated_at']);

            // Email HTML
            $html = "
            <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Hello, {$validated['first_name']}!</h2>
                    <p>An account has been created for you by the administrator.</p>
                    <p>Your temporary password is: <strong>$tempPassword</strong></p>
                    <p>Your verification code is:</p>
                    <h1 style='color: #3498db;'>$verificationCode</h1>
                    <p>Please log in, verify your account, and update your password.</p>
                    <br>
                    <small>This is an automated message. Do not reply.</small>
                </body>
            </html>
        ";

            // Send email
            Mail::send([], [], function ($message) use ($validated, $html) {
                $message->to($validated['email'])
                    ->subject('Your Account Has Been Created')
                    ->setBody($html, 'text/html');
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Account created and email sent successfully.',
                'user' => $user,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Account creation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
