<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to audit changes
    |
    */
    'driver' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Audit Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection where audits will be stored
    |
    */
    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Audit Table
    |--------------------------------------------------------------------------
    |
    | The table where audit records will be stored
    |
    */
    'table' => 'audits',

    /*
    |--------------------------------------------------------------------------
    | User Provider
    |--------------------------------------------------------------------------
    |
    | The provider used to resolve the authenticated user
    |
    */
    'user_provider' => 'auth',

    /*
    |--------------------------------------------------------------------------
    | User Resolver
    |--------------------------------------------------------------------------
    |
    | Callback to resolve the authenticated user
    | Supports both web and API (Sanctum) authentication
    |
    */
    'user_resolver' => function () {
        try {
            // Try API authentication first (for Sanctum tokens)
            if (auth('api')->check()) {
                return auth('api')->user();
            }
            // Fallback to web authentication
            if (auth('web')->check()) {
                return auth('web')->user();
            }
            // Generic auth check
            if (auth()->check()) {
                return auth()->user();
            }
        } catch (\Exception $e) {
            \Log::warning('Audit user resolver error: ' . $e->getMessage());
        }

        return null;
    },

    /*
    |--------------------------------------------------------------------------
    | Attributes to be removed from audit
    |--------------------------------------------------------------------------
    |
    | These attributes will be removed from audit records
    |
    */
    'attributes' => [
        'password',
        'password_confirmation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Events
    |--------------------------------------------------------------------------
    |
    | The events that should be audited
    |
    */
    'events' => ['created', 'updated', 'deleted', 'restored'],

    /*
    |--------------------------------------------------------------------------
    | Timestamps Format
    |--------------------------------------------------------------------------
    |
    | The format for timestamps in audit records
    |
    */
    'timestamps' => [
        'created_at' => 'Y-m-d H:i:s',
        'updated_at' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Use Morph Map
    |--------------------------------------------------------------------------
    |
    | Whether to use the morph map for model relationships
    |
    */
    'use_morph_map' => true,

    /*
    |--------------------------------------------------------------------------
    | Guard Names
    |--------------------------------------------------------------------------
    |
    | The guard names to use for audit resolution
    |
    */
    'guard_names' => ['api', 'web'],

    /*
    |--------------------------------------------------------------------------
    | Audit Strict Mode
    |--------------------------------------------------------------------------
    |
    | Whether to be strict about auditing
    |
    */
    'strict' => false,
];
