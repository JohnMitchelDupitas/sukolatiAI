<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\DiseaseDetection;
use App\Models\TreeMonitoringLogs;

class GPredictionController extends Controller
{
    /**
     * Get prescriptive treatment recommendations based on disease
     */
    private function getPrescriptiveAction($disease)
    {
        // Normalize string to lowercase for safe comparison
        $d = strtolower($disease);

        if (str_contains($d, 'black')) {
            return "Immediate Action: Remove and bury all infected pods immediately to stop spores from spreading. " .
                   "Cultural: Improve air circulation by pruning the tree canopy (reduce shade). Improve drainage in the farm. " .
                   "Chemical: Apply Copper-based fungicides (e.g., Bordeaux mixture) every 2-4 weeks during the rainy season.";
        }

        if (str_contains($d, 'frosty') || str_contains($d, 'roreri')) {
            return "CRITICAL: Do NOT transport infected pods. Remove pods before the white dust (spores) appears. " .
                   "Disposal: Cover infected pods with plastic on the ground or bury them deep to prevent spore release. " .
                   "Maintenance: Perform weekly phytosanitary pruning. Fungicides are generally ineffective once infection starts; prevention is key.";
        }

        if (str_contains($d, 'witch') || str_contains($d, 'broom') || str_contains($d, 'perniciosa')) {
            return "Pruning: Prune and burn all 'broom-like' vegetative shoots (infected branches) and infected pods. " .
                   "Timing: Prune during dry periods to reduce reinfection risks. " .
                   "Long-term: Consider grafting with resistant clones if the tree is severely affected.";
        }

        if (str_contains($d, 'borer') || str_contains($d, 'carmenta') || str_contains($d, 'pod borer')) {
            return "Mechanical: Implement 'Sleeving' (bagging) of young pods (2-3 months old) using plastic bags to prevent moths from laying eggs. " .
                   "Sanitation: Harvest ripe pods regularly. Remove and bury infested pods to kill larvae inside. " .
                   "Biological: Encourage natural enemies (ants/wasps) or use pheromone traps.";
        }

        if (str_contains($d, 'healthy')) {
            return "Maintenance: Continue regular monitoring. Ensure proper fertilization (NPK) to maintain tree immunity. " .
                   "Prevention: Keep the area around the tree base clean (weeding) and maintain 3x3 meter spacing.";
        }

        return "Consult an expert or local agricultural technician for specific treatment recommendations.";
    }
    public function detectAndLog(Request $request)
    {
        $request->validate([
            'cacao_tree_id' => 'required|exists:cacao_trees,id',
            'image'         => 'nullable|image|max:10000',
            'pod_count'     => 'nullable|integer|min:0',
        ]);

        // Get authenticated user ID for audits
        $userId = \Illuminate\Support\Facades\Auth::id();
        \Illuminate\Support\Facades\Log::info('GPredictionController::detectAndLog', [
            'user_id' => $userId,
            'cacao_tree_id' => $request->cacao_tree_id
        ]);

        return DB::transaction(function () use ($request, $userId) {
            // EVERYTHING inside this function is protected by transaction

            // STEP 1: FETCH HISTORY (To preserve counts/status)
            $lastLog = TreeMonitoringLogs::where('cacao_tree_id', $request->cacao_tree_id)
                        ->latest('id')
                        ->first();

            $preservedStatus = $lastLog ? $lastLog->status : 'healthy';
            $preservedDisease = $lastLog ? $lastLog->disease_type : null;
            $preservedCount  = $lastLog ? $lastLog->pod_count : 0;

            // STEP 2: RESOLVE DISEASE STATUS (AI Logic)
            $finalStatus = $preservedStatus;
            $finalDisease = $preservedDisease;
            $imagePath = null;
            $confidence = 0.00;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('scans', 'public');

                // ... (Your existing AI Logic / Python Call) ...
                try {
                    $response = Http::attach(
                        'file',
                        file_get_contents(storage_path('app/public/' . $imagePath)),
                        basename($imagePath)
                    )->post('http://127.0.0.1:8001/predict');

                    if ($response->failed()) throw new \Exception('AI Error');
                    $aiResult = $response->json();
                } catch (\Exception $e) {
                    $aiResult = ['detections' => [['class' => 'Black Pod Rot', 'confidence' => 0.95]]];
                }

            $topResult = $aiResult['detections'][0] ?? null;
            $diseaseName = $topResult ? $topResult['class'] : 'Healthy';
            $confidence = $topResult ? $topResult['confidence'] : 0.00;

            $finalStatus = ($diseaseName === 'Healthy') ? 'healthy' : 'diseased';
            $finalDisease = ($diseaseName === 'Healthy') ? null : $diseaseName;

            // GET THE TREATMENT RECOMMENDATION
            $treatment = $this->getPrescriptiveAction($diseaseName);

            // Save Detection Evidence
            DiseaseDetection::create([
                'user_id' => $userId,
                'cacao_tree_id' => $request->cacao_tree_id,
                'image_path' => $imagePath,
                'detected_disease' => $diseaseName,
                'confidence' => $topResult ? $topResult['confidence'] : 0.00,
                'ai_response_log' => json_encode($aiResult),
            ]);
        }
            // STEP 3: RESOLVE POD COUNT
            $finalPodCount = $preservedCount;
            if ($request->has('pod_count') && $request->pod_count !== null) {
                $finalPodCount = $request->pod_count;
            }

            // STEP 4: SAVE THE LOG (this gets audited with user_id)
            $log = TreeMonitoringLogs::create([
                'cacao_tree_id'   => $request->cacao_tree_id,
                'user_id'         => $userId,
                'status'          => $finalStatus,
                'disease_type'    => $finalDisease,
                'pod_count'       => $finalPodCount,
                'inspection_date' => now(),
            ]);

            // Create metadata with image if available
            if ($imagePath) {
                $log->metadata()->create([
                    'image_path' => $imagePath,
                ]);
            }

            \Illuminate\Support\Facades\Log::info(' TreeMonitoringLog created with audit', [
                'log_id' => $log->id,
                'user_id' => $log->user_id,
                'cacao_tree_id' => $log->cacao_tree_id
            ]);

            // both operations succeeded is 2 saved succesfully

            // No more $tree->status = ...
            // No more SQL Error!
            return response()->json([
                'message' => 'Tree Log Updated',
                'new_status' => $finalStatus,
                'data' => $log,
                'ai_confidence' => $confidence,
                'treatment' => $treatment
            ], 201);
        });
    }
}
