<?php

namespace App\Http\Controllers;

use App\Models\PredictionLog;
use App\Models\Farm;
use App\Models\CacaoTree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class PredictionController extends Controller
{
    public function predict(Request $r)
    {
        $r->validate(['image' => 'required|image|max:8192', 'farm_id' => 'nullable|exists:farms,id', 'cacao_tree_id' => 'nullable|exists:cacao_trees,id']);
        $user = $r->user();
        $path = $r->file('image')->store('predictions', 'public');

        // send to ML service
        $mlUrl = env('ML_PREDICT_URL');
        $resp = Http::attach('image', file_get_contents(storage_path("app/public/{$path}")), basename($path))
            ->post($mlUrl);

        if (!$resp->successful()) {
            return response()->json(['message' => 'ML service error'], 500);
        }

        $json = $resp->json();
        // expected fields: disease, confidence, recommendation
        $disease = $json['disease'] ?? null;
        $confidence = isset($json['confidence']) ? floatval($json['confidence']) : null;
        $recommendation = $json['recommendation'] ?? null;

        $log = PredictionLog::create([
            'user_id' => $user->id,
            'farm_id' => $r->input('farm_id'),
            'cacao_tree_id' => $r->input('cacao_tree_id'),
            'image_path' => $path,
            'disease' => $disease,
            'confidence' => $confidence,
            'recommendation' => $recommendation,
            'model_response' => $json
        ]);

        return response()->json(['prediction' => $log], 201);
    }

    public function index(Farm $farm)
    {
        return response()->json($farm->predictionLogs()->with('user', 'cacaoTree')->latest()->paginate(20));
    }
}
