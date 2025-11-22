<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) { $perPage = 20; }
        if ($perPage > 100) { $perPage = 100; }

        $logs = DB::table('users_audit')
            ->select('audit_id', 'activitylogs', 'action', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($logs);
    }
}
