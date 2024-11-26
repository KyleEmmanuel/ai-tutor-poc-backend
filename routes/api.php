<?php

use App\Http\Controllers\AIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(AIController::class)->group(function() {
    Route::get('/ask_ai_stream', 'ask_ai_stream');
    Route::post('/ask_ai_no_thread', 'ask_ai_no_thread');
});