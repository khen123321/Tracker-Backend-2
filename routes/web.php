<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

// =====================================================================
// TEMPORARY SECURE MIGRATION & TEST ROUTE
// =====================================================================
Route::get('/run-secret-migrations-2026', function () {
    try {
        // Step 1: Explicitly test the database connection first
        DB::connection()->getPdo();

        // Step 2: If connection works, run the migrations
        Artisan::call('migrate', ['--force' => true]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Database tables built successfully! You can now log in.',
            'output' => Artisan::output()
        ]);

    } catch (\Throwable $e) {
        // \Throwable catches EVERYTHING, preventing a silent 500 crash
        return response()->json([
            'status'  => 'error',
            'message' => 'CRITICAL ERROR: ' . $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine()
        ]);
    }
});