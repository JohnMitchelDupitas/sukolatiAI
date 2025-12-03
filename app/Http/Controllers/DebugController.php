<?php

namespace App\Http\Controllers;

use App\Models\CacaoTree;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function testLatestLog()
    {
        try {
            $tree = CacaoTree::with('latestLog')->find(7);

            if (!$tree) {
                return response()->json(['error' => 'Tree not found'], 404);
            }

            Log::info('DEBUG: Testing latestLog relationship', [
                'tree_id' => $tree->id,
                'tree_code' => $tree->tree_code,
                'tree_pod_count' => $tree->pod_count,
                'has_latest_log' => $tree->latestLog ? true : false,
                'latest_log' => $tree->latestLog ? [
                    'id' => $tree->latestLog->id,
                    'pod_count' => $tree->latestLog->pod_count,
                    'inspection_date' => $tree->latestLog->inspection_date,
                ] : null,
            ]);

            return response()->json([
                'tree_id' => $tree->id,
                'tree_code' => $tree->tree_code,
                'tree_pod_count' => $tree->pod_count,
                'latest_log' => $tree->latestLog ? [
                    'id' => $tree->latestLog->id,
                    'pod_count' => $tree->latestLog->pod_count,
                    'inspection_date' => $tree->latestLog->inspection_date,
                ] : null,
                'message' => 'Debug test complete'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in testLatestLog', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
