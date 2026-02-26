<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BillItemController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\ItemSplitController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\ReceiptController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::post('/auth/{provider}', [AuthController::class, 'login'])
    ->whereIn('provider', ['google', 'apple', 'telegram']);

// Dev login (only works when APP_ENV != production)
Route::post('/auth/dev-login', [AuthController::class, 'devLogin']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/settings', [AuthController::class, 'updateSettings']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Bills
    Route::post('/bills', [BillController::class, 'store']);
    Route::get('/bills', [BillController::class, 'index']);
    Route::get('/bills/{bill}', [BillController::class, 'show']);
    Route::delete('/bills/{bill}', [BillController::class, 'destroy']);

    // Participants
    Route::post('/bills/{bill}/participants', [ParticipantController::class, 'store']);
    Route::delete('/bills/{bill}/participants/{participant}', [ParticipantController::class, 'destroy']);

    // Bill Items
    Route::post('/bills/{bill}/items', [BillItemController::class, 'store']);
    Route::put('/bills/{bill}/items/{item}', [BillItemController::class, 'update']);
    Route::delete('/bills/{bill}/items/{item}', [BillItemController::class, 'destroy']);

    // Receipt Scanning
    Route::post('/bills/{bill}/scan-receipt', [ReceiptController::class, 'scanReceipt']);
    Route::post('/bills/{bill}/items/bulk', [ReceiptController::class, 'bulkStore']);

    // Item Splitting
    Route::post('/bills/{bill}/items/{item}/split', [ItemSplitController::class, 'split']);

    // Payment & Summary
    Route::put('/bills/{bill}/paid-by', [PaymentController::class, 'setPaidBy']);
    Route::get('/bills/{bill}/summary', [PaymentController::class, 'summary']);

    // PDF (supports ?token= query parameter for browser tab opening)
    Route::get('/bills/{bill}/pdf', [PdfController::class, 'generate'])
        ->middleware(\App\Http\Middleware\TokenFromQuery::class);
});
