<?php

use App\Http\Controllers\MeetingReportController;
use App\Http\Controllers\SelfPermissionController;
use App\Http\Controllers\SelfPresenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::get('/meeting/{meeting}/pdf', [MeetingReportController::class, 'pdf'])
    ->name('meeting.report.pdf')
    ->middleware(['auth', 'throttle:10,1']);

// Self Permission (Publik — tanpa auth)
Route::get('/self-permission/{meeting}', [SelfPermissionController::class, 'index'])
    ->name('permission.self');
Route::get('/self-permission/{meeting}/lookup', [SelfPermissionController::class, 'lookupMember'])
    ->name('permission.self.lookup')
    ->middleware('throttle:30,1');
Route::post('/self-permission/{meeting}/store', [SelfPermissionController::class, 'store'])
    ->name('permission.self.store')
    ->middleware('throttle:10,1');
Route::post('/self-permission/{meeting}/register-temporary', [SelfPermissionController::class, 'registerTemporary'])
    ->name('permission.self.register-temporary')
    ->middleware('throttle:10,1');
Route::get('/self-permission/{meeting}/members', [SelfPermissionController::class, 'getMembersByGroup'])
    ->name('permission.self.members')
    ->middleware('throttle:30,1');
Route::post('/self-permission/{meeting}/complete-biodata', [SelfPermissionController::class, 'completeBiodata'])
    ->name('permission.self.complete-biodata')
    ->middleware('throttle:10,1');

// Self Presence (Publik — tanpa auth)
Route::get('/self-presence/{meeting}', [SelfPresenceController::class, 'index'])
    ->name('presence.self');
Route::get('/self-presence/{meeting}/lookup', [SelfPresenceController::class, 'lookupMember'])
    ->name('presence.self.lookup')
    ->middleware('throttle:30,1');
Route::post('/self-presence/{meeting}/store', [SelfPresenceController::class, 'store'])
    ->name('presence.self.store')
    ->middleware('throttle:10,1');
Route::post('/self-presence/{meeting}/complete-biodata', [SelfPresenceController::class, 'completeBiodata'])
    ->name('presence.self.complete-biodata')
    ->middleware('throttle:10,1');
Route::get('/self-presence/{meeting}/members', [SelfPresenceController::class, 'getMembersByGroup'])
    ->name('presence.self.members')
    ->middleware('throttle:30,1');
Route::post('/self-presence/{meeting}/register-temporary', [SelfPresenceController::class, 'registerTemporary'])
    ->name('presence.self.register-temporary')
    ->middleware('throttle:10,1');

Route::middleware(['auth'])->group(function () {
    Route::get('/live-scanner/{meeting}', [\App\Http\Controllers\LiveScannerController::class, 'index'])->name('scanner.live');
    Route::post('/live-scanner/{meeting}/process', [\App\Http\Controllers\LiveScannerController::class, 'process'])
        ->name('scanner.process')
        ->middleware('throttle:60,1');
    Route::get('/live-scanner/{meeting}/search', [\App\Http\Controllers\LiveScannerController::class, 'search'])
        ->name('scanner.search')
        ->middleware('throttle:60,1');
    Route::post('/live-scanner/{meeting}/manual', [\App\Http\Controllers\LiveScannerController::class, 'manualStore'])
        ->name('scanner.manual')
        ->middleware('throttle:30,1');
    Route::post('/live-scanner/{meeting}/register-temporary', [\App\Http\Controllers\LiveScannerController::class, 'registerTemporary'])
        ->name('scanner.register-temporary')
        ->middleware('throttle:30,1');

    Route::get('/card-editor/{template}', [\App\Http\Controllers\CardEditorController::class, 'index'])->name('card-editor.index');
    Route::post('/card-editor/{template}/upload-background', [\App\Http\Controllers\CardEditorController::class, 'uploadBackground'])->name('card-editor.upload-background');
    Route::post('/card-editor/{template}/upload-font', [\App\Http\Controllers\CardEditorController::class, 'uploadFont'])->name('card-editor.upload-font');
    Route::post('/card-editor/{template}/save', [\App\Http\Controllers\CardEditorController::class, 'save'])->name('card-editor.save');
    Route::post('/card-editor/{template}/preview', [\App\Http\Controllers\CardEditorController::class, 'previewCard'])->name('card-editor.preview');
});
