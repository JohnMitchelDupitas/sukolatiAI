<?php

namespace App\Http\Controllers;

use App\Models\CacaoTree;
use App\Models\Farm;
use Illuminate\Http\Request;

class CacaoTreeController extends Controller
{
    public function index(Farm $farm)
    {
        return response()->json($farm->cacaoTrees()->with('healthLogs', 'predictionLogs')->get());
    }
    public function store(Request $r, Farm $farm)
    {
        $data = $r->validate([
            'block_name' => 'nullable|string',
            'tree_count' => 'nullable|integer|min:1',
            'variety' => 'nullable|string',
            'date_planted' => 'nullable|date',
            'growth_stage' => 'nullable|string'
        ]);
        $data['farm_id'] = $farm->id;
        $tree = CacaoTree::create($data);
        return response()->json($tree, 201);
    }
    public function show(CacaoTree $cacaoTree)
    {
        $cacaoTree->load('healthLogs', 'predictionLogs');
        return response()->json($cacaoTree);
    }
    public function update(Request $r, CacaoTree $cacaoTree)
    {
        $cacaoTree->update($r->only(['block_name', 'tree_count', 'variety', 'date_planted', 'growth_stage', 'status']));
        return response()->json($cacaoTree);
    }
    public function destroy(CacaoTree $cacaoTree)
    {
        $cacaoTree->delete();
        return response()->json(['message' => 'deleted']);
    }
}
