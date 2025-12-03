<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Models\Audit;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ FIX: Ensure user_id and user_type are always set in audits
        Audit::creating(function (Audit $audit) {
            try {
                Log::info('🔍 Audit::creating listener triggered', [
                    'auditable_type' => $audit->auditable_type,
                    'event' => $audit->event,
                    'current_user_id' => Auth::id()
                ]);

                // Get the authenticated user using Auth helper
                $user = null;

                // Try Auth::user() first (works with all guards)
                if (Auth::check()) {
                    $user = Auth::user();
                    Log::info('✅ User resolved via Auth::check()', ['user_id' => $user->id]);
                }
                // Try guard-specific checks
                elseif (auth('api')->check()) {
                    $user = auth('api')->user();
                    Log::info('✅ User resolved via api guard', ['user_id' => $user->id]);
                }
                elseif (auth('web')->check()) {
                    $user = auth('web')->user();
                    Log::info('✅ User resolved via web guard', ['user_id' => $user->id]);
                }

                if ($user) {
                    $audit->user_id = $user->id;
                    $audit->user_type = \App\Models\User::class; // Always use the User model class
                    Log::info('✅ Audit user_id and user_type set', [
                        'user_id' => $audit->user_id,
                        'user_type' => $audit->user_type,
                        'auditable_type' => $audit->auditable_type,
                        'event' => $audit->event
                    ]);
                } else {
                    Log::warning('⚠️ No authenticated user found for audit', [
                        'auditable_type' => $audit->auditable_type,
                        'event' => $audit->event,
                        'user_id_current' => Auth::id()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ Error resolving audit user: ' . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
        });
    }
}
