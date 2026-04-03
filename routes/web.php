<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('pages::auth.login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:'.User::ROLE_ADMIN])->group(function () {
    Route::livewire('recipients', 'pages::recipients.index')->name('recipients.index');
    Route::livewire('users', 'pages::users.index')->name('users.index');
});

require __DIR__.'/settings.php';
