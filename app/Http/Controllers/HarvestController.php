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
            'pod_count' => $request->pod_count
        ]);

        $validated = $request->validate([
            'tree_id' => 'required|exists:cacao_trees,id',
            'pod_count' => 'required|integer|min:1',
            'harvest_date' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated, $userId) {
            Log::info('Harvest transaction started');

            try {
                // STEP 1: Create harvest log
                $harvest = HarvestLog::create([
                    'tree_id' => $validated['tree_id'],
                    'pod_count' => $validated['pod_count'],
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
                    $newPodCount = max(0, $oldPodCount - $validated['pod_count']);

                    Log::info('Updating pod count', [
                        'old_count' => $oldPodCount,
                        'harvested' => $validated['pod_count'],
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

                $dryWeight = $validated['pod_count'] * 0.04;

                Log::info('Harvest transaction completed');

                return response()->json([
                    'success' => true,
                    'message' => 'Harvest logged successfully',
                    'data' => [
                        'harvest_id' => $harvest->id,
                        'tree_id' => $harvest->tree_id,
                        'pods_harvested' => $harvest->pod_count,
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
}
