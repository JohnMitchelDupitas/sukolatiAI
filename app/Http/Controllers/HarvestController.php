<?php

namespace App\Http\Controllers;

use App\Models\HarvestLog;
use App\Models\TreeMonitoringLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class HarvestController extends Controller
{
    /**
     * Store a new harvest log
     *
     * Expected JSON Request:
     * {
     *     "tree_id": 1,
     *     "pod_count": 50,
     *     "harvest_date": "2025-11-26" (optional, defaults to today)
     * }
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'tree_id' => 'required|exists:cacao_trees,id',
            'pod_count' => 'required|integer|min:1',
            'harvest_date' => 'nullable|date|date_format:Y-m-d',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Get authenticated user
            $user = Auth::user();

            // Create harvest log
            $harvestLog = HarvestLog::create([
                'tree_id' => $request->tree_id,
                'pod_count' => $request->pod_count,
                'harvest_date' => $request->harvest_date ?? now()->format('Y-m-d'),
                'harvester_id' => $user->id,
            ]);

            // Reduce pod_count in tree_monitoring_logs
            // Get the latest tree monitoring log for this tree
            Log::info("🌾 HARVEST: Searching for TreeMonitoringLog with tree_id={$request->tree_id}");

            $treeMonitoringLog = TreeMonitoringLogs::where('cacao_tree_id', $request->tree_id)
                ->latest('inspection_date')
                ->first();

            $updatedPodCount = null;
            if ($treeMonitoringLog) {
                Log::info("🌾 HARVEST: Found TreeMonitoringLog id={$treeMonitoringLog->id}, old pod_count={$treeMonitoringLog->pod_count}");
                // Reduce the pod count by the harvested amount
                $newPodCount = max(0, $treeMonitoringLog->pod_count - $request->pod_count);
                Log::info("🌾 HARVEST: Updating pod_count from {$treeMonitoringLog->pod_count} to {$newPodCount}");
                $treeMonitoringLog->update(['pod_count' => $newPodCount]);
                $updatedPodCount = $newPodCount;
                Log::info("🌾 HARVEST: Update complete, new pod_count={$updatedPodCount}");
            } else {
                Log::warning("🌾 HARVEST: No TreeMonitoringLog found for tree_id={$request->tree_id}");
            }

            // Calculate estimated dry weight
            $estimatedDryWeight = $harvestLog->pod_count * 0.04;

            return response()->json([
                'success' => true,
                'message' => 'Harvest logged successfully',
                'data' => [
                    'id' => $harvestLog->id,
                    'tree_id' => $harvestLog->tree_id,
                    'pod_count' => $harvestLog->pod_count,
                    'harvest_date' => $harvestLog->harvest_date->format('Y-m-d'),
                    'harvester_id' => $harvestLog->harvester_id,
                    'estimated_dry_weight_kg' => $estimatedDryWeight,
                    'updated_tree_pod_count' => $updatedPodCount,
                    'created_at' => $harvestLog->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error("🌾 HARVEST ERROR: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error logging harvest',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all harvest logs for the authenticated user's trees
     */
    public function index()
    {
        try {
            $user = Auth::user();

            // Get harvest logs for trees owned by the user's farms
            $harvestLogs = HarvestLog::whereIn('tree_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('cacao_trees')
                    ->whereIn('farm_id', $user->farms()->pluck('id'));
            })
            ->with(['tree', 'harvester'])
            ->orderBy('harvest_date', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $harvestLogs,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching harvest logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get harvest logs for a specific tree
     */
    public function getByTree($treeId)
    {
        try {
            $harvestLogs = HarvestLog::where('tree_id', $treeId)
                ->with('harvester')
                ->orderBy('harvest_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $harvestLogs,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching harvest logs for tree',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
