<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Admin\CitationController as AdminCitationController;
use App\Http\Controllers\Admin\DatasetController as AdminDatasetController;
use App\Http\Controllers\Admin\GrantController as AdminGrantController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CitationController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GrantController;
use App\Http\Controllers\SchemaController;
use Illuminate\Support\Facades\Route;

// ---- Public: auth + open catalog + token downloads -----------------------
Route::post('/login', [AuthController::class, 'login']);
Route::get('/datasets', [CatalogController::class, 'index']);
Route::get('/datasets/{dataset}', [CatalogController::class, 'show']);
Route::get('/datasets/{dataset}/preview', [CatalogController::class, 'preview']);
Route::get('/download/{token}', [DownloadController::class, 'download']);

// ---- Authenticated researchers -------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/versions/{version}/schema', [SchemaController::class, 'show']);

    Route::get('/requests', [AccessRequestController::class, 'index']);
    Route::post('/requests', [AccessRequestController::class, 'store']);

    Route::get('/grants', [GrantController::class, 'index']);
    Route::post('/grants/{grant}/download-links', [GrantController::class, 'createLink']);

    Route::get('/citations', [CitationController::class, 'index']);
    Route::post('/citations', [CitationController::class, 'store']);

    // ---- Admin -------------------------------------------------------------
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/requests', [AdminRequestController::class, 'index']);
        Route::post('/requests/{accessRequest}/approve', [AdminRequestController::class, 'approve']);
        Route::post('/requests/{accessRequest}/reject', [AdminRequestController::class, 'reject']);

        Route::get('/grants', [AdminGrantController::class, 'index']);
        Route::post('/grants/{grant}/revoke', [AdminGrantController::class, 'revoke']);

        Route::get('/citations', [AdminCitationController::class, 'index']);

        Route::post('/datasets', [AdminDatasetController::class, 'store']);
        Route::post('/datasets/{dataset}/versions', [AdminDatasetController::class, 'storeVersion']);
    });
});
