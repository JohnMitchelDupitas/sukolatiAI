<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        if ($user->role === 'admin') return Farm::with('user', 'cacaoTrees')->paginate(20);
        return Farm::where('user_id', $user->id)->with('cacaoTrees')->get();
    }
    public function store(Request $r)
    {
        $user = $r->user();
        
        $data = $r->validate([
            'name' => 'required|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'soil_type' => 'nullable|string',
            'area_hectares' => 'nullable|numeric',
            'user_id' => 'nullable|exists:users,id' // Allow admin to specify user_id
        ]);
        
        // If admin provides user_id, use it; otherwise use authenticated user's id
        if ($user->role === 'admin' && isset($data['user_id'])) {
            $data['user_id'] = $data['user_id'];
        } else {
            $data['user_id'] = $user->id;
        }
        
        $farm = Farm::create($data);
        $farm->load('user');
        return response()->json($farm, 201);
    }
    public function show(Farm $farm)
    {
        $farm->load('cacaoTrees', 'weatherLogs');
        return response()->json($farm);
    }
    public function update(Request $r, Farm $farm)
    {
        if ($r->user()->role === 'farmer' && $r->user()->id !== $farm->user_id) return response()->json(['message' => 'Forbidden'], 403);
        $farm->update($r->only(['name', 'location', 'latitude', 'longitude', 'soil_type', 'area_hectares']));
        return response()->json($farm);
    }
    public function destroy(Farm $farm)
    {
        $farm->delete();
        return response()->json(['message' => 'deleted']);
    }
}
