<?php

use App\Http\Controllers\Api\V1\TodoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('todos', TodoController::class);
});
