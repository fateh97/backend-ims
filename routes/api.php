<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryLogController;
use App\Http\Controllers\UserController as ControllersUserController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\InventoryTypeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

Route::post('/login', [LoginController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/add-products', [ProductController::class, 'store']);
Route::get('/logs', [InventoryLogController::class, 'index']);
Route::post('/add-logs', [InventoryLogController::class, 'store']);
Route::put('/update-product/{id}', [ProductController::class, 'update']);
Route::delete('/delete-product/{id}', [ProductController::class, 'destroy']);
Route::get('/export-financial-report', [InventoryLogController::class, 'exportFinancialReport']);
Route::apiResource('users', ControllersUserController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('inventory-types', InventoryTypeController::class);

Route::post('/reset-password', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
        'password' => 'required|min:6|confirmed',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Email not found or passwords do not match.'], 422);
    }

    $user = User::where('email', $request->email)->first();
    $user->password = Hash::make($request->password);
    $user->save();

    return response()->json(['message' => 'Password has been reset successfully!']);
});
