<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'auth/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware(['role:TEACHER,ADMIN'])->group(function () {
        Route::inertia('teacher/class', 'teacher/class-view')->name('teacher.class');
    });

    Route::middleware(['role:STUDENT'])->group(function () {
        Route::inertia('student/scan', 'student/scanner')->name('student.scan');
        Route::inertia('student/permission', 'student/permission')->name('student.permission');
    });
});

require __DIR__.'/settings.php';
