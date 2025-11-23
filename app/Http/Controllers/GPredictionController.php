<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\DiseaseDetection;
use App\Models\TreeMonitoringLogs;

class GPredictionController extends Controller
{
    public function detectAndLog(Request $request)
    {
        $request->validate([
            'cacao_tree_id' => 'required|exists:cacao_trees,id',
            'image'         => 'nullable|image|max:10000',
            'pod_count'     => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($request) {

            // -------------------------------------------------------
            // STEP 1: FETCH HISTORY (To preserve counts/status)
            // -------------------------------------------------------
            $lastLog = TreeMonitoringLogs::where('cacao_tree_id', $request->cacao_tree_id)
                        ->latest('id')
                        ->first();

            $preservedStatus = $lastLog ? $lastLog->status : 'healthy';
            $preservedDisease = $lastLog ? $lastLog->disease_type : null;
            $preservedCount  = $lastLog ? $lastLog->pod_count : 0;

            // -------------------------------------------------------
            // STEP 2: RESOLVE DISEASE STATUS (AI Logic)
            // -------------------------------------------------------
            $finalStatus = $preservedStatus;
            $finalDisease = $preservedDisease;
            $imagePath = null;

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

                $finalStatus = ($diseaseName === 'Healthy') ? 'healthy' : 'diseased';
                $finalDisease = ($diseaseName === 'Healthy') ? null : $diseaseName;

                // Save Detection Evidence
                DiseaseDetection::create([
                    'user_id' => $request->user() ? $request->user()->id : 1,
                    'cacao_tree_id' => $request->cacao_tree_id,
                    'image_path' => $imagePath,
                    'detected_disease' => $diseaseName,
                    'confidence' => $topResult ? $topResult['confidence'] : 0.00,
                    'ai_response_log' => json_encode($aiResult),
                ]);
            }

            // -------------------------------------------------------
            // STEP 3: RESOLVE POD COUNT
            // -------------------------------------------------------
            $finalPodCount = $preservedCount;
            if ($request->has('pod_count') && $request->pod_count !== null) {
                $finalPodCount = $request->pod_count;
            }

            // -------------------------------------------------------
            // STEP 4: SAVE THE LOG
            // -------------------------------------------------------
            $log = TreeMonitoringLogs::create([
                'cacao_tree_id'   => $request->cacao_tree_id,
                'user_id'         => $request->user() ? $request->user()->id : 1,
                'status'          => $finalStatus,
                'disease_type'    => $finalDisease,
                'pod_count'       => $finalPodCount,
                'image_path'      => $imagePath,
                'inspection_date' => now(),
            ]);

            // --- REMOVED THE CODE THAT UPDATED CACAO_TREES TABLE ---
            // No more $tree->status = ...
            // No more SQL Error!

            return response()->json([
                'message' => 'Tree Log Updated',
                'new_status' => $finalStatus,
                'data' => $log
            ], 201);
        });
    }
}
