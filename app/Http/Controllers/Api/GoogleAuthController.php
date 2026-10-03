<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function callback()
    {
        try {
            // ទាញយកព័ត៌មានពី Google
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                } else {
                    $user = User::create([
                        'name' => $googleUser->getName()
                            ?? $googleUser->getNickname()
                            ?? 'Google User',
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'password' => Hash::make(Str::random(32)),
                        'email_verified_at' => now(),
                    ]);

                    // Assign Role ប្រសិនបើប្រើ Spatie Permission
                    if (method_exists($user, 'assignRole')) {
                        $user->assignRole('User');
                    }
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect(
                $frontendUrl . '/auth/google/callback?token=' . urlencode($token)
            );

        } catch (\Exception $e) {
            // កត់ត្រា Error ពិតប្រាកដចូលក្នុង storage/logs/laravel.log
            Log::error('Google Auth Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            // ផ្ញើសារ Error ដើម្បីងាយស្រួល Debug លើ React Frontend
            return redirect(
                $frontendUrl . '/login?error=' . urlencode($e->getMessage())
            );
        }
    }
}