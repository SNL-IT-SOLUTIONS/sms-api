<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\accounts;
use App\Models\admissions;
use Illuminate\Support\Str;


class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $googleDriver */
        $googleDriver = Socialite::driver('google');

        return $googleDriver->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();

            $user = accounts::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = accounts::create([
                    'email' => $googleUser->getEmail(),
                    'username' => $googleUser->getNickname() ?? Str::slug($googleUser->getName()),
                    'password' => Hash::make(Str::random(12)),
                    'is_admitted' => 0,
                    'is_verified' => 1,
                    'given_name' => $googleUser->user['given_name'] ?? '',
                    'last_name' => $googleUser->user['surname'] ?? '',
                ]);
            }

            $token = $user->createToken('google-token')->plainTextToken;

            // 👇 Redirect to frontend with token & user info
            return redirect()->to("https://enrollmentsystemproject.vercel.app/oauth-callback?token={$token}&user=" . urlencode(json_encode($user->makeHidden(['created_at', 'updated_at']))));
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Google login failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function redirectToGithub()
    {
        /** @var \Laravel\Socialite\Two\GithubProvider $provider */
        $provider = Socialite::driver('github');
        return $provider->stateless()->redirect();
    }

    public function handleGithubCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GithubProvider $provider */
            $provider = Socialite::driver('github');
            $githubUser = $provider->stateless()->user();

            $user = accounts::where('email', $githubUser->getEmail())->first();

            if (!$user) {
                $user = accounts::create([
                    'email' => $githubUser->getEmail(),
                    'username' => $githubUser->getNickname() ?? Str::slug($githubUser->getName()),
                    'password' => Hash::make(Str::random(12)),
                    'first_name' => $githubUser->getName() ?? '',
                    'is_verified' => 1,
                    'is_admitted' => 0,
                ]);
            }

            $token = $user->createToken('github-token')->plainTextToken;

            return redirect("https://enrollmentsystemproject.vercel.app/oauth-callback2?token=$token&user=" . urlencode(json_encode($user)));
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'GitHub login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
