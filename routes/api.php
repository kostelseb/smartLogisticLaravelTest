<?php

use App\Presentation\Controllers\Api\NotificationBatchController;
use App\Presentation\Controllers\Api\SubscriberNotificationsController;
use Illuminate\Support\Facades\Route;

Route::post('/notification-batches', [NotificationBatchController::class, 'store']);
Route::get('/notification-batches/{batch}', [NotificationBatchController::class, 'show']);
Route::get('/subscribers/{subscriber}/notifications', [SubscriberNotificationsController::class, 'index']);
