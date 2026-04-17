<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyUserController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\LabelConfigController;
use App\Http\Controllers\Api\ItemSetController;
use App\Http\Controllers\Api\OtherChargeController;
use App\Http\Controllers\Api\SaleApiController;
use App\Http\Controllers\Api\SaleReturnApiController;
use App\Http\Controllers\Api\ApprovalApiController;
use App\Http\Controllers\Api\CustomerApiController;

Route::post('/company/login', [AuthController::class, 'login']);
Route::post('/company/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'company.active'])->group(function () {
    Route::post('/company/logout', [AuthController::class, 'logout']);
    Route::get('/company/me', [AuthController::class, 'me']);
});




Route::middleware(['auth:sanctum', 'company.active'])->group(function () {

    Route::get('/users', [CompanyUserController::class, 'index']);
    Route::post('/create_users', [CompanyUserController::class, 'store']);
    Route::put('/update_users/{id}', [CompanyUserController::class, 'update']);
    Route::post('/users/{id}/reset-2fa', [CompanyUserController::class, 'reset2fa']);
    Route::post('/reset2fa_users/{id}', [CompanyUserController::class, 'reset2fa']);
    Route::delete('/delete_users/{id}', [CompanyUserController::class, 'destroy']);

    Route::get('/customers', [CustomerApiController::class, 'index']);
    Route::post('/customers', [CustomerApiController::class, 'store']);
    Route::get('/customers/{id}', [CustomerApiController::class, 'show']);
    Route::put('/customers/{id}', [CustomerApiController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerApiController::class, 'destroy']);
    Route::get('/customers_list', [CustomerApiController::class, 'index']);
    Route::post('/create_customers', [CustomerApiController::class, 'store']);
    Route::post('/update_customers/{id}', [CustomerApiController::class, 'update']);
    Route::delete('/delete_customers/{id}', [CustomerApiController::class, 'destroy']);
    
    
    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/create_items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::put('/update_items/{id}', [ItemController::class, 'update']);
    Route::delete('/delete_items/{id}', [ItemController::class, 'destroy']);
    
    Route::get('/label-configs', [LabelConfigController::class, 'index']);
    Route::post('/create-label-configs', [LabelConfigController::class, 'store']);
    Route::get('/label-configs/{id}', [LabelConfigController::class, 'show']);
    Route::put('/update-label-configs/{id}', [LabelConfigController::class, 'update']);
    Route::delete('/label-configs/{id}', [LabelConfigController::class, 'destroy']);
    
    Route::post('/item-sets', [ItemSetController::class, 'index']);
    Route::post('/item-sets/save-cell', [ItemSetController::class, 'saveCell']);
    Route::post('/item-sets/bulk-save', [ItemSetController::class, 'bulkSave']);
    Route::post('/item-sets/finalize', [ItemSetController::class, 'finalize']);
    Route::get('/item-sets/qr-list', [ItemSetController::class, 'listset_data']);
    
    Route::get('itemsets_list/', [ItemSetController::class, 'listset_data']);     // list + filter
    Route::get('itemsets_show/{id}', [ItemSetController::class, 'show']);  // edit data
    Route::post('itemsets_update/{id}', [ItemSetController::class, 'update']); // update
    Route::delete('itemsets_delete/{id}', [ItemSetController::class, 'destroy']); // delete
    
    Route::get('/other-charges', [OtherChargeController::class, 'index']);
    Route::get('/other-charges/options', [OtherChargeController::class, 'options']);
    Route::post('/other-charges/calculate', [OtherChargeController::class, 'calculate']);
    Route::post('/add-other-charges', [OtherChargeController::class, 'store']);
    Route::get('/other-charges/{id}', [OtherChargeController::class, 'show']);
    Route::put('/update-other-charges/{id}', [OtherChargeController::class, 'update']);
    Route::delete('/delete-other-charges/{id}', [OtherChargeController::class, 'destroy']);
    
    Route::get('/sale-list', [SaleApiController::class, 'index']);             // List sales
    Route::post('/sales/get-item-by-qr', [SaleApiController::class, 'getItemByQr']);
    Route::get('/sales/customerlist', [SaleApiController::class, 'customerlist']);
    Route::post('/sales/scan-qr', [SaleApiController::class, 'scanQr']);
    Route::get('/sales/search-itemsets', [SaleApiController::class, 'searchItemsets']);
    Route::get('/sales/approval-items', [SaleApiController::class, 'approvalItems']);
    Route::get('/sales/add-label-from-approval', [SaleApiController::class, 'approvalItems']);
    Route::post('/sales/add-to-cart', [SaleApiController::class, 'addToCart']);
    Route::get('/sales/cart-items', [SaleApiController::class, 'cartItems']);
    Route::delete('/sales/cart/remove/{id}', [SaleApiController::class, 'removeCartItem']);
    Route::post('/sales/confirm-sale', [SaleApiController::class, 'confirmSale']);
    Route::get('/sales/{id}/pdf', [SaleApiController::class, 'pdf'])->whereNumber('id')->name('api.sales.pdf');
    Route::get('/itemsets/qr-list', [SaleApiController::class, 'qrListApi']);
    Route::post('/itemsets/qr/pdf', [SaleApiController::class, 'downloadQrPdf']);
    Route::post('/sales/store', [SaleApiController::class, 'store']);       // Create sale
    Route::get('/sales/{id}', [SaleApiController::class, 'show'])->whereNumber('id'); // Sale details
    Route::put('/sales/update/{id}', [SaleApiController::class, 'update'])->whereNumber('id');
    Route::get('/sales/itemset', [SaleApiController::class, 'getItemset']); // Scan QR
    
    
    Route::get('/returns/list', [SaleReturnApiController::class, 'list_of_return']);
     Route::get('/returns/customers', [SaleReturnApiController::class, 'getSalesForReturn']);
    Route::get('/returns/sale/{saleId}', [SaleReturnApiController::class, 'saleDetails']);
    Route::post('/returns/store', [SaleReturnApiController::class, 'store']);
    Route::get('/returns/pdf/{returnId}', [SaleReturnApiController::class, 'pdf']);
    Route::post('/returns/scan-product', [SaleReturnApiController::class,'scanProduct']);
    Route::post('/returns/scan-qr', [SaleReturnApiController::class,'scanQr']);
    Route::get('/returns/approval-items', [SaleReturnApiController::class, 'approvalReturnItems']);
    Route::post('/returns/process-selected', [SaleReturnApiController::class, 'processSelected']);
    Route::get('/returns/cart-list', [SaleReturnApiController::class, 'returnCartList']);
    Route::delete('/returns/cart-remove/{id}', [SaleReturnApiController::class, 'removeCartItem']);
    Route::post('/returns/confirm-return', [SaleReturnApiController::class, 'confirmReturn']);
    Route::post('/returns/search-return', [SaleReturnApiController::class, 'returnsearchItemSets']);

    Route::get('/approvals/customers', [ApprovalApiController::class, 'customers']);
    Route::get('/approvals/items', [ApprovalApiController::class, 'items']);
    Route::get('/approvals', [ApprovalApiController::class, 'index']);
    Route::post('/approvals/scan-qr', [ApprovalApiController::class, 'scanQr']);
    Route::get('/approvals/cart-list', [ApprovalApiController::class, 'cartList']);
    Route::delete('/approvals/cart-remove/{id}', [ApprovalApiController::class, 'removeCartItem']);
    Route::get('/approvals/itemsets/{itemId}', [ApprovalApiController::class, 'getItemSets']);
    Route::get('/approvals/search-itemsets', [ApprovalApiController::class, 'searchItemSets']);
    Route::post('/approvals/store', [ApprovalApiController::class, 'store']);
    Route::put('/approvals/update/{id}', [ApprovalApiController::class, 'update'])->whereNumber('id');
    Route::get('/approvals/{id}', [ApprovalApiController::class, 'show'])->whereNumber('id');
    Route::post('/approvals/sale', [ApprovalApiController::class, 'markSold']);
    Route::get('/approvals/pending-items', [ApprovalApiController::class, 'pendingItemsByCustomer']);
    Route::post('/approvals/return', [ApprovalApiController::class, 'returnItems']);
    Route::get('/approvals/{id}/pdf', [ApprovalApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/approvals/pdf/{id}', [ApprovalApiController::class, 'pdf'])->whereNumber('id');
});

// Signed public PDF URL (for browser/app open without bearer header)
Route::get('/public/sales/{id}/pdf', [SaleApiController::class, 'publicPdf'])
    ->whereNumber('id')
    ->name('api.sales.pdf.public');

