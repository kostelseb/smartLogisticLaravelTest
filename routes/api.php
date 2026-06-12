<?php

use App\Presentation\Controllers\Api\NotificationBatchController;
use App\Presentation\Controllers\Api\SubscriberNotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notification-batches', [NotificationBatchController::class, 'store']);
Route::get('/notification-batches/{notificationBatch}', [NotificationBatchController::class, 'show']);
Route::get('/subscribers/{subscriber}/notifications', [SubscriberNotificationController::class, 'index']);
