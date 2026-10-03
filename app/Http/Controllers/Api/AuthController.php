<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash; 
use Illuminate\Support\Facades\Password; 
use Illuminate\Support\Facades\Auth; 
class AuthController extends Controller
{
    public function register(Request $request){
        $request->validate(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    // 'regex:/^[a-zA-Z\s]+$/',
                ],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'ends_with:@gmail.com',
                    'unique:users,email',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[0-9]/',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[!@#$%^&*()-+]/',
                    'confirmed',      // Must match password_confirmation
                ],
            ],
            [
                'name.required' => 'Name is required.',
                'name.regex' => 'Name must contain letters and spaces only.',

                'email.required' => 'Email is required.',
                'email.email' => 'Please enter a valid email address.',
                'email.ends_with' => 'Email must end with @gmail.com.',
                'email.unique' => 'Email already exists.',

                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters.',
                'password.regex' => 'Password must contain uppercase, lowercase, number, and special character.',
                'password.confirmed' => 'Passwords do not match.',
            ]
        );

        $email = $request->email;

        if(User::where('email', $email)->exists()){
            return response()->json([
                'success' => false,
                'message' => 'Email already exists'
            ], 409);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => $user,
        ], 201);
    }
    //Login function
    public function login(Request $request){
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login details'
            ], 401);
        }
        //User is authenticated, retrieve the user
        $user = Auth::user();
        // Get roles
        $roles = $user->getRoleNames();
        // Get permissions
        $permissions = $user->getAllPermissions()->pluck('name');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'user' => $user,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'token' => $token,
        ]);
        
    }
    public function dashboard(Request $request){

        $user_login = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Welcome to the dashboard',
            'data' => $user_login,
        ]);
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully'
        ]);
    }
    
    public function forgotPassword(Request $request){
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send password reset link.'
            ], 500);
        }
    }
    public function resetPassword(Request $request){
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[!@#$%^&*()-+]/',
                'confirmed',      // Must match password_confirmation
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password.'
            ], 500);
        }
    }
    public function googleLogin(Request $request){
        $request->validate([
            'google_id' => 'required|string|unique:users,google_id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|ends_with:@gmail.com|unique:users,email',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'google_id' => $request->google_id,
            // You can set a default password or leave it null
            'password' => Hash::make(uniqid()), // Random password
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function googleLoginExisting(Request $request){
        $request->validate([
            'google_id' => 'required|string|exists:users,google_id',
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with the provided Google ID.'
            ], 404);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function googleLogout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'User logged out from Google successfully'
        ]);
    }
    public function googleForgotPassword(Request $request){
        $request->validate([
            'google_id' => 'required|string|exists:users,google_id',
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with the provided Google ID.'
            ], 404);
        }

        // Here you can implement your logic to send a password reset link or token to the user's email.
        // For demonstration, we'll just return a success message.

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email associated with Google account.'
        ]);
    }
    public function googleResetPassword(Request $request){
        $request->validate([
            'google_id' => 'required|string|exists:users,google_id',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[!@#$%^&*()-+]/',
                'confirmed',      // Must match password_confirmation
            ],
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with the provided Google ID.'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully for the Google account.'
        ]);
    }
    public function googleDashboard(Request $request){
        $user_login = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Welcome to the Google dashboard',
            'data' => $user_login,
        ]);
    }
    public function googleRegister(Request $request){
        $request->validate([
            'google_id' => 'required|string|unique:users,google_id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|ends_with:@gmail.com|unique:users,email',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'google_id' => $request->google_id,
            // You can set a default password or leave it null
            'password' => Hash::make(uniqid()), // Random password
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function googleLoginOrRegister(Request $request){
        $request->validate([
            'google_id' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|ends_with:@gmail.com',
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            // If user doesn't exist, create a new one
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'google_id' => $request->google_id,
                // You can set a default password or leave it null
                'password' => Hash::make(uniqid()), // Random password
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in or registered with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function googleLoginOrRegisterExisting(Request $request){
        $request->validate([
            'google_id' => 'required|string|exists:users,google_id',
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with the provided Google ID.'
            ], 404);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function googleLoginOrRegisterNew(Request $request){
        $request->validate([
            'google_id' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|ends_with:@gmail.com',
        ]);

        $user = User::where('google_id', $request->google_id)->first();

        if (!$user) {
            // If user doesn't exist, create a new one
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'google_id' => $request->google_id,
                // You can set a default password or leave it null
                'password' => Hash::make(uniqid()), // Random password
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in or registered with Google successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
}
