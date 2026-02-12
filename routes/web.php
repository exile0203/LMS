<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\MailController;

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




require __DIR__.'/settings.php';
