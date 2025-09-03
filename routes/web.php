<?php

use App\Mail\RegisteredMemberMail;
use App\Models\RegisteredMember;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/preview-email', function () {
    // Fake a registered member (use an actual one in DB or a mock)
    $registeredMember = RegisteredMember::first() ?? new RegisteredMember([
        'name' => 'Sample Member',
        'email' => 'sample@example.com',
        'reference_number' => 'AGMA2025-ABC123'
    ]);

    return (new RegisteredMemberMail($registeredMember))->render();
});