<?php

namespace App\Http\Controllers;

use OwenIt\Auditing\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    /**
     * Get all audit logs with user information
     */
    public function index(Request $request)
    {
        Log::info('📋 Fetching audit logs');

        try {
            $perPage = $request->get('per_page', 15);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $search = $request->get('search', '');
            $eventFilter = $request->get('event', '');
            $modelFilter = $request->get('model', '');

            $query = Audit::query()
                ->with('user')  // Load user relationship
                ->orderBy($sortBy, $sortOrder);

            // Filter by search term (user name or email)
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by event (created, updated, deleted)
            if ($eventFilter) {
                $query->where('event', $eventFilter);
            }

            // Filter by model type
            if ($modelFilter) {
                $query->where('auditable_type', $modelFilter);
            }

            $audits = $query->paginate($perPage);

            // Transform the data to include user name
            $audits->transform(function ($audit) {
                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user?->name ?? 'System',
                    'user_email' => $audit->user?->email ?? 'N/A',
                    'event' => $audit->event,
                    'auditable_type' => class_basename($audit->auditable_type),
                    'auditable_id' => $audit->auditable_id,
                    'old_values' => $audit->old_values ?? [],
                    'new_values' => $audit->new_values ?? [],
                    'url' => $audit->url,
                    'ip_address' => $audit->ip_address,
                    'user_agent' => $audit->user_agent,
                    'tags' => $audit->tags,
                    'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $audit->created_at->diffForHumans(),
                ];
            });

            Log::info('Audit logs retrieved', ['count' => $audits->count()]);

            return response()->json([
                'success' => true,
                'data' => $audits->items(),
                'pagination' => [
                    'total' => $audits->total(),
                    'per_page' => $audits->perPage(),
                    'current_page' => $audits->currentPage(),
                    'last_page' => $audits->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error fetching audit logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific model
     */
    public function getByModel($modelType, $modelId)
    {
        Log::info('Fetching audit logs for model', ['type' => $modelType, 'id' => $modelId]);

        try {
            $audits = Audit::query()
                ->where('auditable_type', 'like', "%{$modelType}%")
                ->where('auditable_id', $modelId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'user_name' => $audit->user?->name ?? 'System',
                        'event' => $audit->event,
                        'old_values' => $audit->old_values ?? [],
                        'new_values' => $audit->new_values ?? [],
                        'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $audits
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching model audit logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit statistics
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_audits' => Audit::count(),
                'today_audits' => Audit::whereDate('created_at', today())->count(),
                'events' => Audit::select('event')
                    ->distinct()
                    ->pluck('event')
                    ->toArray(),
                'models' => Audit::select('auditable_type')
                    ->distinct()
                    ->pluck('auditable_type')
                    ->map(fn($type) => class_basename($type))
                    ->toArray(),
                'top_users' => Audit::with('user')
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->selectRaw('count(*) as count')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get()
                    ->map(fn($audit) => [
                        'user_name' => $audit->user?->name ?? 'System',
                        'count' => $audit->count
                    ])
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
