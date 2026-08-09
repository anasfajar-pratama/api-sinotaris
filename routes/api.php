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
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\OrderController;
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
                Route::get('/', [DocumentController::class, 'index'])->middleware('permission:documents.view');
                Route::post('/', [DocumentController::class, 'store'])->middleware('permission:documents.create');
                Route::get('/{id}', [DocumentController::class, 'show'])->middleware('permission:documents.view');
                Route::put('/{id}', [DocumentController::class, 'update'])->middleware('permission:documents.edit');
                Route::delete('/{id}', [DocumentController::class, 'destroy'])->middleware('permission:documents.delete');
                Route::get('/{id}/timeline', [DocumentController::class, 'timeline'])->middleware('permission:documents.view');
                Route::put('/{id}/stage', [DocumentController::class, 'updateStage'])->middleware('permission:documents.approve');
                Route::post('/{id}/notes', [DocumentController::class, 'addNote'])->middleware('permission:documents.edit');
                Route::post('/{id}/files', [DocumentController::class, 'uploadFile'])->middleware('permission:documents.edit');
                Route::delete('/{id}/files/{fileId}', [DocumentController::class, 'deleteFile'])->middleware('permission:documents.edit');
            });

            // AJB Cases
            Route::prefix('ajb')->group(function () {
                Route::get('/', [AjbController::class, 'index'])->middleware('permission:ajb.view');
                Route::post('/', [AjbController::class, 'store'])->middleware('permission:ajb.create');
                Route::get('/{id}', [AjbController::class, 'show'])->middleware('permission:ajb.view');
                Route::put('/{id}', [AjbController::class, 'update'])->middleware('permission:ajb.edit');
                Route::put('/{id}/step/{stepNumber}', [AjbController::class, 'updateStep'])->middleware('permission:ajb.edit');
                Route::post('/{id}/seller', [AjbController::class, 'addSeller'])->middleware('permission:ajb.edit');
                Route::put('/{id}/seller/{sellerId}', [AjbController::class, 'updateSeller'])->middleware('permission:ajb.edit');
                Route::post('/{id}/buyer', [AjbController::class, 'addBuyer'])->middleware('permission:ajb.edit');
                Route::put('/{id}/buyer/{buyerId}', [AjbController::class, 'updateBuyer'])->middleware('permission:ajb.edit');
                Route::post('/{id}/certificate', [AjbController::class, 'addCertificate'])->middleware('permission:ajb.edit');
                Route::put('/{id}/certificate/{certId}', [AjbController::class, 'updateCertificate'])->middleware('permission:ajb.edit');
                Route::post('/{id}/tax-payment', [AjbController::class, 'addTaxPayment'])->middleware('permission:ajb.edit');
                Route::put('/{id}/tax-payment/{paymentId}', [AjbController::class, 'updateTaxPayment'])->middleware('permission:ajb.edit');
                Route::post('/{id}/documents', [AjbController::class, 'uploadDocument'])->middleware('permission:ajb.edit');
                Route::put('/{id}/bpn-submission', [AjbController::class, 'updateBpnSubmission'])->middleware('permission:ajb.edit');
            });

            // Clients
            Route::prefix('clients')->group(function () {
                Route::get('/', [ClientController::class, 'index'])->middleware('permission:clients.view');
                Route::post('/', [ClientController::class, 'store'])->middleware('permission:clients.create');
                Route::get('/{id}', [ClientController::class, 'show'])->middleware('permission:clients.view');
                Route::put('/{id}', [ClientController::class, 'update'])->middleware('permission:clients.edit');
                Route::delete('/{id}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete');
                Route::get('/{id}/documents', [ClientController::class, 'documents'])->middleware('permission:clients.view');
            });

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('documents', [ReportController::class, 'documents'])->middleware('permission:reports.view');
                Route::get('ajb', [ReportController::class, 'ajb'])->middleware('permission:reports.view');
                Route::get('clients', [ReportController::class, 'clients'])->middleware('permission:reports.view');
                Route::get('export/pdf', [ReportController::class, 'exportPdf'])->middleware('permission:reports.export');
                Route::get('export/excel', [ReportController::class, 'exportExcel'])->middleware('permission:reports.export');
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::put('/{id}/read', [NotificationController::class, 'markRead']);
                Route::put('read-all', [NotificationController::class, 'markAllRead']);
                Route::get('templates', [NotificationController::class, 'templates'])->middleware('permission:notifications.manage');
                Route::put('templates/{id}', [NotificationController::class, 'updateTemplate'])->middleware('permission:notifications.manage');
            });

            // Users (Notaris & Super Admin)
            Route::middleware('role:super-admin|notaris')->prefix('users')->group(function () {
                Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
                Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
                Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
                Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.edit');
                Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
                Route::put('/{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit');
            });

            // Settings (Super Admin & Notaris)
            Route::middleware('role:super-admin|notaris')->prefix('settings')->group(function () {
                Route::get('/', [SettingController::class, 'index'])->middleware('permission:settings.view');
                Route::put('/', [SettingController::class, 'update'])->middleware('permission:settings.edit');
                Route::get('document-types', [SettingController::class, 'documentTypes'])->middleware('permission:documents.view');
                Route::post('document-types', [SettingController::class, 'createDocumentType'])->middleware('permission:settings.edit');
                Route::put('document-types/{id}', [SettingController::class, 'updateDocumentType'])->middleware('permission:settings.edit');
                Route::get('order-mapping', [SettingController::class, 'orderMapping'])->middleware('permission:settings.view');
                Route::put('order-mapping/{typeId}', [SettingController::class, 'syncOrderMapping'])->middleware('permission:settings.edit');
            });

            // Order actor/asset API (dynamic order detail)
            Route::prefix('orders')->group(function () {
                Route::get('template/{document_type_id}', [OrderController::class, 'template'])->middleware('permission:documents.view');

                Route::post('/{documentId}/actors', [OrderController::class, 'storeActor'])->middleware('permission:documents.edit');
                Route::put('/{documentId}/actors/{actorId}', [OrderController::class, 'updateActor'])->middleware('permission:documents.edit');
                Route::delete('/{documentId}/actors/{actorId}', [OrderController::class, 'destroyActor'])->middleware('permission:documents.edit');
                Route::post('/{documentId}/actors/{actorId}/documents', [OrderController::class, 'uploadActorDocument'])->middleware('permission:documents.edit');
                Route::delete('/{documentId}/actors/{actorId}/documents/{fileId}', [OrderController::class, 'destroyActorDocument'])->middleware('permission:documents.edit');

                Route::post('/{documentId}/assets', [OrderController::class, 'storeAsset'])->middleware('permission:documents.edit');
                Route::put('/{documentId}/assets/{assetId}', [OrderController::class, 'updateAsset'])->middleware('permission:documents.edit');
                Route::delete('/{documentId}/assets/{assetId}', [OrderController::class, 'destroyAsset'])->middleware('permission:documents.edit');
                Route::post('/{documentId}/assets/{assetId}/documents', [OrderController::class, 'uploadAssetDocument'])->middleware('permission:documents.edit');
                Route::delete('/{documentId}/assets/{assetId}/documents/{fileId}', [OrderController::class, 'destroyAssetDocument'])->middleware('permission:documents.edit');

                Route::post('/{documentId}/documents', [OrderController::class, 'storeOrderDocument'])->middleware('permission:documents.edit');
                Route::delete('/{documentId}/documents/{fileId}', [OrderController::class, 'destroyOrderDocument'])->middleware('permission:documents.edit');
            });

            // RBAC (Super Admin only)
            Route::middleware('role:super-admin')->prefix('rbac')->group(function () {
                Route::get('roles', [RoleController::class, 'index']);
                Route::get('permissions', [RoleController::class, 'permissions']);
                Route::post('roles', [RoleController::class, 'store']);
                Route::put('roles/{id}', [RoleController::class, 'update']);
                Route::put('roles/{id}/permissions', [RoleController::class, 'syncPermissions']);
                Route::delete('roles/{id}', [RoleController::class, 'destroy']);
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
