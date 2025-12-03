<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;
use App\Models\Farm;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Get comprehensive admin dashboard statistics
     * Simple database queries - no complex logic
     */
    public function index(Request $request)
    {
        try {
            // 1. Total Registered Farmers - direct count from users table
            $totalFarmers = User::count();

            // 2. Total Farms - direct count from farms table
            $totalFarms = Farm::count();

            // 3. Total Trees Mapped - direct count from cacao_trees table
            $totalTrees = CacaoTree::count();

            // 4. Active Disease Cases - count from tree_monitoring_logs where disease_type is not null/empty and created today
            $today = now()->startOfDay();
            $activeDiseaseCases = TreeMonitoringLogs::whereDate('created_at', $today)
                ->whereNotNull('disease_type')
                ->where('disease_type', '!=', '')
                ->count();

            // 5. Total Estimated Yield (kg) - sum pod_count from tree_monitoring_logs table
            $totalPods = DB::table('tree_monitoring_logs')
                ->whereNotNull('pod_count')
                ->sum('pod_count');
            // Estimate: 1 pod ≈ 0.04 kg of dried beans
            $estimatedYieldKg = round($totalPods * 0.04, 2);

            // 6. Disease Distribution - count by status from tree_monitoring_logs
            $diseaseDistribution = $this->getDiseaseDistribution();

            // 7. Recent Activity Feed - get recent audits and monitoring logs
            $recentActivities = $this->getRecentActivities();

            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_farmers' => $totalFarmers,
                        'total_farms' => $totalFarms,
                        'total_trees' => $totalTrees,
                        'active_disease_cases' => $activeDiseaseCases,
                        'estimated_yield_kg' => $estimatedYieldKg,
                    ],
                    'disease_distribution' => $diseaseDistribution,
                    'recent_activities' => $recentActivities,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get disease distribution for pie chart
     * Direct database query - count by disease_type from tree_monitoring_logs
     */
    private function getDiseaseDistribution()
    {
        // Count healthy trees (status = 'healthy' or disease_type is null/empty)
        $healthy = TreeMonitoringLogs::where(function($query) {
            $query->where('status', 'healthy')
                  ->orWhere(function($q) {
                      $q->whereNull('disease_type')
                        ->orWhere('disease_type', '');
                  });
        })->count();

        // Count Black Pod cases
        $blackPod = TreeMonitoringLogs::where(function($query) {
            $query->where('disease_type', 'like', '%Black Pod%')
                  ->orWhere('disease_type', 'like', '%black pod%')
                  ->orWhere('status', 'like', '%Black Pod%');
        })->count();

        // Count Pod Borer cases
        $podBorer = TreeMonitoringLogs::where(function($query) {
            $query->where('disease_type', 'like', '%Pod Borer%')
                  ->orWhere('disease_type', 'like', '%pod borer%')
                  ->orWhere('disease_type', 'like', '%Borer%')
                  ->orWhere('status', 'like', '%Pod Borer%');
        })->count();

        // Count other diseases
        $other = TreeMonitoringLogs::whereNotNull('disease_type')
            ->where('disease_type', '!=', '')
            ->where('disease_type', 'not like', '%Black Pod%')
            ->where('disease_type', 'not like', '%black pod%')
            ->where('disease_type', 'not like', '%Pod Borer%')
            ->where('disease_type', 'not like', '%pod borer%')
            ->where('disease_type', 'not like', '%Borer%')
            ->where('status', '!=', 'healthy')
            ->count();

        return [
            ['label' => 'Healthy', 'value' => $healthy, 'color' => '#28a745'],
            ['label' => 'Black Pod', 'value' => $blackPod, 'color' => '#dc3545'],
            ['label' => 'Pod Borer', 'value' => $podBorer, 'color' => '#ffc107'],
            ['label' => 'Other', 'value' => $other, 'color' => '#6c757d'],
        ];
    }

    /**
     * Get recent activity feed
     * Direct database queries - get recent records
     */
    private function getRecentActivities($limit = 20)
    {
        $activities = [];

        // Get recent audits from audits table
        $audits = Audit::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        foreach ($audits as $audit) {
            $userName = $audit->user ? ($audit->user->firstname . ' ' . $audit->user->lastname) : 'System';
            $modelName = class_basename($audit->auditable_type);
            $event = ucfirst($audit->event);

            $message = $this->formatActivityMessage($userName, $event, $modelName, $audit);
            
            $activities[] = [
                'id' => 'audit_' . $audit->id,
                'type' => 'audit',
                'message' => $message,
                'user_name' => $userName,
                'user_email' => $audit->user?->email ?? 'N/A',
                'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $audit->created_at->diffForHumans(),
                'icon' => $this->getActivityIcon($event),
                'color' => $this->getActivityColor($event),
            ];
        }

        // Get recent disease detections from tree_monitoring_logs
        $recentDetections = TreeMonitoringLogs::with(['user', 'tree'])
            ->whereNotNull('disease_type')
            ->where('disease_type', '!=', '')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentDetections as $detection) {
            $userName = $detection->user ? ($detection->user->firstname . ' ' . $detection->user->lastname) : 'Unknown Farmer';
            $treeCode = $detection->tree?->tree_code ?? 'Tree #' . $detection->cacao_tree_id;
            $disease = $detection->disease_type ?? 'Unknown Disease';

            $activities[] = [
                'id' => 'detection_' . $detection->id,
                'type' => 'detection',
                'message' => "{$userName} detected {$disease} on {$treeCode}",
                'user_name' => $userName,
                'user_email' => $detection->user?->email ?? 'N/A',
                'created_at' => $detection->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $detection->created_at->diffForHumans(),
                'icon' => 'bi-exclamation-triangle',
                'color' => '#dc3545',
            ];
        }

        // Get recent tree registrations from cacao_trees table
        $recentTrees = CacaoTree::with('farm.user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($recentTrees as $tree) {
            $farmerName = 'Unknown Farmer';
            if ($tree->farm && $tree->farm->user) {
                $farmerName = $tree->farm->user->firstname . ' ' . $tree->farm->user->lastname;
            }
            $treeCode = $tree->tree_code ?? 'Tree #' . $tree->id;

            $activities[] = [
                'id' => 'tree_' . $tree->id,
                'type' => 'tree_registration',
                'message' => "{$farmerName} registered {$treeCode}",
                'user_name' => $farmerName,
                'user_email' => $tree->farm?->user?->email ?? 'N/A',
                'created_at' => $tree->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $tree->created_at->diffForHumans(),
                'icon' => 'bi-tree',
                'color' => '#28a745',
            ];
        }

        // Sort by created_at descending and limit
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Format activity message from audit
     */
    private function formatActivityMessage($userName, $event, $modelName, $audit)
    {
        $modelDisplay = $this->getModelDisplayName($modelName);
        
        if ($event === 'Created') {
            return "{$userName} created a new {$modelDisplay}";
        } elseif ($event === 'Updated') {
            $changes = [];
            if (isset($audit->new_values)) {
                $newValues = is_array($audit->new_values) ? $audit->new_values : json_decode($audit->new_values, true);
                if (isset($newValues['pod_count'])) {
                    $changes[] = "pod count to {$newValues['pod_count']}";
                }
                if (isset($newValues['status'])) {
                    $changes[] = "status to {$newValues['status']}";
                }
            }
            $changeText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';
            return "{$userName} updated {$modelDisplay}{$changeText}";
        } elseif ($event === 'Deleted') {
            return "{$userName} deleted a {$modelDisplay}";
        }
        
        return "{$userName} {$event} a {$modelDisplay}";
    }

    /**
     * Get display name for model
     */
    private function getModelDisplayName($modelName)
    {
        $names = [
            'TreeMonitoringLogs' => 'tree monitoring log',
            'CacaoTree' => 'tree',
            'Farm' => 'farm',
            'HarvestLog' => 'harvest log',
            'User' => 'user',
        ];
        
        return $names[$modelName] ?? strtolower($modelName);
    }

    /**
     * Get activity icon based on event type
     */
    private function getActivityIcon($event)
    {
        $icons = [
            'Created' => 'bi-plus-circle',
            'Updated' => 'bi-pencil',
            'Deleted' => 'bi-trash',
        ];
        
        return $icons[$event] ?? 'bi-circle';
    }

    /**
     * Get activity color based on event type
     */
    private function getActivityColor($event)
    {
        $colors = [
            'Created' => '#28a745',
            'Updated' => '#007bff',
            'Deleted' => '#dc3545',
        ];
        
        return $colors[$event] ?? '#6c757d';
    }
}

