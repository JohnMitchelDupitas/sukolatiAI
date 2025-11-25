<?php

namespace App\Http\Controllers;

use App\Models\CacaoTree;
use App\Models\Farm;
use Illuminate\Http\Request;

class CacaoTreeController extends Controller
{
    public function index(Request $request)
    {
        // 1. OPTIONAL: Filter by Farm ID if provided in the query URL
        // e.g. /api/trees?farm_id=5
        $query = CacaoTree::with('latestLog'); // Eager load logs if you have that relationship

        if ($request->has('farm_id')) {
            $query->where('farm_id', $request->farm_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $r)
    {
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
        // You might need to update these relationship names if you changed them
        // previously you had 'healthLogs', 'predictionLogs'.
        // Ensure they exist in your CacaoTree model.
        $cacaoTree->load('detections'); // Using the new 'detections' relationship we made earlier
        return response()->json($cacaoTree);
    }

    public function update(Request $r, CacaoTree $cacaoTree)
    {
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

    public function destroy(CacaoTree $cacaoTree)
    {
        $cacaoTree->delete();
        return response()->json(['message' => 'deleted']);
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
}
