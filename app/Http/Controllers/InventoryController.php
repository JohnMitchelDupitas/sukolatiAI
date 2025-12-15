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

            // Get all trees in user's farms with their latest log
            $trees = CacaoTree::whereIn('farm_id', $userFarms)
                ->with('latestLog')
                ->get();

            // Calculate summary stats
            $totalTrees = $trees->count();
            $totalPods = $trees->sum(function($tree) {
                return $tree->latestLog?->pod_count ?? $tree->pod_count ?? 0;
            });

            // Health breakdown based on latest_log, not cacao_trees.status
            $healthBreakdownMap = [];
            $healthyCount = 0;
            
            foreach ($trees as $tree) {
                $log = $tree->latestLog;
                $status = "Healthy"; // Default to healthy
                
                if ($log != null) {
                    $diseaseType = $log->disease_type;
                    $logStatus = $log->status;
                    
                    // If disease_type exists and is not empty/null/healthy, tree is diseased
                    if ($diseaseType != null && 
                        trim($diseaseType) !== '' && 
                        strtolower(trim($diseaseType)) !== 'healthy' &&
                        strtolower(trim($diseaseType)) !== 'null') {
                        $status = $diseaseType; // Use disease name as status
                    } 
                    // If disease_type is null/empty/healthy, check status field
                    else if ($logStatus != null && 
                             trim($logStatus) !== '' &&
                             strtolower(trim($logStatus)) !== 'healthy' &&
                             strtolower(trim($logStatus)) !== 'null') {
                        $status = $logStatus;
                    } 
                    // Otherwise, tree is healthy
                    else {
                        $status = "Healthy";
                    }
                }
                
                // Count by status
                $statusLower = strtolower(trim($status));
                if ($statusLower === "healthy" || $statusLower === '') {
                    $healthyCount++;
                } else {
                    // Group by disease type
                    if (!isset($healthBreakdownMap[$status])) {
                        $healthBreakdownMap[$status] = 0;
                    }
                    $healthBreakdownMap[$status]++;
                }
            }
            
            // Build health breakdown array
            $healthBreakdown = [];
            if ($healthyCount > 0) {
                $healthBreakdown[] = [
                    'status' => 'Healthy',
                    'total' => $healthyCount,
                ];
            }
            // Add all disease types
            foreach ($healthBreakdownMap as $diseaseType => $count) {
                $healthBreakdown[] = [
                    'status' => $diseaseType,
                    'total' => $count,
                ];
            }
            
            // Estimated yield based on active pods from latest monitoring logs
            // 1 pod = 0.04 kg of dried beans
            $estimatedYield = $totalPods * 0.04;

            // Recent detections with issues (last 10, filtered to only show diseased trees)
            $recentDetections = TreeMonitoringLogs::whereIn('cacao_tree_id', $trees->pluck('id'))
                ->whereNotNull('disease_type')
                ->where('disease_type', '!=', '')
                ->where(function($query) {
                    // Exclude healthy trees - only show trees with actual diseases
                    $query->where(function($q) {
                        $q->where('disease_type', '!=', 'Healthy')
                          ->where('disease_type', '!=', 'healthy')
                          ->where('disease_type', 'not like', '%Healthy%');
                    });
                })
                ->with('tree')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'tree_id' => $log->cacao_tree_id, // Add tree_id for navigation
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
