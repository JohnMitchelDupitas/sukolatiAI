<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Farm;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisoryController extends Controller
{
    /**
     * Get recommended advisories based on disease rates by location
     */
    public function getRecommendedAdvisories(Request $request)
    {
        try {
            $advisories = [];
            
            // Get all unique locations from farms
            $locations = Farm::whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->pluck('location')
                ->filter()
                ->values();

            foreach ($locations as $location) {
                // Get farms in this location
                $farmsInLocation = Farm::where('location', $location)->pluck('id');
                
                // Get trees in these farms
                $treesInLocation = CacaoTree::whereIn('farm_id', $farmsInLocation)->pluck('id');
                
                if ($treesInLocation->isEmpty()) {
                    continue;
                }

                // Get total trees in location
                $totalTrees = $treesInLocation->count();

                // Get diseased trees by disease type
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

            return response()->json([
                'success' => true,
                'data' => $advisories
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting recommended advisories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading advisories: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send advisory to farmers in a specific location
     */
    public function sendAdvisory(Request $request)
    {
        try {
            $request->validate([
                'location' => 'required|string',
                'title' => 'required|string',
                'message' => 'required|string',
                'disease_type' => 'nullable|string'
            ]);

            // Get all farmers with farms in this location
            $farms = Farm::where('location', $request->location)->get();
            $farmerIds = $farms->pluck('user_id')->unique();

            $sentCount = 0;
            foreach ($farmerIds as $farmerId) {
                Notification::create([
                    'user_id' => $farmerId,
                    'title' => $request->title,
                    'body' => $request->message,
                    'read' => false
                ]);
                $sentCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Advisory sent to {$sentCount} farmers in {$request->location}",
                'sent_count' => $sentCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending advisory: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending advisory: ' . $e->getMessage()
            ], 500);
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
}

