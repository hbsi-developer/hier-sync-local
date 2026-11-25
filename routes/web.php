<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\SyncSettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/devices');

Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
Route::get('/devices/{device}/status', [DeviceController::class, 'status'])->name('devices.status');
Route::post('/devices/{device}/test', [DeviceController::class, 'test'])->name('devices.test');
Route::post('/devices/{device}/sync-logs', [DeviceController::class, 'syncLogs'])->name('devices.sync-logs');
Route::post('/devices/{device}/sync-users', [DeviceController::class, 'syncUsers'])->name('devices.sync-users');
Route::post('/devices/{device}/sync-time', [DeviceController::class, 'syncTime'])->name('devices.sync-time');

Route::post('/auto-sync', [DeviceController::class, 'autoSyncSettings'])->name('auto-sync.settings');
Route::post('/devices/{device}/toggle-auto', [DeviceController::class, 'toggleAuto'])->name('devices.toggle-auto');

Route::post('/sdk/register', [DeviceController::class, 'registerSdk'])->name('sdk.register');

Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

Route::get('/settings', [SyncSettingsController::class, 'settings'])->name('settings.index');
Route::get('/payroll-sync', [SyncSettingsController::class, 'index'])->name('sync.index');
Route::post('/payroll-sync', [SyncSettingsController::class, 'save'])->name('sync.save');
Route::post('/payroll-sync/retry-rejected', [SyncSettingsController::class, 'retryRejected'])->name('sync.retry-rejected');

Route::get('/sync-logs', [SyncLogController::class, 'index'])->name('sync-logs.index');
Route::get('/sync-logs/export', [SyncLogController::class, 'export'])->name('sync-logs.export');

Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
