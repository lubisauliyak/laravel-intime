<?php

use App\Http\Controllers\MeetingReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/meeting/{meeting}/pdf', [MeetingReportController::class, 'pdf'])
    ->name('meeting.report.pdf')
    ->middleware(['auth', 'throttle:10,1']);

Route::middleware(['auth'])->group(function () {
    Route::get('/live-scanner/{meeting}', [\App\Http\Controllers\LiveScannerController::class, 'index'])->name('scanner.live');
    Route::post('/live-scanner/{meeting}/process', [\App\Http\Controllers\LiveScannerController::class, 'process'])
        ->name('scanner.process')
        ->middleware('throttle:60,1');
    Route::get('/live-scanner/{meeting}/search', [\App\Http\Controllers\LiveScannerController::class, 'search'])->name('scanner.search');
    Route::post('/live-scanner/{meeting}/manual', [\App\Http\Controllers\LiveScannerController::class, 'manualStore'])
        ->name('scanner.manual')
        ->middleware('throttle:30,1');
});
