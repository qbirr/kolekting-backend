<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KabupatenController;
use App\Http\Controllers\KecamatanController;
use App\Http\Controllers\KelurahanController;
use App\Http\Controllers\ProvinsiController;
use App\Http\Controllers\ArticleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/logout', [AuthController::class, 'logout']);
    Route::get('/my-downlines', [AuthController::class, 'myDownlines']);
    Route::get('/user-downlines', [AuthController::class, 'userDownlines']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);

    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles/update', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    Route::get('/count-users', [AuthController::class, 'countUsers']);

    Route::get('/filter-users', [AuthController::class, 'filterUser']);
    Route::get('/admins', [AuthController::class, 'getAdmins']);
    Route::post('/admins/add', [AuthController::class, 'addAdmin']);
    Route::post('/import-users', [AuthController::class, 'importUsers']);

    Route::post('/users/update', [AuthController::class, 'updateUser']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);

});

Route::post('/password/request', [AuthController::class, 'requestReset']);
Route::post('/password/verify', [AuthController::class, 'verifyOtp']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::get('provinsi', [ProvinsiController::class, 'index'])->name('provinsi.index');
Route::get('provinsi/{id}', [ProvinsiController::class, 'show'])->name('provinsi.show');

Route::get('kabupaten', [KabupatenController::class, 'index'])->name('kabupaten.index');
Route::get('kabupaten/{id}', [KabupatenController::class, 'show'])->name('kabupaten.show');

Route::get('kecamatan', [KecamatanController::class, 'index'])->name('kecamatan.index');
Route::get('kecamatan/{id}', [KecamatanController::class, 'show'])->name('kecamatan.show');

Route::get('kelurahan', [KelurahanController::class, 'index'])->name('kelurahan.index');
Route::get('kelurahan/{id}', [KelurahanController::class, 'show'])->name('kelurahan.show');
