<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    /**
     * Get dashboard statistics for the current user's farms
     */
    public function dashboard(Request $request)
    {
        try {
            $user = Auth::user();

            // Get all farms owned by the user
            $userFarms = $user->farms()->pluck('id');

            if ($userFarms->isEmpty()) {
                return response()->json([
                    'summary' => [
                        'total_trees' => 0,
                        'total_pods' => 0,
                        'estimated_yield_kg' => 0,
                    ],
                    'health_breakdown' => [],
                    'recent_detections' => [],
                ], 200);
            }

            // Get all trees in user's farms
            $trees = CacaoTree::whereIn('farm_id', $userFarms)->get();

            // Calculate summary stats
            $totalTrees = $trees->count();
            $totalPods = $trees->sum('pod_count') ?? 0;

            // Estimated yield: assume ~1kg per healthy tree + ~0.5kg per diseased tree
            $healthyTrees = $trees->where('status', 'Healthy')->count();
            $diseasedTrees = $totalTrees - $healthyTrees;
            $estimatedYield = ($healthyTrees * 1.0) + ($diseasedTrees * 0.5);

            // Health breakdown
            $healthBreakdown = $trees->groupBy('status')->map(function ($group, $status) {
                return [
                    'status' => $status ?? 'Unknown',
                    'total' => $group->count(),
                ];
            })->values()->toArray();

            // Recent detections (last 10)
            $recentDetections = TreeMonitoringLogs::whereIn('cacao_tree_id', $trees->pluck('id'))
                ->with('tree')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'tree_code' => $log->tree->tree_code ?? 'Unknown',
                        'disease_type' => $log->disease_type,
                        'status' => $log->status,
                        'inspection_date' => $log->inspection_date,
                        'created_at' => $log->created_at,
                    ];
                })
                ->toArray();

            return response()->json([
                'summary' => [
                    'total_trees' => $totalTrees,
                    'total_pods' => $totalPods,
                    'estimated_yield_kg' => round($estimatedYield, 2),
                ],
                'health_breakdown' => $healthBreakdown,
                'recent_detections' => $recentDetections,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load dashboard stats',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
