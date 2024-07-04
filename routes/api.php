<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AntrolBPJSController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });



Route::get('auth', [AntrolBPJSController::class, 'token'])->name('token');
// operasi
Route::post('jadwaloperasirs', [AntrolBPJSController::class, 'jadwaloperasirs'])->name('jadwaloperasirs');
Route::post('jadwaloperasipasien', [AntrolBPJSController::class, 'jadwaloperasipasien'])->name('jadwaloperasipasien');

// JKN
Route::post('ambilantrean', [AntrolBPJSController::class, 'ambilantrean'])->name('ambilantrean');
Route::post('checkinantrean', [AntrolBPJSController::class, 'checkinantrean'])->name('checkinantrean');
Route::post('batalantrean', [AntrolBPJSController::class, 'batalantrean'])->name('batalantrean');
Route::post('statusantrean', [AntrolBPJSController::class, 'statusantrean'])->name('statusantrean');
Route::post('sisaantrean', [AntrolBPJSController::class, 'sisaantrean'])->name('sisaantrean');
Route::post('pasienbaru', [AntrolBPJSController::class, 'pasienbaru'])->name('pasienbaru');






Route::post('x', [AntrolBPJSController::class, 'x'])->name('x');
