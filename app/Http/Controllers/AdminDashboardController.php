<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;
use App\Models\Farm;
use App\Models\HarvestLog;
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
            $totalFarmers = User::where('role', 'farmer')->count();

            // 2. Total Inventory (Total Trees Mapped) - direct count from cacao_trees table
            $totalInventory = CacaoTree::count();

            // 3. Infection Rate - % of trees currently flagged as diseased
            // Get trees with latest monitoring logs that have disease
            $totalTrees = CacaoTree::count();
            $diseasedTrees = CacaoTree::whereHas('latestLog', function($query) {
                $query->whereNotNull('disease_type')
                      ->where('disease_type', '!=', '');
            })->count();
            $infectionRate = $totalTrees > 0 ? round(($diseasedTrees / $totalTrees) * 100, 2) : 0;

            // 4. Regional Yield Forecast - Total estimated harvest (kg) for the season
            $totalPods = DB::table('tree_monitoring_logs')
                ->whereNotNull('pod_count')
                ->sum('pod_count');
            // Estimate: 1 pod ≈ 0.04 kg of dried beans
            $regionalYieldForecast = round($totalPods * 0.04, 2);

            // 6. Disease Distribution - count by status from tree_monitoring_logs
            $diseaseDistribution = $this->getDiseaseDistribution();

            // 7. Recent Activity Feed - get recent audits and monitoring logs
            $recentActivities = $this->getRecentActivities();

            // 8. Top Harvested Trees - rank trees by total pods harvested
            $topHarvestedTrees = $this->getTopHarvestedTrees(5);

            // 9. Top Harvested Farms - rank farms by total pods harvested
            $topHarvestedFarms = $this->getTopHarvestedFarms(5);

            // 8. New Infections per Week (for trend line chart)
            $infectionTrend = $this->getInfectionTrend();

            // 9. Recommended Advisories
            $recommendedAdvisories = $this->getRecommendedAdvisories();

            // 10. Intervention Plans (Resource Allocation)
            $interventionPlans = $this->getInterventionPlans();

            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_farmers' => $totalFarmers,
                        'total_inventory' => $totalInventory,
                        'infection_rate' => $infectionRate,
                        'regional_yield_forecast' => $regionalYieldForecast,
                    ],
                    'disease_distribution' => $diseaseDistribution,
                    'infection_trend' => $infectionTrend,
                    'recommended_advisories' => $recommendedAdvisories,
                    'intervention_plans' => $interventionPlans,
                    'recent_activities' => $recentActivities,
                    'top_harvested_trees' => $topHarvestedTrees,
                    'top_harvested_farms' => $topHarvestedFarms,
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
     * Dynamically gets all unique diseases from latest monitoring logs (current state)
     */
    private function getDiseaseDistribution()
    {
        $distribution = [];
        
        // Get all trees with their latest logs to get current disease state
        $trees = CacaoTree::with('latestLog')->get();
        
        // Count diseases from latest logs only (current state)
        $diseaseCounts = [];
        $healthyCount = 0;
        
        foreach ($trees as $tree) {
            $log = $tree->latestLog;
            
            if (!$log) {
                // Tree with no logs is considered healthy
                $healthyCount++;
                continue;
            }
            
            // Check disease_type first
            $diseaseType = $log->disease_type;
            $status = $log->status;
            
            // Determine if healthy
            $isHealthy = false;
            if (empty($diseaseType) || 
                strtolower(trim($diseaseType)) === 'healthy' ||
                strtolower(trim($diseaseType)) === 'null') {
                if (empty($status) || 
                    strtolower(trim($status)) === 'healthy' ||
                    strtolower(trim($status)) === 'null') {
                    $isHealthy = true;
                }
            }
            
            if ($isHealthy) {
                $healthyCount++;
            } else {
                // Use disease_type if available, otherwise use status
                $diseaseName = !empty($diseaseType) ? trim($diseaseType) : trim($status);
                
                if (!empty($diseaseName) && strtolower($diseaseName) !== 'healthy') {
                    if (!isset($diseaseCounts[$diseaseName])) {
                        $diseaseCounts[$diseaseName] = 0;
                    }
                    $diseaseCounts[$diseaseName]++;
                }
            }
        }
        
        // Add healthy count
        if ($healthyCount > 0) {
            $distribution[] = ['label' => 'Healthy', 'value' => $healthyCount, 'color' => '#28a745'];
        }
        
        // Get all unique disease types from counts
        arsort($diseaseCounts); // Sort by count descending

        // Color palette for diseases
        $colors = [
            '#dc3545', // Red - Black Pod
            '#ffc107', // Yellow - Pod Borer
            '#17a2b8', // Cyan - Frosty Pod
            '#6f42c1', // Purple - Witches Broom
            '#fd7e14', // Orange - Canker
            '#e83e8c', // Pink - Other diseases
            '#20c997', // Teal
            '#6610f2', // Indigo
            '#f8d7da', // Light Red
            '#fff3cd', // Light Yellow
        ];

        $colorIndex = 0;
        foreach ($diseaseCounts as $diseaseName => $count) {
            // Use specific colors for known diseases
            $color = $this->getDiseaseColor($diseaseName, $colors, $colorIndex);
            
            $distribution[] = [
                'label' => $diseaseName,
                'value' => $count,
                'color' => $color
            ];
            
            $colorIndex++;
        }

        return $distribution;
    }

    /**
     * Get color for a specific disease
     */
    private function getDiseaseColor($diseaseName, $colors, $colorIndex)
    {
        $diseaseLower = strtolower($diseaseName);
        
        // Map known diseases to specific colors
        if (strpos($diseaseLower, 'black pod') !== false) {
            return '#dc3545'; // Red
        } elseif (strpos($diseaseLower, 'pod borer') !== false || strpos($diseaseLower, 'borer') !== false) {
            return '#ffc107'; // Yellow
        } elseif (strpos($diseaseLower, 'frosty pod') !== false || strpos($diseaseLower, 'frosty') !== false) {
            return '#17a2b8'; // Cyan
        } elseif (strpos($diseaseLower, 'witches') !== false || strpos($diseaseLower, 'broom') !== false) {
            return '#6f42c1'; // Purple
        } elseif (strpos($diseaseLower, 'canker') !== false) {
            return '#fd7e14'; // Orange
        } else {
            // Use color from palette, cycling through if needed
            return $colors[$colorIndex % count($colors)];
        }
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

    /**
     * Get top harvested trees ranked by total pods harvested
     */
    private function getTopHarvestedTrees($limit = 10)
    {
        $topTrees = HarvestLog::select(
                'harvest_logs.tree_id',
                'cacao_trees.tree_code',
                'cacao_trees.id as cacao_tree_id',
                'farms.name as farm_name',
                'users.firstname',
                'users.lastname',
                DB::raw('SUM(harvest_logs.pod_count) as total_pods_harvested'),
                DB::raw('COUNT(harvest_logs.id) as harvest_count')
            )
            ->join('cacao_trees', 'harvest_logs.tree_id', '=', 'cacao_trees.id')
            ->join('farms', 'cacao_trees.farm_id', '=', 'farms.id')
            ->leftJoin('users', 'farms.user_id', '=', 'users.id')
            ->groupBy('harvest_logs.tree_id', 'cacao_trees.tree_code', 'cacao_trees.id', 'farms.name', 'users.firstname', 'users.lastname')
            ->orderBy('total_pods_harvested', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                $userName = trim(($item->firstname ?? '') . ' ' . ($item->lastname ?? ''));
                return [
                    'rank' => $index + 1,
                    'tree_id' => $item->tree_id,
                    'tree_code' => $item->tree_code ?? 'Tree #' . $item->tree_id,
                    'farm_name' => $item->farm_name ?? 'Unknown Farm',
                    'farmer_name' => $userName ?: 'Unknown',
                    'total_pods_harvested' => (int) $item->total_pods_harvested,
                    'harvest_count' => (int) $item->harvest_count,
                    'estimated_weight_kg' => round($item->total_pods_harvested * 0.04, 2),
                ];
            });

        return $topTrees->toArray();
    }

    /**
     * Get top harvested farms ranked by total pods harvested
     */
    private function getTopHarvestedFarms($limit = 10)
    {
        $topFarms = HarvestLog::select(
                'farms.id as farm_id',
                'farms.name as farm_name',
                DB::raw('SUM(harvest_logs.pod_count) as total_pods_harvested'),
                DB::raw('COUNT(DISTINCT harvest_logs.tree_id) as tree_count'),
                DB::raw('COUNT(harvest_logs.id) as harvest_count'),
                'users.firstname',
                'users.lastname'
            )
            ->join('cacao_trees', 'harvest_logs.tree_id', '=', 'cacao_trees.id')
            ->join('farms', 'cacao_trees.farm_id', '=', 'farms.id')
            ->leftJoin('users', 'farms.user_id', '=', 'users.id')
            ->groupBy('farms.id', 'farms.name', 'users.firstname', 'users.lastname')
            ->orderBy('total_pods_harvested', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                $userName = trim(($item->firstname ?? '') . ' ' . ($item->lastname ?? ''));
                return [
                    'rank' => $index + 1,
                    'farm_id' => $item->farm_id,
                    'farm_name' => $item->farm_name ?? 'Unknown Farm',
                    'farmer_name' => $userName ?: 'Unknown',
                    'total_pods_harvested' => (int) $item->total_pods_harvested,
                    'tree_count' => (int) $item->tree_count,
                    'harvest_count' => (int) $item->harvest_count,
                    'estimated_weight_kg' => round($item->total_pods_harvested * 0.04, 2),
                ];
            });

        return $topFarms->toArray();
    }

    /**
     * Get infection trend - new infections per week
     */
    private function getInfectionTrend($weeks = 12)
    {
        $trend = [];
        $now = now();
        
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $weekEnd = $now->copy()->subWeeks($i)->endOfWeek();
            
            // Count new disease detections in this week
            $newInfections = TreeMonitoringLogs::whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereNotNull('disease_type')
                ->where('disease_type', '!=', '')
                ->count();
            
            $trend[] = [
                'week' => $weekStart->format('M d'),
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'new_infections' => $newInfections
            ];
        }
        
        return $trend;
    }

    /**
     * Get recommended advisories based on disease rates by location
     */
    private function getRecommendedAdvisories()
    {
        try {
            $advisories = [];
            
            // Get all unique locations from farms
            $locations = \App\Models\Farm::whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->pluck('location')
                ->filter()
                ->values();

            foreach ($locations as $location) {
                // Get farms in this location
                $farmsInLocation = \App\Models\Farm::where('location', $location)->pluck('id');
                
                // Get trees in these farms
                $treesInLocation = \App\Models\CacaoTree::whereIn('farm_id', $farmsInLocation)->pluck('id');
                
                if ($treesInLocation->isEmpty()) {
                    continue;
                }

                // Get total trees in location
                $totalTrees = $treesInLocation->count();

                // Get diseased trees by disease type (using latest logs)
                $diseaseStats = TreeMonitoringLogs::whereIn('cacao_tree_id', $treesInLocation)
                    ->whereNotNull('disease_type')
                    ->where('disease_type', '!=', '')
                    ->select('disease_type', DB::raw('COUNT(DISTINCT cacao_tree_id) as infected_count'))
                    ->groupBy('disease_type')
                    ->get();

                foreach ($diseaseStats as $stat) {
                    $infectionRate = ($stat->infected_count / $totalTrees) * 100;
                    $diseaseType = $stat->disease_type;

                    // Check if infection rate exceeds threshold
                    $threshold = $this->getDiseaseThreshold($diseaseType);
                    
                    if ($infectionRate >= $threshold) {
                        $advisory = $this->generateAdvisory($location, $diseaseType, $infectionRate, $stat->infected_count, $totalTrees);
                        $advisories[] = $advisory;
                    }
                }
            }

            return $advisories;

        } catch (\Exception $e) {
            Log::error('Error getting recommended advisories: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get disease threshold for triggering advisory
     */
    private function getDiseaseThreshold($diseaseType)
    {
        $thresholds = [
            'Black Pod Disease' => 15,
            'Black Pod' => 15,
            'Pod Borer' => 20,
            'Pod Borer Disease' => 20,
        ];

        // Check for partial matches
        foreach ($thresholds as $key => $threshold) {
            if (stripos($diseaseType, $key) !== false) {
                return $threshold;
            }
        }

        // Default threshold
        return 15;
    }

    /**
     * Generate advisory message based on disease type and location
     */
    private function generateAdvisory($location, $diseaseType, $infectionRate, $infectedCount, $totalTrees)
    {
        $prescription = $this->getPrescription($diseaseType);
        
        return [
            'id' => md5($location . $diseaseType),
            'location' => $location,
            'disease_type' => $diseaseType,
            'infection_rate' => round($infectionRate, 2),
            'infected_trees' => $infectedCount,
            'total_trees' => $totalTrees,
            'reason' => "{$diseaseType} infection rate exceeded " . $this->getDiseaseThreshold($diseaseType) . "% in this area.",
            'title' => "ALERT: High Risk of {$diseaseType} in {$location}",
            'prescription' => $prescription,
            'priority' => $infectionRate >= 25 ? 'high' : ($infectionRate >= 20 ? 'medium' : 'low')
        ];
    }

    /**
     * Get prescription message based on disease type
     */
    private function getPrescription($diseaseType)
    {
        $prescriptions = [
            'Black Pod Disease' => 'ALERT: High risk of Black Pod detected in your area. Please perform sanitary pruning and spray copper fungicide immediately. Remove and destroy infected pods to prevent further spread.',
            'Black Pod' => 'ALERT: High risk of Black Pod detected in your area. Please perform sanitary pruning and spray copper fungicide immediately. Remove and destroy infected pods to prevent further spread.',
            'Pod Borer' => 'ALERT: High risk of Pod Borer infestation detected in your area. Please apply appropriate insecticides and monitor your trees regularly. Remove and destroy infested pods immediately.',
            'Pod Borer Disease' => 'ALERT: High risk of Pod Borer infestation detected in your area. Please apply appropriate insecticides and monitor your trees regularly. Remove and destroy infested pods immediately.',
        ];

        // Check for partial matches
        foreach ($prescriptions as $key => $prescription) {
            if (stripos($diseaseType, $key) !== false) {
                return $prescription;
            }
        }

        // Default prescription
        return "ALERT: High risk of {$diseaseType} detected in your area. Please take immediate action to prevent further spread. Consult with agricultural experts for specific treatment recommendations.";
    }

    /**
     * Get intervention plans - Resource allocation recommendations
     * Converts biological data into logistics and operations data
     */
    private function getInterventionPlans()
    {
        try {
            $plans = [];
            
            // Get all unique locations (sectors) from farms
            $locations = Farm::whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->pluck('location')
                ->filter()
                ->values();

            foreach ($locations as $location) {
                // Get farms in this location (sector)
                $farmsInLocation = Farm::where('location', $location)
                    ->with('cacaoTrees')
                    ->get();
                
                if ($farmsInLocation->isEmpty()) {
                    continue;
                }

                // Get all trees in these farms
                $treeIds = [];
                $farmNames = [];
                foreach ($farmsInLocation as $farm) {
                    $treeIds = array_merge($treeIds, $farm->cacaoTrees->pluck('id')->toArray());
                    $farmNames[] = $farm->name;
                }

                if (empty($treeIds)) {
                    continue;
                }

                $totalTrees = count($treeIds);

                // Analyze disease outbreaks by disease type
                $diseaseAnalysis = TreeMonitoringLogs::whereIn('cacao_tree_id', $treeIds)
                    ->whereNotNull('disease_type')
                    ->where('disease_type', '!=', '')
                    ->select('disease_type', DB::raw('COUNT(DISTINCT cacao_tree_id) as infected_count'))
                    ->groupBy('disease_type')
                    ->get();

                foreach ($diseaseAnalysis as $analysis) {
                    $diseaseType = $analysis->disease_type;
                    $infectedCount = $analysis->infected_count;
                    $infectionRate = ($infectedCount / $totalTrees) * 100;
                    
                    // Only create intervention plan for significant outbreaks (>= 10% infection rate)
                    if ($infectionRate >= 10) {
                        $severity = $this->getSeverityLevel($infectionRate);
                        $resources = $this->calculateResources($diseaseType, $infectedCount, $farmsInLocation->count(), $severity);
                        
                        $plans[] = [
                            'id' => md5($location . $diseaseType),
                            'target_location' => $location,
                            'target_farms' => $farmNames,
                            'farm_count' => $farmsInLocation->count(),
                            'issue' => $this->formatIssue($diseaseType, $infectionRate, $infectedCount, $totalTrees),
                            'severity' => $severity,
                            'recommendations' => $resources,
                            'total_trees' => $totalTrees,
                            'infected_trees' => $infectedCount,
                            'infection_rate' => round($infectionRate, 2),
                        ];
                    }
                }
            }

            // Sort by severity (high to low) and infection rate
            usort($plans, function($a, $b) {
                $severityOrder = ['critical' => 4, 'severe' => 3, 'moderate' => 2, 'mild' => 1];
                $aSeverity = $severityOrder[$a['severity']] ?? 0;
                $bSeverity = $severityOrder[$b['severity']] ?? 0;
                
                if ($aSeverity !== $bSeverity) {
                    return $bSeverity - $aSeverity;
                }
                
                return $b['infection_rate'] <=> $a['infection_rate'];
            });

            return $plans;

        } catch (\Exception $e) {
            Log::error('Error getting intervention plans: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get severity level based on infection rate
     */
    private function getSeverityLevel($infectionRate)
    {
        if ($infectionRate >= 40) return 'critical';
        if ($infectionRate >= 25) return 'severe';
        if ($infectionRate >= 15) return 'moderate';
        return 'mild';
    }

    /**
     * Calculate resource needs based on disease, infected trees, and severity
     */
    private function calculateResources($diseaseType, $infectedTrees, $farmCount, $severity)
    {
        $resources = [];
        
        // Base calculations
        $severityMultiplier = [
            'critical' => 1.5,
            'severe' => 1.2,
            'moderate' => 1.0,
            'mild' => 0.8,
        ];
        $multiplier = $severityMultiplier[$severity] ?? 1.0;

        // Agricultural Technicians
        // 1 technician per 3-5 farms, more for severe outbreaks
        $techniciansNeeded = max(1, ceil($farmCount / (5 / $multiplier)));
        $resources[] = [
            'type' => 'personnel',
            'resource' => 'Agricultural Technicians',
            'quantity' => $techniciansNeeded,
            'description' => "Deploy {$techniciansNeeded} Agricultural Technician" . ($techniciansNeeded > 1 ? 's' : '') . " for training and on-site support.",
            'priority' => $severity === 'critical' || $severity === 'severe' ? 'high' : 'medium',
        ];

        // Disease-specific resources
        if (stripos($diseaseType, 'Pod Borer') !== false || stripos($diseaseType, 'Borer') !== false) {
            // Pod Borer requires sleeving bags
            // Estimate: 2-3 bags per infected tree (for protection and replacement)
            $bagsNeeded = ceil($infectedTrees * 2.5 * $multiplier);
            $resources[] = [
                'type' => 'supplies',
                'resource' => 'Sleeving Bags',
                'quantity' => $bagsNeeded,
                'description' => "Subsidize/Distribute {$bagsNeeded} Sleeving Bags to these farmers for pod protection.",
                'priority' => 'high',
            ];

            // Insecticides for Pod Borer
            $insecticideLiters = ceil($infectedTrees * 0.1 * $multiplier);
            $resources[] = [
                'type' => 'supplies',
                'resource' => 'Insecticides',
                'quantity' => $insecticideLiters,
                'unit' => 'liters',
                'description' => "Provide {$insecticideLiters} liters of appropriate insecticides for treatment.",
                'priority' => $severity === 'critical' || $severity === 'severe' ? 'high' : 'medium',
            ];
        }

        if (stripos($diseaseType, 'Black Pod') !== false) {
            // Black Pod requires fungicides
            $fungicideLiters = ceil($infectedTrees * 0.15 * $multiplier);
            $resources[] = [
                'type' => 'supplies',
                'resource' => 'Copper Fungicides',
                'quantity' => $fungicideLiters,
                'unit' => 'liters',
                'description' => "Provide {$fungicideLiters} liters of copper fungicide for treatment.",
                'priority' => 'high',
            ];

            // Pruning tools for sanitary pruning
            $pruningTools = ceil($farmCount / 2);
            $resources[] = [
                'type' => 'equipment',
                'resource' => 'Pruning Tools',
                'quantity' => $pruningTools,
                'description' => "Distribute {$pruningTools} sets of pruning tools for sanitary pruning.",
                'priority' => 'medium',
            ];
        }

        // General resources for any disease outbreak
        if ($severity === 'critical' || $severity === 'severe') {
            $resources[] = [
                'type' => 'personnel',
                'resource' => 'Extension Officers',
                'quantity' => max(1, ceil($farmCount / 5)),
                'description' => "Assign Extension Officer" . (ceil($farmCount / 5) > 1 ? 's' : '') . " for continuous monitoring and support.",
                'priority' => 'high',
            ];
        }

        return $resources;
    }

    /**
     * Format issue description
     */
    private function formatIssue($diseaseType, $infectionRate, $infectedCount, $totalTrees)
    {
        $severity = $this->getSeverityLevel($infectionRate);
        $severityText = ucfirst($severity);
        
        return "{$severityText} {$diseaseType} Outbreak - {$infectedCount} out of {$totalTrees} trees affected ({$infectionRate}% infection rate)";
    }
}

