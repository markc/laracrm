<?php

use App\Http\Controllers\InvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)
    ->name('invoices.pdf')
    ->middleware('auth');
