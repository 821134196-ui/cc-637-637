<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CitationController;
use App\Http\Controllers\DatasetAdminController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GrantController;
use Illuminate\Support\Facades\Route;

// --- Auth ---
Route::post('/login', [AuthController::class, 'login']);

// --- Public catalog (no restricted fields anywhere) ---
Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{dataset}', [CatalogController::class, 'show']);

// --- Public citation registry ---
Route::get('/citations', [CitationController::class, 'index']);

// --- Token-gated download (link itself is the credential) ---
Route::get('/download/{token}', [DownloadController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Researcher: applications
    Route::get('/datasets/{dataset}/fields', [ApplicationController::class, 'fields']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);

    // Researcher: approved scope + download links
    Route::get('/grants', [GrantController::class, 'index']);
    Route::post('/grants/{grant}/token', [GrantController::class, 'issueToken']);

    // Researcher: register published results
    Route::post('/citations', [CitationController::class, 'store']);

    // Approver only
    Route::middleware('approver')->prefix('admin')->group(function () {
        Route::get('/applications', [ApprovalController::class, 'pending']);
        Route::post('/applications/{application}/approve', [ApprovalController::class, 'approve']);
        Route::post('/applications/{application}/reject', [ApprovalController::class, 'reject']);
        Route::post('/grants/{grant}/revoke', [GrantController::class, 'revoke']);
        Route::get('/datasets', [DatasetAdminController::class, 'index']);
        Route::post('/datasets', [DatasetAdminController::class, 'store']);
        Route::post('/datasets/{dataset}/versions', [DatasetAdminController::class, 'addVersion']);
    });
});
