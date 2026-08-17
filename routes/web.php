<?php

use App\Http\Controllers\Admin\AnalysisTypeAdminController;
use App\Http\Controllers\Admin\AssignmentAdminController;
use App\Http\Controllers\Admin\ControlNumberAdminController;
use App\Http\Controllers\Admin\FormTemplateAdminController;
use App\Http\Controllers\Admin\HistoryAccessAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AnalystController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware('throttle:20,1')->prefix('intake')->name('intake.')->group(function () {
    Route::get('/', [IntakeController::class, 'index'])->name('index');
    Route::post('/lookup', [IntakeController::class, 'lookup'])->name('lookup');
    Route::get('/create', [IntakeController::class, 'create'])->name('create');
    Route::post('/job-orders', [IntakeController::class, 'store'])->name('store');
    Route::get('/success/{jobOrder}', [IntakeController::class, 'success'])->name('success');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('history.access')->prefix('history')->name('history.')->group(function () {
        Route::get('/', [HistoryController::class, 'index'])->name('index');
        Route::get('/{jobOrder}', [HistoryController::class, 'show'])->name('show');
        Route::get('/{jobOrder}/print', [HistoryController::class, 'print'])->name('print');
        Route::get('/{jobOrder}/pdf', [HistoryController::class, 'pdf'])->name('pdf');
    });

    Route::middleware('role:receiving')->prefix('receiving')->name('receiving.')->group(function () {
        Route::get('/', [ReceivingController::class, 'index'])->name('index');
        Route::get('/{jobOrder}', [ReceivingController::class, 'show'])->name('show');
        Route::patch('/{jobOrder}/pricing', [ReceivingController::class, 'updatePricing'])->name('pricing');
        Route::post('/{jobOrder}/receive', [ReceivingController::class, 'receive'])->name('receive');
        Route::get('/{jobOrder}/print', [ReceivingController::class, 'print'])->name('print');
        Route::get('/{jobOrder}/pdf', [ReceivingController::class, 'pdf'])->name('pdf');
    });

    Route::middleware('role:analyst')->prefix('analyst')->name('analyst.')->group(function () {
        Route::get('/', [AnalystController::class, 'index'])->name('index');
        Route::post('/tasks/{analysis}/draft', [AnalystController::class, 'saveDraft'])->name('draft');
        Route::post('/tasks/{analysis}/complete', [AnalystController::class, 'complete'])->name('complete');
        Route::get('/tasks/{analysis}/pdf', [AnalystController::class, 'pdf'])->name('pdf');
    });

    Route::middleware('role:head_analysis')->prefix('head')->name('head.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::post('/sign-batch', [ReviewController::class, 'signBatch'])->name('sign-batch');
        Route::get('/{jobOrder}', [ReviewController::class, 'show'])->name('show');
        Route::post('/{jobOrder}/sign', [ReviewController::class, 'sign'])->name('sign');
        Route::post('/{jobOrder}/return', [ReviewController::class, 'returnAnalyses'])->name('return');
        Route::get('/{jobOrder}/print', [ReviewController::class, 'print'])->name('print');
        Route::get('/{jobOrder}/pdf', [ReviewController::class, 'pdf'])->name('pdf');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserAdminController::class, 'index'])->name('users');
        Route::post('/users', [UserAdminController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserAdminController::class, 'update'])->name('users.update');

        Route::get('/prices', [AnalysisTypeAdminController::class, 'index'])->name('prices');
        Route::post('/prices/categories', [AnalysisTypeAdminController::class, 'storeCategory'])->name('prices.categories.store');
        Route::patch('/prices/categories/{category}', [AnalysisTypeAdminController::class, 'updateCategory'])->name('prices.categories.update');
        Route::delete('/prices/categories/{category}', [AnalysisTypeAdminController::class, 'destroyCategory'])->name('prices.categories.destroy');
        Route::post('/prices', [AnalysisTypeAdminController::class, 'store'])->name('prices.store');
        Route::patch('/prices/{analysisType}', [AnalysisTypeAdminController::class, 'update'])->name('prices.update');

        Route::get('/assignments', [AssignmentAdminController::class, 'index'])->name('assignments');
        Route::put('/assignments/{user}', [AssignmentAdminController::class, 'update'])->name('assignments.update');

        Route::get('/history-access', [HistoryAccessAdminController::class, 'edit'])->name('history-access');
        Route::put('/history-access', [HistoryAccessAdminController::class, 'update'])->name('history-access.update');

        Route::get('/control-number', [ControlNumberAdminController::class, 'edit'])->name('control-number');
        Route::put('/control-number', [ControlNumberAdminController::class, 'update'])->name('control-number.update');

        Route::get('/form-templates', [FormTemplateAdminController::class, 'edit'])->name('form-templates');
        Route::post('/form-templates', [FormTemplateAdminController::class, 'update'])->name('form-templates.update');
        Route::post('/form-templates/regenerate', [FormTemplateAdminController::class, 'regenerate'])->name('form-templates.regenerate');
        Route::get('/form-templates/source', [FormTemplateAdminController::class, 'downloadSource'])->name('form-templates.source');
        Route::get('/form-templates/fillable', [FormTemplateAdminController::class, 'downloadFillable'])->name('form-templates.fillable');
        Route::get('/form-templates/sample', [FormTemplateAdminController::class, 'downloadSample'])->name('form-templates.sample');
        Route::get('/form-templates/calibration', [FormTemplateAdminController::class, 'downloadCalibration'])->name('form-templates.calibration');
    });
});

require __DIR__.'/settings.php';
