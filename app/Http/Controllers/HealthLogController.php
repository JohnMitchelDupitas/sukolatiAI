<?php

namespace App\Http\Controllers;

use App\Models\HealthLog;
use App\Models\CacaoTree;
use Illuminate\Http\Request;

class HealthLogController extends Controller
{
    public function store(Request $r, CacaoTree $cacaoTree)
    {
        $data = $r->validate([
            'notes' => 'nullable|string',
            'height_m' => 'nullable|numeric',
            'canopy_diameter_m' => 'nullable|numeric',
            'flowers' => 'nullable|boolean',
            'pods' => 'nullable|boolean',
            'observed_pests' => 'nullable|string'
        ]);
        $data['user_id'] = $r->user()->id;
        $data['cacao_tree_id'] = $cacaoTree->id;
        $log = HealthLog::create($data);
        return response()->json($log, 201);
    }
    public function index(CacaoTree $cacaoTree)
    {
        return response()->json($cacaoTree->healthLogs()->latest()->get());
    }
}
