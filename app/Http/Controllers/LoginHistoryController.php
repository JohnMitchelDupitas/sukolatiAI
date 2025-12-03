<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginHistoryController extends Controller
{
    /**
     * Get all login histories for the authenticated user
     */
    public function myLoginHistories(Request $request)
    {
        Log::info(' [LOGIN HISTORY] Fetching user login histories', [
            'user_id' => auth()->id()
        ]);

        try {
            $perPage = $request->get('per_page', 15);

            $histories = LoginHistory::where('user_id', auth()->id())
                ->orderBy('login_at', 'desc')
                ->paginate($perPage)
                ->through(function ($history) {
                    return [
                        'id' => $history->id,
                        'user_id' => $history->user_id,
                        'ip_address' => $history->ip_address,
                        'user_agent' => $history->user_agent,
                        'login_at' => $history->login_at->format('Y-m-d H:i:s'),
                        'login_at_human' => $history->login_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $histories->items(),
                'pagination' => [
                    'total' => $histories->total(),
                    'per_page' => $histories->perPage(),
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[LOGIN HISTORY] Error fetching login histories', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all login histories (Admin only)
     */
    public function getAllLoginHistories(Request $request)
    {
        Log::info(' [LOGIN HISTORY] Admin fetching all login histories');

        try {
            $perPage = $request->get('per_page', 15);
            $userId = $request->get('user_id');
            $search = $request->get('search', '');

            $query = LoginHistory::with('user')
                ->orderBy('login_at', 'desc');

            // Filter by user_id if provided
            if ($userId) {
                $query->where('user_id', $userId);
            }

            // Search by user name or email
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $histories = $query->paginate($perPage)
                ->through(function ($history) {
                    return [
                        'id' => $history->id,
                        'user_id' => $history->user_id,
                        'user_name' => $history->user?->name,
                        'user_email' => $history->user?->email,
                        'ip_address' => $history->ip_address,
                        'user_agent' => $history->user_agent,
                        'login_at' => $history->login_at->format('Y-m-d H:i:s'),
                        'login_at_human' => $history->login_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $histories->items(),
                'pagination' => [
                    'total' => $histories->total(),
                    'per_page' => $histories->perPage(),
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('[LOGIN HISTORY] Error fetching all login histories', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
