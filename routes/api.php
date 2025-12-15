<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\CacaoTreeController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\HealthLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\GPredictionController;
use App\Http\Controllers\TreeLogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\HarvestController;
use App\Models\TreeMonitoringLogs;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdvisoryController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']); // Admin only - for Vue admin app
Route::post('/mobile/login', [AuthController::class, 'mobileLogin']); // Farmers + Admins - for Flutter mobile app

Route::get('/logs', [LogController::class, 'index']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('programs', [ProgramController::class, 'store']);
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('programs', [ProgramController::class, 'index']);
//     Route::get('programs/{id}', [ProgramController::class, 'show']);
//     Route::put('programs/{id}', [ProgramController::class, 'update']);
//     Route::delete('programs/{id}', [ProgramController::class, 'destroy']);
// });

Route::middleware('auth:sanctum')->group(function () {

    // Admin Dashboard
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

    // Advisory System
    Route::get('/admin/advisories/recommended', [AdvisoryController::class, 'getRecommendedAdvisories']);
    Route::post('/admin/advisories/send', [AdvisoryController::class, 'sendAdvisory']);

    // Admin - Users Management (only for admin)
    Route::get('/admin/users', [UserController::class, 'index']);

    // Dashboard & Inventory
    Route::get('/inventory/dashboard', [InventoryController::class, 'dashboard']);

    // We pass the Tree ID (e.g., 1) in the URL so we know which tree is being monitored
    Route::post('/trees/{tree}/logs', [TreeLogController::class, 'store']);


    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Farms
    Route::get('/farms', [FarmController::class, 'index']);
    Route::post('/farms', [FarmController::class, 'store']);
    Route::get('/farms/{farm}', [FarmController::class, 'show']);
    Route::put('/farms/{farm}', [FarmController::class, 'update']);
    Route::delete('/farms/{farm}', [FarmController::class, 'destroy']);

    // Cacao Trees
    Route::get('/farms/{farm}/cacao-trees', [CacaoTreeController::class, 'indexByFarm']);
    Route::post('/farms/{farm}/cacao-trees', [CacaoTreeController::class, 'store']);
    Route::get('/cacao-trees/{cacaoTree}', [CacaoTreeController::class, 'show']);
    Route::put('/cacao-trees/{cacaoTree}', [CacaoTreeController::class, 'update']);
    Route::delete('/cacao-trees/{cacaoTree}', [CacaoTreeController::class, 'destroy']);

    // Health logs
    // Route::post('/cacao-trees/{cacaoTree}/health-logs', [HealthLogController::class, 'store']);
    // Route::get('/cacao-trees/{cacaoTree}/health-logs', [HealthLogController::class, 'index']);

    // // Predictions
    // Route::post('/predict', [PredictionController::class, 'predict']);
    // Route::get('/farms/{farm}/predictions', [PredictionController::class, 'index']);




    //Gemini Prediction
    Route::post('/detect-disease', [GPredictionController::class, 'predict']);
    Route::post('/detect-disease', [GPredictionController::class, 'detectAndLog']);
    Route::post('/trees/{tree}/inventory', [TreeLogController::class, 'updateInventory']);

    //mapping of tree
    Route::post('/trees', [CacaoTreeController::class, 'store']); // Register a tree
    Route::get('/trees', [CacaoTreeController::class, 'index']);  // Get all trees (for map)
    Route::get('/trees/{cacaoTree}', [CacaoTreeController::class, 'show']); // Get specific tree details
    Route::put('/trees/{cacaoTree}', [CacaoTreeController::class, 'update']); // Update tree
    Route::delete('/trees/{cacaoTree}', [CacaoTreeController::class, 'destroy']); // Delete tree

    //update pods
    Route::put('/trees/{id}/pods', [App\Http\Controllers\CacaoTreeController::class, 'updatePods']);

    // Harvest Logs
    Route::post('/harvest', [HarvestController::class, 'store']); // Create a new harvest log
    Route::get('/harvest', [HarvestController::class, 'index']); // Get all user's harvest logs
    Route::get('/harvest/tree/{treeId}', [HarvestController::class, 'getByTree']); // Get harvest logs for specific tree
    Route::get('/harvest/forecast', [HarvestController::class, 'getForecastReport']); // Get harvest forecast report

    Route::get('/audits', [AuditLogController::class, 'index']);
    Route::get('/audits/model/{modelType}/{modelId}', [AuditLogController::class, 'getByModel']);
    Route::get('/audits/stats', [AuditLogController::class, 'getStats']);

    // Login history routes
    Route::get('/login-histories', [LoginHistoryController::class, 'myLoginHistories']);
    Route::get('/admin/login-histories', [LoginHistoryController::class, 'getAllLoginHistories']);





    // Route::post('/disease', [PredictionController::class, 'detectAndLog']);

    // // Weather
    // Route::get('/farms/{farm}/weather/fetch', [WeatherController::class, 'fetchWeather']);
    // Route::get('/farms/{farm}/weather/recent', [WeatherController::class, 'recent']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    // Debug routes
    Route::get('/debug/tree-latest-log', [\App\Http\Controllers\DebugController::class, 'testLatestLog']);
});
