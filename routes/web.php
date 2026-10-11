<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'auth/login')->name('home');

Route::inertia('dashboard', 'dashboard')->name('dashboard');
Route::inertia('teacher/class', 'teacher/class-view')->name('teacher.class');
Route::inertia('student/scan', 'student/scanner')->name('student.scan');
Route::inertia('student/permission', 'student/permission')->name('student.permission');

require __DIR__.'/settings.php';
