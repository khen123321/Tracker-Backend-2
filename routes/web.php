<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// =====================================================================
// THE "NUKE & PAVE" DATABASE ROUTE
// =====================================================================
Route::get('/run-secret-migrations-2026', function () {
    try {
        // 1. Wipe Laravel's memory so it sees the new PostgreSQL settings
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // 2. Wipe the database and rebuild it perfectly with your updated migration
        Artisan::call('migrate:fresh', ['--force' => true]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Cache cleared and Postgres tables built successfully!',
            'output' => Artisan::output()
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'CRITICAL ERROR: ' . $e->getMessage()
        ]);
    }
});