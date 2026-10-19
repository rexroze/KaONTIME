<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/hello', [ApiController::class, 'hello']);
Route::get('/health', [ApiController::class, 'health']);
Route::get('/menu', [ApiController::class, 'menu']);
Route::post('/promo/validate', [ApiController::class, 'validatePromo']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/orders', [CustomerController::class, 'orderIndex']);
    Route::post('/orders', [CustomerController::class, 'orderCreate'])->middleware('throttle:orders');
    Route::get('/orders/{orderNumber}', [CustomerController::class, 'orderShow']);
    Route::patch('/orders/{orderNumber}/cancel', [CustomerController::class, 'orderCancel']);
    Route::get('/favorites', [CustomerController::class, 'favorites']);
    Route::put('/favorites/{productId}', [CustomerController::class, 'favoriteAdd']);
    Route::delete('/favorites/{productId}', [CustomerController::class, 'favoriteRemove']);
    Route::get('/addresses', [CustomerController::class, 'addresses']);
    Route::post('/addresses', [CustomerController::class, 'addressCreate']);
    Route::put('/addresses/{id}', [CustomerController::class, 'addressUpdate']);
    Route::delete('/addresses/{id}', [CustomerController::class, 'addressDelete']);
    Route::prefix('admin')->middleware('admin')->group(function (): void {
        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'productCreate']);
        Route::put('/products/{id}', [AdminController::class, 'productUpdate']);
        Route::delete('/products/{id}', [AdminController::class, 'productDelete']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::patch('/orders/{orderNumber}/status', [AdminController::class, 'status']);
        Route::get('/reports', [AdminController::class, 'reports']);
    });
});
