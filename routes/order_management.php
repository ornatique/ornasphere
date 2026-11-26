<?php

use App\Http\Controllers\Company\OrderManagementController as Orders;
use Illuminate\Support\Facades\Route;

Route::prefix('order-management')->name('order-management.')->group(function () {
    Route::get('/', [Orders::class, 'index'])->name('index');
    Route::get('/create', [Orders::class, 'create'])->name('create');
    Route::get('/items/create', [Orders::class, 'search'])->name('items.create');
    Route::get('/items/edit', [Orders::class, 'search'])->name('items.edit');
    Route::post('/', [Orders::class, 'store'])->name('store');
    Route::get('/{id}/pdf', [Orders::class, 'pdf'])->whereNumber('id')->name('pdf');
    Route::get('/{id}/edit', [Orders::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('/{id}', [Orders::class, 'store'])->whereNumber('id')->name('update');
});
