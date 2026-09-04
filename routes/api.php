<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is working!',
    ]);
});

// Route::post('/auth/register', [AuthController::class, 'register']);
// Route::post('/auth/login', [AuthController::class, 'login']);
// Route::post('/auth/google', [AuthController::class, 'googleLogin']);

// Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::prefix('v1')->group(function () {
    Route::apiResource('category', CategoryController::class)->parameters(['category' => 'slug']);

    Route::apiResource('product', ProductController::class);

    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('items', [CartController::class, 'addItem']);
        Route::put('items/{productId}', [CartController::class, 'updateItem']);
        Route::delete('items/{productId}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
    });

});
