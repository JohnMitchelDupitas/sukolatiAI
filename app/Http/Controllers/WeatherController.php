<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\WeatherLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function fetchWeather(Farm $farm)
    {
        if (!$farm->latitude || !$farm->longitude) {
            return response()->json(['message' => 'Farm coordinates required'], 422);
        }
        $key = env('OPENWEATHER_KEY');
        $resp = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'lat' => $farm->latitude,
            'lon' => $farm->longitude,
            'units' => 'metric',
            'appid' => $key
        ]);
        if (!$resp->successful()) return response()->json(['message' => 'Weather fetch failed'], 500);
        $d = $resp->json();
        $temp = $d['main']['temp'] ?? null;
        $humidity = $d['main']['humidity'] ?? null;
        $rain = $d['rain']['1h'] ?? ($d['rain']['3h'] ?? 0);
        $wind = $d['wind']['speed'] ?? null;
        $clouds = $d['clouds']['all'] ?? null;
        $log = WeatherLog::create([
            'farm_id' => $farm->id,
            'temperature' => $temp,
            'humidity' => $humidity,
            'rainfall' => $rain,
            'wind_speed' => $wind,
            'cloudiness' => $clouds,
            'raw' => $d,
            'recorded_at' => now()
        ]);
        return response()->json($log);
    }

    public function recent(Farm $farm)
    {
        return response()->json($farm->weatherLogs()->latest()->take(24)->get());
    }
}
