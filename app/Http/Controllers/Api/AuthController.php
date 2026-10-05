<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash; 
use Illuminate\Support\Facades\Password; 
use Illuminate\Support\Facades\Auth; 
use Spatie\Permission\Models\Role;
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
        // Assign a default role (e.g., 'user')
        $user->assignRole('User');

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
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // 1. ទាញយក Roles និង Permissions ពី Spatie
            $roles = $user->getRoleNames(); // ទទួលបាន ["Admin"]
            $permissions = $user->getAllPermissions()->pluck('name'); // ទទួលបាន ["permissions.view", ...]

            return response()->json([
                'success' => true,
                'message' => 'User dashboard data retrieved successfully',
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'បរាជ័យក្នុងការទាញយកទិន្នន័យអ្នកប្រើប្រាស់',
                'error' => $e->getMessage()
            ], 500);
        }
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
}