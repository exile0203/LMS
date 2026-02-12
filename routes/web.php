<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\GroupChatController;


use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('mail')->name('mail.')->group(function(){
    Route::get('/', [MailController::class, 'index'])->name('index');
});
Route::prefix('groupchat')->name('groupchat.')->group(function(){
    Route::get('/', [GroupChatController::class, 'index'])->name('index');
});
Route::prefix('quiz')->name('quiz.')->group(function(){
    Route::get('/', [QuizController::class, 'index'])->name('index');
});




require __DIR__.'/settings.php';
