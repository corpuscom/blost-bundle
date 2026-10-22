<?php

declare(strict_types=1);

use Corpuscom\Blost\Laravel\BlostController;
use Illuminate\Support\Facades\Route;

Route::get('/blost/v1/status', [BlostController::class, 'status'])->name('blost.status');
Route::put('/blost/v1/articles/{id}', [BlostController::class, 'publish'])->name('blost.publish');
