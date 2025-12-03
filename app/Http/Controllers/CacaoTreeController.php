<?php

namespace App\Http\Controllers;

use App\Models\CacaoTree;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class CacaoTreeController extends Controller
{
    private function setAuditVariables($request)
    {
        DB::statement('SET @current_user_id = ?', [auth()?->id()]);
        DB::statement('SET @current_ip_address = ?', [$request->ip()]);
        DB::statement('SET @current_user_agent = ?', [$request->userAgent()]);
    }

    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // For admin, get all trees. For farmers, get only their trees
        $query = CacaoTree::with(['latestLog', 'farm']);
        
        if ($user->role !== 'admin') {
            $query->whereIn('farm_id', $user->farms()->pluck('id'));
        }
        
        $trees = $query->get()
            ->map(function($tree) {
                // Return pod_count from tree_monitoring_logs (latestLog)
                return [
                    'id' => $tree->id,
                    'farm_id' => $tree->farm_id,
                    'tree_code' => $tree->tree_code,
                    'block_name' => $tree->block_name,
                    'variety' => $tree->variety,
                    'date_planted' => $tree->date_planted,
                    'latitude' => $tree->latitude,
                    'longitude' => $tree->longitude,
                    'pod_count' => $tree->latestLog?->pod_count ?? 0, // FROM tree_monitoring_logs
                    'status' => $tree->status,
                    'growth_stage' => $tree->growth_stage,
                    'created_at' => $tree->created_at,
                    'updated_at' => $tree->updated_at,
                    'farm' => $tree->farm ? [
                        'id' => $tree->farm->id,
                        'name' => $tree->farm->name,
                        'location' => $tree->farm->location,
                    ] : null,
                    'latest_log' => $tree->latestLog ? [
                        'id' => $tree->latestLog->id,
                        'status' => $tree->latestLog->status,
                        'disease_type' => $tree->latestLog->disease_type,
                        'pod_count' => $tree->latestLog->pod_count,
                        'image_path' => $tree->latestLog->metadata?->image_path,
                        'inspection_date' => $tree->latestLog->inspection_date,
                    ] : null,
                ];
            });

        return response()->json($trees);
    }

    /**
     * Get trees for a specific farm
     * Route: /farms/{farm}/cacao-trees
     * Returns pod_count from tree_monitoring_logs (latestLog)
     */
    public function indexByFarm(Request $request, Farm $farm)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Allow admins to view all farms, farmers can only view their own farms
        if ($user->role !== 'admin' && $farm->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get only trees from the specified farm
        $trees = CacaoTree::with('latestLog')
            ->where('farm_id', $farm->id)
            ->get()
            ->map(function($tree) {
                //  Return pod_count from tree_monitoring_logs (latestLog)
                return [
                    'id' => $tree->id,
                    'farm_id' => $tree->farm_id,
                    'tree_code' => $tree->tree_code,
                    'block_name' => $tree->block_name,
                    'variety' => $tree->variety,
                    'date_planted' => $tree->date_planted,
                    'latitude' => $tree->latitude,
                    'longitude' => $tree->longitude,
                    'pod_count' => $tree->latestLog?->pod_count ?? 0, //  FROM tree_monitoring_logs
                    'status' => $tree->status,
                    'growth_stage' => $tree->growth_stage,
                    'created_at' => $tree->created_at,
                    'updated_at' => $tree->updated_at,
                    'latest_log' => $tree->latestLog ? [
                        'id' => $tree->latestLog->id,
                        'status' => $tree->latestLog->status,
                        'disease_type' => $tree->latestLog->disease_type,
                        'pod_count' => $tree->latestLog->pod_count,
                        'image_path' => $tree->latestLog->metadata?->image_path,
                        'inspection_date' => $tree->latestLog->inspection_date,
                    ] : null,
                ];
            });

        return response()->json($trees);
    }

    public function store(Request $r)
    {
        $this->setAuditVariables($r);

        // 2. UPDATED VALIDATION
        $data = $r->validate([
            'farm_id'      => 'required|exists:farms,id', // Ensure tree links to a farm
            'tree_code'    => 'required|string|unique:cacao_trees,tree_code', // Critical for identification
            'latitude'     => 'required|numeric',  // GIS Data
            'longitude'    => 'required|numeric',  // GIS Data

            'block_name'   => 'nullable|string',
            'variety'      => 'nullable|string',
            'date_planted' => 'nullable|date',
            'growth_stage' => 'nullable|string',
            // 'tree_count' => 'nullable|integer|min:1', // REMOVED: As discussed, 1 row = 1 tree
        ]);

        // 3. CREATE THE TREE
        $tree = CacaoTree::create($data);

        return response()->json([
            'message' => 'Tree registered successfully with GPS coordinates',
            'data' => $tree
        ], 201);
    }

    public function show(CacaoTree $cacaoTree)
    {
        try {
            // Load the tree with latestLog relationship
            // latestLog contains pod_count from tree_monitoring_logs
            $cacaoTree->load('latestLog');

            //  Return pod_count from tree_monitoring_logs (latestLog)
            return response()->json([
                'id' => $cacaoTree->id,
                'farm_id' => $cacaoTree->farm_id,
                'tree_code' => $cacaoTree->tree_code,
                'block_name' => $cacaoTree->block_name,
                'variety' => $cacaoTree->variety,
                'date_planted' => $cacaoTree->date_planted,
                'latitude' => $cacaoTree->latitude,
                'longitude' => $cacaoTree->longitude,
                'pod_count' => $cacaoTree->latestLog?->pod_count ?? 0, //  FROM tree_monitoring_logs
                'status' => $cacaoTree->status,
                'growth_stage' => $cacaoTree->growth_stage,
                'created_at' => $cacaoTree->created_at,
                'updated_at' => $cacaoTree->updated_at,
                'latest_log' => $cacaoTree->latestLog ? [
                    'id' => $cacaoTree->latestLog->id,
                    'status' => $cacaoTree->latestLog->status,
                    'disease_type' => $cacaoTree->latestLog->disease_type,
                    'pod_count' => $cacaoTree->latestLog->pod_count,
                    'image_path' => $cacaoTree->latestLog->metadata?->image_path,
                    'inspection_date' => $cacaoTree->latestLog->inspection_date,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading tree: ' . $e->getMessage(), [
                'tree_id' => $cacaoTree->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to load tree details',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $r, CacaoTree $cacaoTree)
    {
        $this->setAuditVariables($r);

        // 4. ALLOW UPDATING GPS
        $cacaoTree->update($r->only([
            'tree_code',
            'block_name',
            'variety',
            'date_planted',
            'growth_stage',
            'status',
            'latitude',  // Allow correcting the location
            'longitude'
        ]));

        return response()->json($cacaoTree);
    }

    public function destroy(CacaoTree $cacaoTree, Request $request)
    {
        $this->setAuditVariables($request);

        $cacaoTree->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tree deleted successfully'
        ]);
    }

    // UPDATE POD COUNT
    public function updatePods(Request $request, $id)
    {
        $request->validate([
            'pod_count' => 'required|integer|min:0',
        ]);

        $tree = CacaoTree::find($id);

        if (!$tree) {
            return response()->json(['message' => 'Tree not found'], 404);
        }

        // Update the tree directly
        $tree->update(['pod_count' => $request->pod_count]);

        // OPTIONAL: Log this event in the history table so you have a timeline
        // "On Nov 24, Farmer updated pods to 25"
        // You can create a simple 'TreeLog' model for this later if you want history.

        return response()->json([
            'message' => 'Pod count updated successfully',
            'tree' => $tree
        ]);
    }

    public function dashboardStats(Request $request)
    {
        // 1. Build query to get trees, filtering by farm if provided
        $treeQuery = \App\Models\CacaoTree::with('latestLog');
        if ($request->has('farm_id')) {
            $treeQuery->where('farm_id', $request->farm_id);
        }

        $trees = $treeQuery->get();
        $totalTrees = $trees->count();

        // 2. Calculate total pods from tree_monitoring_logs (latestLog)
        // ✅ This is the REAL pod count from tree_monitoring_logs table
        $totalPods = $trees->sum(function($tree) {
            return $tree->latestLog?->pod_count ?? 0;
        });

        // Estimate Yield (Assumption: 1 pod = 0.04 kg of dried beans - adjust this value)
        $estimatedYieldKg = $totalPods * 0.04;

        // 3. Health stats from cacao_trees table
        $query = \App\Models\CacaoTree::query();
        if ($request->has('farm_id')) {
            $query->where('farm_id', $request->farm_id);
        }

        $healthStats = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // 4. Variety Breakdown (Bar Chart Data)
        $varietyStats = $query->select('variety', DB::raw('count(*) as total'))
            ->groupBy('variety')
            ->get();

        return response()->json([
            'summary' => [
                'total_trees' => $totalTrees,
                'total_pods'  => $totalPods,
                'estimated_yield_kg' => round($estimatedYieldKg, 2),
            ],
            'health_breakdown' => $healthStats,
            'variety_inventory' => $varietyStats
        ]);
    }
}
