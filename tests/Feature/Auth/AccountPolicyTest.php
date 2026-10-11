<?php

use Illuminate\Support\Facades\Route;

test('public registration is disabled', function () {
    expect(Route::has('register'))->toBeFalse();
});

test('self service password reset is disabled', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('password.update'))->toBeFalse();
});

test('login is still available', function () {
    expect(Route::has('login'))->toBeTrue();
});

test('application name is SiAbsen', function () {
    expect(config('app.name'))->toBe('SiAbsen');
});
