<?php

use App\Http\Controllers\Admin\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware(['auth', 'can:receive-push-notifications'])
    ->prefix('admin/push-subscriptions')
    ->name('admin.push-subscriptions.')
    ->group(function () {
        Route::post('/', [PushSubscriptionController::class, 'store'])->name('store');
        Route::delete('/', [PushSubscriptionController::class, 'destroy'])->name('destroy');
        Route::post('test', [PushSubscriptionController::class, 'test'])->name('test');
    });
