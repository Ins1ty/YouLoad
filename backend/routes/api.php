<?php

use App\Http\Controllers\Api\DownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['cors'])->group(function () {
    Route::get('/info', [DownloadController::class, 'info']);
    Route::post('/download', [DownloadController::class, 'download'])
        ->middleware('throttle:5,1');
});