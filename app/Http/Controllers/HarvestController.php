<?php

namespace App\Http\Controllers;

use App\Models\HarvestLog;
use App\Models\TreeMonitoringLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarvestController extends Controller
{
    /**
     * Store harvest log with TRANSACTION and proper auditing
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        Log::info(' HarvestController::store() called', [
            'user_id' => $userId,
            'tree_id' => $request->tree_id,
            'pod_count' => $request->pod_count,
            'reject_pods' => $request->reject_pods ?? 0
        ]);

        $validated = $request->validate([
            'tree_id' => 'required|exists:cacao_trees,id',
            'pod_count' => 'required|integer|min:1',
            'reject_pods' => 'nullable|integer|min:0',
            'harvest_date' => 'nullable|date',
        ]);
        
        // Ensure reject_pods doesn't exceed pod_count
        $rejectPods = $validated['reject_pods'] ?? 0;
        if ($rejectPods > $validated['pod_count']) {
            return response()->json([
                'success' => false,
                'message' => 'Reject pods cannot exceed total harvested pods'
            ], 422);
        }

        return DB::transaction(function () use ($validated, $userId, $rejectPods) {
            Log::info('Harvest transaction started');

            try {
                // STEP 1: Create harvest log
                $harvest = HarvestLog::create([
                    'tree_id' => $validated['tree_id'],
                    'pod_count' => $validated['pod_count'],
                    'reject_pods' => $rejectPods,
                    'harvest_date' => $validated['harvest_date'] ?? now()->toDateString(),
                    'harvester_id' => $userId,
                ]);

                Log::info('Harvest log created', ['harvest_id' => $harvest->id]);

                // STEP 2: Get the latest tree monitoring log
                $treeMonitoringLog = TreeMonitoringLogs::where('cacao_tree_id', $validated['tree_id'])
                    ->latest('inspection_date')
                    ->first();

                if ($treeMonitoringLog) {
                    $oldPodCount = $treeMonitoringLog->pod_count;
                    // Subtract both harvested pods AND rejected pods from tree count
                    // This ensures rejects are also removed from the tree inventory
                    $totalPodsToSubtract = $validated['pod_count'] + $rejectPods;
                    $newPodCount = max(0, $oldPodCount - $totalPodsToSubtract);

                    Log::info('Updating pod count', [
                        'old_count' => $oldPodCount,
                        'harvested' => $validated['pod_count'],
                        'reject_pods' => $rejectPods,
                        'total_subtracted' => $totalPodsToSubtract,
                        'new_count' => $newPodCount,
                        'user_id' => $userId
                    ]);

                    // STEP 3: Update pod count (Auditable will log this)
                    $treeMonitoringLog->update([
                        'pod_count' => $newPodCount,
                        'user_id' => $userId  // Ensure user_id is set in model
                    ]);

                    Log::info('Pod count reduced', [
                        'monitoring_log_id' => $treeMonitoringLog->id,
                        'new_count' => $newPodCount,
                        'user_id' => $userId
                    ]);

                    // Verify audit was created
                    $latestAudit = $treeMonitoringLog->audits()->latest()->first();
                    if ($latestAudit) {
                        Log::info('Audit record created', [
                            'audit_id' => $latestAudit->id,
                            'user_id' => $latestAudit->user_id,
                            'user_type' => $latestAudit->user_type,
                            'old_values' => $latestAudit->old_values,
                            'new_values' => $latestAudit->new_values
                        ]);
                    } else {
                        Log::warning('Audit not created');
                    }

                } else {
                    Log::warning('No tree monitoring log found');
                }

                // Calculate dry weight excluding rejects
                $usablePods = $validated['pod_count'] - $rejectPods;
                $dryWeight = $usablePods * 0.04;

                Log::info('Harvest transaction completed');

                return response()->json([
                    'success' => true,
                    'message' => 'Harvest logged successfully',
                    'data' => [
                        'harvest_id' => $harvest->id,
                        'tree_id' => $harvest->tree_id,
                        'pods_harvested' => $harvest->pod_count,
                        'reject_pods' => $rejectPods,
                        'usable_pods' => $usablePods,
                        'estimated_dry_weight_kg' => round($dryWeight, 2),
                        'remaining_pod_count' => $treeMonitoringLog?->pod_count ?? 0,
                    ]
                ], 201);

            } catch (\Exception $e) {
                Log::error('Harvest error', ['error' => $e->getMessage()]);
                throw $e;
            }
        }, 3);
    }

    /**
     * Get all harvest logs (for admin)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search', '');
            $treeId = $request->get('tree_id');
            $farmId = $request->get('farm_id');

            $query = HarvestLog::with(['tree.farm.user', 'harvester']);

            // If not admin, only show their own harvests
            if ($user->role !== 'admin') {
                $query->where('harvester_id', $user->id);
            }

            // Filter by tree_id
            if ($treeId) {
                $query->where('tree_id', $treeId);
            }

            // Filter by farm_id
            if ($farmId) {
                $query->whereHas('tree', function($q) use ($farmId) {
                    $q->where('farm_id', $farmId);
                });
            }

            // Search by tree code or farm name
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('tree', function($subQ) use ($search) {
                        $subQ->where('tree_code', 'like', "%{$search}%")
                             ->orWhereHas('farm', function($farmQ) use ($search) {
                                 $farmQ->where('name', 'like', "%{$search}%");
                             });
                    });
                });
            }

            $harvests = $query->orderBy('harvest_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $harvests->getCollection()->transform(function ($harvest) {
                $tree = $harvest->tree;
                $farm = $tree?->farm;
                $farmer = $farm?->user;
                $harvester = $harvest->harvester;

                $farmerName = null;
                if ($farmer) {
                    $farmerName = trim(($farmer->firstname ?? '') . ' ' . ($farmer->lastname ?? ''));
                }

                $harvesterName = null;
                if ($harvester) {
                    $harvesterName = trim(($harvester->firstname ?? '') . ' ' . ($harvester->lastname ?? ''));
                }

                $rejectPods = $harvest->reject_pods ?? 0;
                $usablePods = $harvest->pod_count - $rejectPods;
                
                return [
                    'id' => $harvest->id,
                    'tree_id' => $harvest->tree_id,
                    'tree_code' => $tree?->tree_code ?? 'Tree #' . $harvest->tree_id,
                    'farm_id' => $farm?->id,
                    'farm_name' => $farm?->name ?? 'Unknown Farm',
                    'farmer_name' => $farmerName ?: 'Unknown',
                    'harvester_id' => $harvest->harvester_id,
                    'harvester_name' => $harvesterName ?: 'Unknown',
                    'pod_count' => $harvest->pod_count,
                    'reject_pods' => $rejectPods,
                    'usable_pods' => $usablePods,
                    'estimated_weight_kg' => round($usablePods * 0.04, 2),
                    'harvest_date' => $harvest->harvest_date?->format('Y-m-d') ?? 'N/A',
                    'created_at' => $harvest->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $harvest->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $harvests->items(),
                'pagination' => [
                    'total' => $harvests->total(),
                    'per_page' => $harvests->perPage(),
                    'current_page' => $harvests->currentPage(),
                    'last_page' => $harvests->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching harvest logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get harvest audit logs
     */
    public function getAuditLogs($treeId)
    {
        Log::info('Fetching audit logs for tree: ' . $treeId);

        try {
            // Get TreeMonitoringLogs audits
            $logs = TreeMonitoringLogs::where('cacao_tree_id', $treeId)
                ->get();

            $audits = \OwenIt\Auditing\Models\Audit::where('auditable_type', 'App\Models\TreeMonitoringLogs')
                ->whereIn('auditable_id', $logs->pluck('id'))
                ->with('user')  // Load user relationship
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'event' => $audit->event,
                        'user_id' => $audit->user_id,
                        'user_name' => $audit->user?->name ?? 'System',
                        'auditable_type' => $audit->auditable_type,
                        'old_values' => $audit->old_values,
                        'new_values' => $audit->new_values,
                        'tags' => $audit->tags,
                        'created_at' => $audit->created_at,
                    ];
                });

            Log::info('Audit logs retrieved', ['count' => $audits->count()]);

            return response()->json([
                'success' => true,
                'data' => $audits
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving audit logs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Harvest Forecast Report
     * Shows forecast data per farm with filters
     */
    public function getForecastReport(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Filters
            $location = $request->get('location', ''); // Barangay/Municipality filter
            $minYield = $request->get('min_yield', 0); // Minimum yield in kg
            $marketPrice = $request->get('market_price', 180); // Default ₱180/kg

            // Get all farms with their trees and latest monitoring logs
            $query = \App\Models\Farm::with(['cacaoTrees.latestLog', 'user']);

            // Filter by location if provided
            if ($location) {
                $query->where('location', 'like', "%{$location}%");
            }

            // If not admin, only show their farms
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            $farms = $query->get();

            $reportData = $farms->map(function ($farm) use ($marketPrice) {
                // Get all trees for this farm
                $trees = $farm->cacaoTrees;

                // Calculate productive trees (trees with > 0 pods in latest log)
                $productiveTrees = $trees->filter(function ($tree) {
                    $latestLog = $tree->latestLog;
                    return $latestLog && $latestLog->pod_count > 0;
                });

                // Calculate total pod count (sum of pod_count from latest logs)
                $totalPodCount = $productiveTrees->sum(function ($tree) {
                    return $tree->latestLog?->pod_count ?? 0;
                });

                // Calculate estimated yield (kg)
                $estimatedYield = round($totalPodCount * 0.04, 2);

                // Calculate estimated revenue (PHP)
                $estimatedRevenue = round($estimatedYield * $marketPrice, 2);

                // Determine status
                $status = 'Low';
                if ($estimatedYield >= 500) {
                    $status = 'High';
                } elseif ($estimatedYield >= 50) {
                    $status = 'Medium';
                }

                return [
                    'farm_id' => $farm->id,
                    'farm_name' => $farm->name,
                    'location' => $farm->location ?? 'N/A',
                    'total_productive_trees' => $productiveTrees->count(),
                    'current_pod_count' => (int) $totalPodCount,
                    'estimated_yield_kg' => $estimatedYield,
                    'estimated_revenue_php' => $estimatedRevenue,
                    'status' => $status,
                ];
            })
            ->filter(function ($item) use ($minYield) {
                // Filter by minimum yield
                return $item['estimated_yield_kg'] >= $minYield;
            })
            ->sortByDesc('estimated_yield_kg')
            ->values();

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'filters' => [
                    'location' => $location,
                    'min_yield' => $minYield,
                    'market_price' => $marketPrice,
                ],
                'summary' => [
                    'total_farms' => $reportData->count(),
                    'total_productive_trees' => $reportData->sum('total_productive_trees'),
                    'total_pod_count' => $reportData->sum('current_pod_count'),
                    'total_estimated_yield_kg' => round($reportData->sum('estimated_yield_kg'), 2),
                    'total_estimated_revenue_php' => round($reportData->sum('estimated_revenue_php'), 2),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating harvest forecast report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
