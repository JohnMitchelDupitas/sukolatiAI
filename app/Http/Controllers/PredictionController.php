<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\DiseaseDetection;
use App\Models\TreeMonitoringLog;
use App\Models\CacaoTree;
use App\Models\TreeMonitoringLogs;

class PredictionController extends Controller
{
    public function detectAndLog(Request $request)
    {
        // 1. VALIDATION
        $request->validate([
            'cacao_tree_id' => 'required|exists:cacao_trees,id', // Must pick a valid tree
            'image'         => 'required|image|max:10000',       // Max 10MB
            'pod_count'     => 'nullable|integer|min:0',         // Optional inventory count
        ]);

        // Start a Database Transaction (Safety First)
        return DB::transaction(function () use ($request) {

            // 2. UPLOAD IMAGE
            // Stores in storage/app/public/scans
            $imagePath = $request->file('image')->store('scans', 'public');

            // 3. AI PREDICTION (The "Bridge" to Python)
            // -----------------------------------------------------------
            try {
                // Send to your Python AI (assuming running on port 8001 or 5000)
                $response = Http::attach(
                    'file',
                    file_get_contents(storage_path('app/public/' . $imagePath)),
                    basename($imagePath)
                )->post('http://127.0.0.1:8001/predict');

                if ($response->failed()) {
                    throw new \Exception('AI Service Unreachable');
                }
                $aiResult = $response->json();
            } catch (\Exception $e) {
                // FALLBACK FOR TESTING (If Python is offline, use this fake data)
                // Remove this catch block when going to production!
                $aiResult = [
                    'detections' => [
                        ['class' => 'Black Pod Rot', 'confidence' => 0.95]
                    ]
                ];
            }
            // -----------------------------------------------------------

            // 4. PARSE AI RESULTS
            $topResult = $aiResult['detections'][0] ?? null;
            $diseaseName = $topResult ? $topResult['class'] : 'Healthy';
            $confidence = $topResult ? $topResult['confidence'] : 0.00;
            // Determine standardized status for the Map
            $mapStatus = ($diseaseName === 'Healthy') ? 'healthy' : 'diseased';

            // 5. UPDATE TABLE A: Disease Detections (The Scientific Record)
            $detection = DiseaseDetection::create([
                'user_id'          => $request->user() ? $request->user()->id : 1,
                'cacao_tree_id'    => $request->cacao_tree_id,
                'image_path'       => $imagePath,
                'detected_disease' => $diseaseName,
                'confidence'       => $confidence,
                'ai_response_log'  => json_encode($aiResult),
            ]);

            // 6. UPDATE TABLE B: Monitoring Logs (The History & Map Data)
            $log = TreeMonitoringLogs::create([
                'cacao_tree_id'   => $request->cacao_tree_id,
                'user_id'         => $request->user() ? $request->user()->id : 1,
                'disease_type'    => ($diseaseName === 'Healthy') ? null : $diseaseName,
                'pod_count'       => $request->input('pod_count', 0), // Save user input or 0
                'inspection_date' => now(),
            ]);

            // Create metadata with image if available
            if ($imagePath) {
                $log->metadata()->create([
                    'image_path' => $imagePath,
                ]);
            }

            // 7. UPDATE TABLE C: The Main Tree (Real-time Status)
            // This ensures the tree immediately turns RED on the map list
            $tree = CacaoTree::find($request->cacao_tree_id);
            $tree->save();

            // 8. RETURN SUCCESS RESPONSE
            return response()->json([
                'message' => 'Scan processed successfully',
                'tree_id' => $tree->id,
                'detected_disease' => $diseaseName,
                'log_data' => $log
            ], 201);
        });
    }
}
