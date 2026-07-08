<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\AjbController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\ClientDocumentController;

// Public routes
Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
    });

    // Public tracking
    Route::get('track/{tracking_code}', [DocumentController::class, 'publicTrack']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('change-password', [AuthController::class, 'changePassword']);
        });

        // === ADMIN ROUTES ===
        Route::middleware('role:super-admin|notaris|staff')->prefix('admin')->group(function () {

            // Dashboard
            Route::prefix('dashboard')->group(function () {
                Route::get('stats', [DashboardController::class, 'stats']);
                Route::get('activity', [DashboardController::class, 'recentActivity']);
                Route::get('chart-data', [DashboardController::class, 'chartData']);
                Route::get('deadlines', [DashboardController::class, 'upcomingDeadlines']);
            });

            // Documents
            Route::prefix('documents')->group(function () {
                Route::get('/', [DocumentController::class, 'index']);
                Route::post('/', [DocumentController::class, 'store']);
                Route::get('/{id}', [DocumentController::class, 'show']);
                Route::put('/{id}', [DocumentController::class, 'update']);
                Route::delete('/{id}', [DocumentController::class, 'destroy']);
                Route::get('/{id}/timeline', [DocumentController::class, 'timeline']);
                Route::put('/{id}/stage', [DocumentController::class, 'updateStage']);
                Route::post('/{id}/notes', [DocumentController::class, 'addNote']);
                Route::post('/{id}/files', [DocumentController::class, 'uploadFile']);
                Route::delete('/{id}/files/{fileId}', [DocumentController::class, 'deleteFile']);
            });

            // AJB Cases
            Route::prefix('ajb')->group(function () {
                Route::get('/', [AjbController::class, 'index']);
                Route::post('/', [AjbController::class, 'store']);
                Route::get('/{id}', [AjbController::class, 'show']);
                Route::put('/{id}', [AjbController::class, 'update']);
                Route::put('/{id}/step/{stepNumber}', [AjbController::class, 'updateStep']);
                Route::post('/{id}/seller', [AjbController::class, 'addSeller']);
                Route::put('/{id}/seller/{sellerId}', [AjbController::class, 'updateSeller']);
                Route::post('/{id}/buyer', [AjbController::class, 'addBuyer']);
                Route::put('/{id}/buyer/{buyerId}', [AjbController::class, 'updateBuyer']);
                Route::post('/{id}/certificate', [AjbController::class, 'addCertificate']);
                Route::put('/{id}/certificate/{certId}', [AjbController::class, 'updateCertificate']);
                Route::post('/{id}/tax-payment', [AjbController::class, 'addTaxPayment']);
                Route::put('/{id}/tax-payment/{paymentId}', [AjbController::class, 'updateTaxPayment']);
                Route::post('/{id}/documents', [AjbController::class, 'uploadDocument']);
                Route::put('/{id}/bpn-submission', [AjbController::class, 'updateBpnSubmission']);
            });

            // Clients
            Route::prefix('clients')->group(function () {
                Route::get('/', [ClientController::class, 'index']);
                Route::post('/', [ClientController::class, 'store']);
                Route::get('/{id}', [ClientController::class, 'show']);
                Route::put('/{id}', [ClientController::class, 'update']);
                Route::delete('/{id}', [ClientController::class, 'destroy']);
                Route::get('/{id}/documents', [ClientController::class, 'documents']);
            });

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('documents', [ReportController::class, 'documents']);
                Route::get('ajb', [ReportController::class, 'ajb']);
                Route::get('clients', [ReportController::class, 'clients']);
                Route::get('export/pdf', [ReportController::class, 'exportPdf']);
                Route::get('export/excel', [ReportController::class, 'exportExcel']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::put('/{id}/read', [NotificationController::class, 'markRead']);
                Route::put('read-all', [NotificationController::class, 'markAllRead']);
                Route::get('templates', [NotificationController::class, 'templates']);
                Route::put('templates/{id}', [NotificationController::class, 'updateTemplate']);
            });

            // Users (Notaris only)
            Route::middleware('role:super-admin|notaris')->prefix('users')->group(function () {
                Route::get('/', [UserController::class, 'index']);
                Route::post('/', [UserController::class, 'store']);
                Route::get('/{id}', [UserController::class, 'show']);
                Route::put('/{id}', [UserController::class, 'update']);
                Route::delete('/{id}', [UserController::class, 'destroy']);
                Route::put('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            });

            // Settings (Super Admin & Notaris)
            Route::middleware('role:super-admin|notaris')->prefix('settings')->group(function () {
                Route::get('/', [SettingController::class, 'index']);
                Route::put('/', [SettingController::class, 'update']);
                Route::get('document-types', [SettingController::class, 'documentTypes']);
                Route::post('document-types', [SettingController::class, 'createDocumentType']);
                Route::put('document-types/{id}', [SettingController::class, 'updateDocumentType']);
            });

            // Activity Log
            Route::get('activity-logs', [DashboardController::class, 'activityLogs']);
        });

        // === CLIENT ROUTES ===
        Route::middleware('role:klien')->prefix('client')->group(function () {
            Route::get('dashboard', [ClientDashboardController::class, 'index']);
            Route::get('documents', [ClientDocumentController::class, 'index']);
            Route::get('documents/{id}', [ClientDocumentController::class, 'show']);
            Route::get('documents/{id}/download/{fileId}', [ClientDocumentController::class, 'downloadFile']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
        });
    });
});
