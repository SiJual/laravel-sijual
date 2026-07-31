<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Dashboard\HubController;
use App\Http\Controllers\Profile\OutletController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

// === GUEST ROUTES ===
Route::middleware('guest')->group(function () {
    Route::get('/', fn() => redirect()->route('login'));
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store']);
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// === AUTHENTICATED ROUTES ===
Route::middleware(['auth.supabase'])->group(function () {

    // Onboarding
    Route::get('/onboarding', [ProfileController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [ProfileController::class, 'completeOnboarding']);

    // Protected routes (profile must be complete)
    Route::middleware(['profile.complete'])->group(function () {

        // SiJual Hub (Dashboard)
        Route::get('/dashboard', [HubController::class, 'index'])->name('dashboard');

        // SiKas Routes
        Route::prefix('sikas')->name('sikas.')->group(function () {
            Route::get('/', [App\Http\Controllers\SiKas\DashboardController::class, 'index'])->name('dashboard');
            Route::resource('transactions', App\Http\Controllers\SiKas\TransactionController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::get('/reports', [App\Http\Controllers\SiKas\ReportController::class, 'index'])->name('reports');
            Route::post('/reports/export', [App\Http\Controllers\SiKas\ReportController::class, 'export'])->name('reports.export');
            Route::post('/voice-input', [App\Http\Controllers\SiKas\VoiceInputController::class, 'process'])->name('voice')->middleware('throttle:10,1');
            Route::post('/qris-sync', [App\Http\Controllers\SiKas\QrisSyncController::class, 'sync'])->name('qris.sync');
        });

        // SiPasar Routes
        Route::prefix('sipasar')->name('sipasar.')->group(function () {
            Route::get('/', [App\Http\Controllers\SiPasar\AnalysisController::class, 'index'])->name('landing');
            Route::get('/history', [App\Http\Controllers\SiPasar\AnalysisController::class, 'history'])->name('history');
            Route::post('/analyze', [App\Http\Controllers\SiPasar\AnalysisController::class, 'analyze'])->name('analyze')->middleware('throttle:10,1');
            Route::get('/results/{analysis}', [App\Http\Controllers\SiPasar\AnalysisController::class, 'results'])->name('results');
            Route::get('/competitors/{analysis}', [App\Http\Controllers\SiPasar\CompetitorController::class, 'index'])->name('competitors');
            Route::get('/demographics/{analysis}', [App\Http\Controllers\SiPasar\DemographicController::class, 'index'])->name('demographics');
        });

        // SiPromo Routes
        Route::prefix('sipromo')->name('sipromo.')->group(function () {
            Route::get('/', [App\Http\Controllers\SiPromo\ContentController::class, 'index'])->name('landing');
            Route::get('/preview/{content}', [App\Http\Controllers\SiPromo\ContentController::class, 'preview'])->name('preview');
            Route::get('/history', [App\Http\Controllers\SiPromo\ContentController::class, 'history'])->name('history');
            Route::post('/generate', [App\Http\Controllers\SiPromo\GenerateController::class, 'create'])->name('generate.create')->middleware('throttle:10,1');
        });

        // SiStok Routes
        Route::prefix('sistok')->name('sistok.')->group(function () {
            Route::get('/', [App\Http\Controllers\SiStok\ProductController::class, 'index'])->name('products.index');
            Route::post('/products', [App\Http\Controllers\SiStok\ProductController::class, 'store'])->name('products.store');
            Route::put('/products/{product}', [App\Http\Controllers\SiStok\ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [App\Http\Controllers\SiStok\ProductController::class, 'destroy'])->name('products.destroy');
        });

        // Copilot AI Assistant
        Route::post('/copilot/ask', [App\Http\Controllers\Copilot\CopilotController::class, 'ask'])->name('copilot.ask');

        // Profile & Outlets
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::resource('outlets', OutletController::class)->only(['store', 'destroy']);
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

