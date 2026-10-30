<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn()=>redirect('/login.html'));
Route::get('/health', fn()=>redirect('/api/health'));
