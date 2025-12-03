<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'firstname' => 'required|string|max:255',
            'middlename' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'required|regex:/^09\d{9}$/|unique:users,phone',
        ]);

        $user = User::create([
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'phone' => $data['phone'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create login history
     */
    public function login(Request $request)
    {
        Log::info('[LOGIN] Attempt started', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('[LOGIN] Invalid credentials', ['email' => $request->email]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if user is admin - only admins can login to Vue admin app
        if ($user->role !== 'admin') {
            Log::warning('[LOGIN] Non-admin user attempted login', [
                'email' => $request->email,
                'role' => $user->role
            ]);
            return response()->json([
                'message' => 'Access denied. Only administrators can access this application.',
                'error' => 'unauthorized_role'
            ], 403);
        }

        // CREATE LOGIN HISTORY
        try {
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now(),
            ]);

            Log::info('[LOGIN] Login history recorded', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

        } catch (\Exception $e) {
            Log::error('[LOGIN] Failed to record login history', [
                'error' => $e->getMessage()
            ]);
        }

        //  CREATE API TOKEN
        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('[LOGIN] Login successful', [
            'user_id' => $user->id,
            'user_email' => $user->email
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'farm_id' => $user->farm_id,
            ]
        ], 200);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        Log::info('[LOGOUT] User logging out', ['user_id' => auth()->id()]);

        $request->user()->currentAccessToken()->delete();

        Log::info('[LOGOUT] User logged out successfully');

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function getMe(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'firstname' => $user->firstname,
                'middlename' => $user->middlename,
                'lastname' => $user->lastname,
                'phone' => $user->phone,
                'role' => $user->role,
                'farm_id' => $user->farm_id,
                'created_at' => $user->created_at,
            ]
        ]);
    }
}
