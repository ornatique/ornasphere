<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\OrderProductionController as Orders;

Route::get('orders', [Orders::class, 'index'])->name('orders.index');
Route::get('orders/create', [Orders::class, 'create'])->name('orders.create');
Route::post('orders', [Orders::class, 'store'])->name('orders.store');
Route::get('orders/{id}', [Orders::class, 'show'])->whereNumber('id')->name('orders.show');
Route::get('orders/{id}/edit', [Orders::class, 'edit'])->whereNumber('id')->name('orders.edit');
Route::put('orders/{id}', [Orders::class, 'store'])->whereNumber('id')->name('orders.draft.update');
Route::put('orders/{id}/status', [Orders::class, 'status'])->whereNumber('id')->name('orders.update');
Route::post('orders/{id}/vouchers', [Orders::class, 'linkVoucher'])->whereNumber('id')->name('orders.vouchers.update');
Route::post('orders/{id}/files', [Orders::class, 'upload'])->whereNumber('id')->name('orders.files.update');
Route::post('orders/{id}/cad-files', [Orders::class, 'upload'])->whereNumber('id')->name('cad.files.update');
Route::post('orders/{id}/rpt-files', [Orders::class, 'upload'])->whereNumber('id')->name('rpt.files.update');
Route::get('order-files/{id}', [Orders::class, 'download'])->whereNumber('id')->name('orders.files.show');
Route::get('cad-designs', [Orders::class, 'cadIndex'])->name('cad.index');
Route::post('order-lines/{lineId}/cad', [Orders::class, 'cadStore'])->whereNumber('lineId')->name('cad.store');
Route::get('cad-designs/{id}', [Orders::class, 'cadShow'])->whereNumber('id')->name('cad.show');
Route::put('cad-designs/{id}', [Orders::class, 'cadUpdate'])->whereNumber('id')->name('cad.update');
Route::get('rpt-batches', [Orders::class, 'rptIndex'])->name('rpt.index');
Route::post('cad-designs/{designId}/rpt', [Orders::class, 'rptStore'])->whereNumber('designId')->name('rpt.store');
Route::get('rpt-batches/{id}', [Orders::class, 'rptShow'])->whereNumber('id')->name('rpt.show');
Route::put('rpt-batches/{id}', [Orders::class, 'rptUpdate'])->whereNumber('id')->name('rpt.update');
