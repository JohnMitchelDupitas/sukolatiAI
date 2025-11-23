<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TreeLogController extends Controller
{
    public function store(Request $request, $treeId)
    {
        // 1. Validate
        $request->validate([
            'status' => 'required|string',
            'disease_type' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'user_id' => 'required', // In real app, use auth()->id()
            'inspection_date' => 'required|date',
        ]);

        // 2. Handle Image Upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Stores in storage/app/public/disease_reports
            $imagePath = $request->file('image')->store('disease_reports', 'public');
        }

        // 3. Create the Log
        $log = \App\Models\TreeMonitoringLogs::create([
            'cacao_tree_id' => $treeId,
            'user_id' => $request->user_id, // For Postman testing, send this manually
            'status' => $request->status,
            'disease_type' => $request->disease_type,
            'image_path' => $imagePath,
            'inspection_date' => $request->inspection_date,
        ]);

        return response()->json(['message' => 'Log created', 'data' => $log], 201);
    }

    public function updateInventory(Request $request, $treeId)
    {
        $request->validate([
            'pod_count' => 'required|integer|min:0',
        ]);

        // 1. Fetch the MOST RECENT log for this tree
        // We do this to grab the specific disease name (e.g., "Black Pod Rot")
        $lastLog = \App\Models\TreeMonitoringLogs::where('cacao_tree_id', $treeId)
            ->latest('id') // Get the newest one
            ->first();

        // 2. Determine what to copy
        // If a log exists, copy its status and disease type.
        // If no log exists (new tree), default to 'healthy'.
        $preservedStatus = $lastLog ? $lastLog->status : 'healthy';
        $preservedDisease = $lastLog ? $lastLog->disease_type : null;

        // 3. Create the new log (Update Count, Keep Disease)
        $log = \App\Models\TreeMonitoringLogs::create([
            'cacao_tree_id'   => $treeId,
            'user_id'         => $request->user() ? $request->user()->id : 1,

            // PASTE THE OLD DATA HERE
            'status'          => $preservedStatus,
            'disease_type'    => $preservedDisease,

            // PASTE THE NEW DATA HERE
            'pod_count'       => $request->pod_count,

            'image_path'      => null, // No new image for a count update
            'inspection_date' => now(),
        ]);

        return response()->json([
            'message' => 'Inventory Updated',
            'data' => $log,
            'preserved_health' => $preservedDisease // Just for your verification in Postman
        ]);
    }
}
