<?php
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/animals', [ProductController::class, 'index'])->name('animals.index');
Route::get('/animals/create', [ProductController::class, 'create'])->name('animals.create');
Route::get('/animals/{id}/edit', [ProductController::class, 'edit'])->name('animals.edit');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
