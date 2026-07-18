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
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\ProductionCostApiController;
use App\Http\Controllers\Api\LabourFormulaApiController;
use App\Http\Controllers\Api\ProductionStepApiController;
use App\Http\Controllers\Api\JobWorkerApiController;
use App\Http\Controllers\Api\JobworkIssueApiController;
use App\Http\Controllers\Api\VisitingCardApiController;
use App\Http\Controllers\Api\ProductBackgroundRemoveApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\CustomerAdvanceApiController;
use App\Http\Controllers\Api\AppThemeApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\VacuumBuchApiController;
use App\Http\Controllers\Api\CastingHeatingApiController;
use App\Http\Controllers\Api\CastingMetalIssueApiController;
use App\Http\Controllers\Api\CastingReceiveApiController;
use App\Http\Controllers\Api\CastingSortingApiController;
use App\Http\Controllers\Api\TreeCuttingIssueApiController;
use App\Http\Controllers\Api\TreeCuttingReceiveApiController;
use App\Http\Controllers\Api\VacuumProcessApiController;
use App\Http\Controllers\Api\VacuumVoucherApiController;

Route::post('/company/login', [AuthController::class, 'login']);
Route::post('/company/verify-otp', [AuthController::class, 'verifyOtp']);

Route::middleware(['auth:sanctum', 'company.active'])->group(function () {
    Route::post('/company/logout', [AuthController::class, 'logout']);
    Route::get('/company/me', [AuthController::class, 'me']);
});




Route::middleware(['auth:sanctum', 'company.active'])->group(function () {

    Route::get('/notifications/summary', [NotificationApiController::class, 'summary']);
    Route::get('/notifications', [NotificationApiController::class, 'index']);
    Route::post('/notifications/read', [NotificationApiController::class, 'markAllRead']);
    Route::post('/notifications/read-module', [NotificationApiController::class, 'markModuleRead']);

    Route::get('/app-theme', [AppThemeApiController::class, 'active']);
    Route::get('/app/themes/active', [AppThemeApiController::class, 'active']);

    Route::get('/users', [CompanyUserController::class, 'index']);
    Route::post('/create_users', [CompanyUserController::class, 'store']);
    Route::put('/update_users/{id}', [CompanyUserController::class, 'update']);
    Route::post('/update_users/{id}', [CompanyUserController::class, 'update']);
    Route::post('/users/{id}/reset-2fa', [CompanyUserController::class, 'reset2fa']);
    Route::post('/reset2fa_users/{id}', [CompanyUserController::class, 'reset2fa']);
    Route::post('/users/{id}/toggle-status', [CompanyUserController::class, 'toggleStatus']);
    Route::post('/users/{id}/status', [CompanyUserController::class, 'toggleStatus']);
    Route::post('/toggle_users/{id}', [CompanyUserController::class, 'toggleStatus']);
    Route::delete('/delete_users/{id}', [CompanyUserController::class, 'destroy']);

    Route::get('/roles', [RoleApiController::class, 'index']);
    Route::post('/roles/list', [RoleApiController::class, 'index']);
    Route::post('/list-roles', [RoleApiController::class, 'index']);
    Route::post('/roles', [RoleApiController::class, 'store']);
    Route::post('/add-roles', [RoleApiController::class, 'store']);
    Route::get('/roles/{id}', [RoleApiController::class, 'show']);
    Route::put('/roles/{id}', [RoleApiController::class, 'update']);
    Route::delete('/roles/{id}', [RoleApiController::class, 'destroy']);

    Route::get('/permissions', [PermissionApiController::class, 'index']);
    Route::post('/permissions/list', [PermissionApiController::class, 'index']);
    Route::post('/list-permissions', [PermissionApiController::class, 'index']);
    Route::post('/permissions', [PermissionApiController::class, 'store']);
    Route::post('/add-permissions', [PermissionApiController::class, 'store']);
    Route::get('/permissions/{id}', [PermissionApiController::class, 'show']);
    Route::put('/permissions/{id}', [PermissionApiController::class, 'update']);
    Route::delete('/permissions/{id}', [PermissionApiController::class, 'destroy']);

    Route::get('/customers', [CustomerApiController::class, 'index']);
    Route::post('/customers', [CustomerApiController::class, 'store']);
    Route::get('/customers/{id}', [CustomerApiController::class, 'show']);
    Route::put('/customers/{id}', [CustomerApiController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerApiController::class, 'destroy']);
    Route::get('/customers_list', [CustomerApiController::class, 'index']);
    Route::post('/create_customers', [CustomerApiController::class, 'store']);
    Route::post('/update_customers/{id}', [CustomerApiController::class, 'update']);
    Route::delete('/delete_customers/{id}', [CustomerApiController::class, 'destroy']);

    Route::get('/job-workers', [JobWorkerApiController::class, 'index']);
    Route::post('/job-workers', [JobWorkerApiController::class, 'store']);
    Route::get('/job-workers/export/excel', [JobWorkerApiController::class, 'exportExcel']);
    Route::get('/job-workers/export/pdf', [JobWorkerApiController::class, 'exportPdf']);
    Route::get('/job-workers/{id}', [JobWorkerApiController::class, 'show']);
    Route::put('/job-workers/{id}', [JobWorkerApiController::class, 'update']);
    Route::delete('/job-workers/{id}', [JobWorkerApiController::class, 'destroy']);
    Route::get('/job-workers-list', [JobWorkerApiController::class, 'index']);
    Route::post('/create-job-workers', [JobWorkerApiController::class, 'store']);
    Route::put('/update-job-workers/{id}', [JobWorkerApiController::class, 'update']);
    Route::delete('/delete-job-workers/{id}', [JobWorkerApiController::class, 'destroy']);
    Route::get('/export-job-workers/excel', [JobWorkerApiController::class, 'exportExcel']);
    Route::get('/export-job-workers/pdf', [JobWorkerApiController::class, 'exportPdf']);

    Route::get('/jobwork-issues', [JobworkIssueApiController::class, 'index']);
    Route::post('/jobwork-issues', [JobworkIssueApiController::class, 'store']);
    Route::get('/jobwork-issues/other-charges', [JobworkIssueApiController::class, 'otherCharges']);
    Route::get('/jobwork-issues/export/excel', [JobworkIssueApiController::class, 'exportExcel']);
    Route::get('/jobwork-issues/export/pdf', [JobworkIssueApiController::class, 'exportPdf']);
    Route::get('/jobwork-issues/export/excel/{id}', [JobworkIssueApiController::class, 'exportSingleExcel']);
    Route::get('/jobwork-issues/export/pdf/{id}', [JobworkIssueApiController::class, 'exportSinglePdf']);
    Route::get('/jobwork-issues/{id}', [JobworkIssueApiController::class, 'show']);
    Route::put('/jobwork-issues/{id}', [JobworkIssueApiController::class, 'update']);
    Route::delete('/jobwork-issues/{id}', [JobworkIssueApiController::class, 'destroy']);
    Route::get('/jobwork-issues-list', [JobworkIssueApiController::class, 'index']);
    Route::get('/export-jobwork-issues/excel', [JobworkIssueApiController::class, 'exportExcel']);
    Route::get('/export-jobwork-issues/pdf', [JobworkIssueApiController::class, 'exportPdf']);
    Route::post('/create-jobwork-issues', [JobworkIssueApiController::class, 'store']);
    Route::put('/update-jobwork-issues/{id}', [JobworkIssueApiController::class, 'update']);
    Route::delete('/delete-jobwork-issues/{id}', [JobworkIssueApiController::class, 'destroy']);

    Route::get('/vacuum-buchs', [VacuumBuchApiController::class, 'index']);
    Route::get('/vacuum-buchs/options', [VacuumBuchApiController::class, 'options']);
    Route::post('/vacuum-buchs', [VacuumBuchApiController::class, 'store']);
    Route::get('/vacuum-buchs/{id}', [VacuumBuchApiController::class, 'show'])->whereNumber('id');
    Route::put('/vacuum-buchs/{id}', [VacuumBuchApiController::class, 'update'])->whereNumber('id');
    Route::delete('/vacuum-buchs/{id}', [VacuumBuchApiController::class, 'destroy'])->whereNumber('id');
    Route::get('/vacuum_buchs_list', [VacuumBuchApiController::class, 'index']);
    Route::post('/create_vacuum_buchs', [VacuumBuchApiController::class, 'store']);
    Route::post('/update_vacuum_buchs/{id}', [VacuumBuchApiController::class, 'update'])->whereNumber('id');
    Route::delete('/delete_vacuum_buchs/{id}', [VacuumBuchApiController::class, 'destroy'])->whereNumber('id');

    Route::get('/vacuum-processes', [VacuumProcessApiController::class, 'index']);
    Route::get('/vacuum-processes/options', [VacuumProcessApiController::class, 'options']);
    Route::post('/vacuum-processes', [VacuumProcessApiController::class, 'store']);
    Route::get('/vacuum-processes/{id}', [VacuumProcessApiController::class, 'show'])->whereNumber('id');
    Route::put('/vacuum-processes/{id}', [VacuumProcessApiController::class, 'update'])->whereNumber('id');
    Route::delete('/vacuum-processes/{id}', [VacuumProcessApiController::class, 'destroy'])->whereNumber('id');
    Route::get('/vacuum_processes_list', [VacuumProcessApiController::class, 'index']);
    Route::post('/create_vacuum_processes', [VacuumProcessApiController::class, 'store']);
    Route::post('/update_vacuum_processes/{id}', [VacuumProcessApiController::class, 'update'])->whereNumber('id');
    Route::delete('/delete_vacuum_processes/{id}', [VacuumProcessApiController::class, 'destroy'])->whereNumber('id');

    Route::get('/vouchers', [VacuumVoucherApiController::class, 'index']);
    Route::get('/vouchers/buch-options', [VacuumVoucherApiController::class, 'buchOptions']);
    Route::post('/vouchers', [VacuumVoucherApiController::class, 'store']);
    Route::get('/vouchers/{id}/pdf', [VacuumVoucherApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/vouchers/{id}', [VacuumVoucherApiController::class, 'show'])->whereNumber('id');
    Route::put('/vouchers/{id}', [VacuumVoucherApiController::class, 'update'])->whereNumber('id');
    Route::delete('/vouchers/{id}', [VacuumVoucherApiController::class, 'destroy'])->whereNumber('id');
    Route::get('/vouchers_list', [VacuumVoucherApiController::class, 'index']);
    Route::get('/vouchers_buch_options', [VacuumVoucherApiController::class, 'buchOptions']);
    Route::get('/vouchers_pdf/{id}', [VacuumVoucherApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/create_vouchers', [VacuumVoucherApiController::class, 'store']);
    Route::post('/update_vouchers/{id}', [VacuumVoucherApiController::class, 'update'])->whereNumber('id');
    Route::delete('/delete_vouchers/{id}', [VacuumVoucherApiController::class, 'destroy'])->whereNumber('id');

    Route::get('/casting-heating', [CastingHeatingApiController::class, 'index']);
    Route::get('/casting-heating/{id}/pdf', [CastingHeatingApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/casting-heating/{id}', [CastingHeatingApiController::class, 'show'])->whereNumber('id');
    Route::put('/casting-heating/{id}', [CastingHeatingApiController::class, 'update'])->whereNumber('id');
    Route::post('/casting-heating/{id}', [CastingHeatingApiController::class, 'update'])->whereNumber('id');
    Route::get('/casting_heating_list', [CastingHeatingApiController::class, 'index']);
    Route::get('/casting_heating_show/{id}', [CastingHeatingApiController::class, 'show'])->whereNumber('id');
    Route::get('/casting_heating_pdf/{id}', [CastingHeatingApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_casting_heating/{id}', [CastingHeatingApiController::class, 'update'])->whereNumber('id');

    Route::get('/casting-metal-issue', [CastingMetalIssueApiController::class, 'index']);
    Route::get('/casting-metal-issue/{id}/pdf', [CastingMetalIssueApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/casting-metal-issue/{id}', [CastingMetalIssueApiController::class, 'show'])->whereNumber('id');
    Route::put('/casting-metal-issue/{id}', [CastingMetalIssueApiController::class, 'update'])->whereNumber('id');
    Route::post('/casting-metal-issue/{id}', [CastingMetalIssueApiController::class, 'update'])->whereNumber('id');
    Route::get('/casting_metal_issue_list', [CastingMetalIssueApiController::class, 'index']);
    Route::get('/casting_metal_issue_show/{id}', [CastingMetalIssueApiController::class, 'show'])->whereNumber('id');
    Route::get('/casting_metal_issue_pdf/{id}', [CastingMetalIssueApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_casting_metal_issue/{id}', [CastingMetalIssueApiController::class, 'update'])->whereNumber('id');

    Route::get('/casting-receive', [CastingReceiveApiController::class, 'index']);
    Route::get('/casting-receive/{id}/pdf', [CastingReceiveApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/casting-receive/{id}', [CastingReceiveApiController::class, 'show'])->whereNumber('id');
    Route::put('/casting-receive/{id}', [CastingReceiveApiController::class, 'update'])->whereNumber('id');
    Route::post('/casting-receive/{id}', [CastingReceiveApiController::class, 'update'])->whereNumber('id');
    Route::get('/casting_receive_list', [CastingReceiveApiController::class, 'index']);
    Route::get('/casting_receive_show/{id}', [CastingReceiveApiController::class, 'show'])->whereNumber('id');
    Route::get('/casting_receive_pdf/{id}', [CastingReceiveApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_casting_receive/{id}', [CastingReceiveApiController::class, 'update'])->whereNumber('id');

    Route::get('/casting-release', [CastingReceiveApiController::class, 'index']);
    Route::get('/casting-release/pdf/{id}', [CastingReceiveApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/casting-release/{id}', [CastingReceiveApiController::class, 'show'])->whereNumber('id');
    Route::put('/casting-release/{id}', [CastingReceiveApiController::class, 'update'])->whereNumber('id');
    Route::post('/casting-release/{id}', [CastingReceiveApiController::class, 'update'])->whereNumber('id');

    Route::get('/tree-cutting-issue', [TreeCuttingIssueApiController::class, 'index']);
    Route::get('/tree-cutting-issue/{id}/pdf', [TreeCuttingIssueApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/tree-cutting-issue/{id}', [TreeCuttingIssueApiController::class, 'show'])->whereNumber('id');
    Route::put('/tree-cutting-issue/{id}', [TreeCuttingIssueApiController::class, 'update'])->whereNumber('id');
    Route::post('/tree-cutting-issue/{id}', [TreeCuttingIssueApiController::class, 'update'])->whereNumber('id');
    Route::get('/tree_cutting_issue_list', [TreeCuttingIssueApiController::class, 'index']);
    Route::get('/tree_cutting_issue_show/{id}', [TreeCuttingIssueApiController::class, 'show'])->whereNumber('id');
    Route::get('/tree_cutting_issue_pdf/{id}', [TreeCuttingIssueApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_tree_cutting_issue/{id}', [TreeCuttingIssueApiController::class, 'update'])->whereNumber('id');

    Route::get('/tree-cutting-receive', [TreeCuttingReceiveApiController::class, 'index']);
    Route::get('/tree-cutting-receive/pdf/{id}', [TreeCuttingReceiveApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/tree-cutting-receive/{id}', [TreeCuttingReceiveApiController::class, 'show'])->whereNumber('id');
    Route::put('/tree-cutting-receive/{id}', [TreeCuttingReceiveApiController::class, 'update'])->whereNumber('id');
    Route::post('/tree-cutting-receive/{id}', [TreeCuttingReceiveApiController::class, 'update'])->whereNumber('id');
    Route::get('/tree_cutting_receive_list', [TreeCuttingReceiveApiController::class, 'index']);
    Route::get('/tree_cutting_receive_show/{id}', [TreeCuttingReceiveApiController::class, 'show'])->whereNumber('id');
    Route::get('/tree_cutting_receive_pdf/{id}', [TreeCuttingReceiveApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_tree_cutting_receive/{id}', [TreeCuttingReceiveApiController::class, 'update'])->whereNumber('id');

    Route::get('/casting-sorting', [CastingSortingApiController::class, 'index']);
    Route::get('/casting-sorting/{id}/pdf', [CastingSortingApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/casting-sorting/{id}', [CastingSortingApiController::class, 'show'])->whereNumber('id');
    Route::put('/casting-sorting/{id}', [CastingSortingApiController::class, 'update'])->whereNumber('id');
    Route::post('/casting-sorting/{id}', [CastingSortingApiController::class, 'update'])->whereNumber('id');
    Route::get('/casting_sorting_list', [CastingSortingApiController::class, 'index']);
    Route::get('/casting_sorting_show/{id}', [CastingSortingApiController::class, 'show'])->whereNumber('id');
    Route::get('/casting_sorting_pdf/{id}', [CastingSortingApiController::class, 'pdf'])->whereNumber('id');
    Route::post('/update_casting_sorting/{id}', [CastingSortingApiController::class, 'update'])->whereNumber('id');

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
    Route::get('/item-sets/bulk-list', [ItemSetController::class, 'bulkListsetData']);

    Route::get('itemsets_list/', [ItemSetController::class, 'listset_data']);     // list + filter
    Route::get('itemsets_bulk_list/', [ItemSetController::class, 'bulkListsetData']); // bulk grouped list
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

    Route::get('/production-costs', [ProductionCostApiController::class, 'index']);
    Route::get('/production-costs/options', [ProductionCostApiController::class, 'options']);
    Route::post('/production-costs', [ProductionCostApiController::class, 'store']);
    Route::get('/production-costs/{id}', [ProductionCostApiController::class, 'show']);
    Route::put('/production-costs/{id}', [ProductionCostApiController::class, 'update']);
    Route::delete('/production-costs/{id}', [ProductionCostApiController::class, 'destroy']);

    Route::post('/add-production-costs', [ProductionCostApiController::class, 'store']);
    Route::put('/update-production-costs/{id}', [ProductionCostApiController::class, 'update']);
    Route::delete('/delete-production-costs/{id}', [ProductionCostApiController::class, 'destroy']);

    Route::get('/labour-formulas', [LabourFormulaApiController::class, 'index']);
    Route::get('/labour-formulas/options', [LabourFormulaApiController::class, 'options']);
    Route::post('/labour-formulas', [LabourFormulaApiController::class, 'store']);
    Route::get('/labour-formulas/{id}', [LabourFormulaApiController::class, 'show']);
    Route::put('/labour-formulas/{id}', [LabourFormulaApiController::class, 'update']);
    Route::delete('/labour-formulas/{id}', [LabourFormulaApiController::class, 'destroy']);

    Route::post('/add-labour-formulas', [LabourFormulaApiController::class, 'store']);
    Route::put('/update-labour-formulas/{id}', [LabourFormulaApiController::class, 'update']);
    Route::delete('/delete-labour-formulas/{id}', [LabourFormulaApiController::class, 'destroy']);

    Route::get('/production-steps', [ProductionStepApiController::class, 'index']);
    Route::get('/production-steps/options', [ProductionStepApiController::class, 'options']);
    Route::post('/production-steps', [ProductionStepApiController::class, 'store']);
    Route::get('/production-steps/{id}', [ProductionStepApiController::class, 'show']);
    Route::put('/production-steps/{id}', [ProductionStepApiController::class, 'update']);
    Route::delete('/production-steps/{id}', [ProductionStepApiController::class, 'destroy']);

    Route::post('/add-production-steps', [ProductionStepApiController::class, 'store']);
    Route::put('/update-production-steps/{id}', [ProductionStepApiController::class, 'update']);
    Route::delete('/delete-production-steps/{id}', [ProductionStepApiController::class, 'destroy']);

    Route::get('/sale-list', [SaleApiController::class, 'index']);             // List sales
    Route::get('/sales/export/listpdf', [SaleApiController::class, 'exportListPdf']);
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
    Route::get('/sales/pdf/{id}', [SaleApiController::class, 'pdf'])->whereNumber('id');
    Route::get('/itemsets/qr-list', [SaleApiController::class, 'qrListApi']);
    Route::post('/itemsets/qr/pdf', [SaleApiController::class, 'downloadQrPdf']);
    Route::post('/itemsets/qr/pdf/direct', [SaleApiController::class, 'directQrPdfForApp']);
    Route::post('/sales/store', [SaleApiController::class, 'store']);       // Create sale
    Route::get('/sales/{id}', [SaleApiController::class, 'show'])->whereNumber('id'); // Sale details
    Route::put('/sales/update/{id}', [SaleApiController::class, 'update'])->whereNumber('id');
    Route::get('/sales/itemset', [SaleApiController::class, 'getItemset']); // Scan QR

    // Receive / Return / Purchase (Advance Ledger)
    Route::get('/sales/advance-ledger/customers', [CustomerAdvanceApiController::class, 'customers']);
    Route::get('/sales/advance-ledger/summary', [CustomerAdvanceApiController::class, 'summary']);
    Route::get('/sales/advance-ledger/entries', [CustomerAdvanceApiController::class, 'entries']);
    Route::post('/sales/advance-ledger/entries', [CustomerAdvanceApiController::class, 'store']);
    Route::get('/sales/advance-ledger/pdf-url', [CustomerAdvanceApiController::class, 'pdfUrl']);
    Route::get('/sales/advance-ledger/pdf', [CustomerAdvanceApiController::class, 'pdf']);


    Route::get('/returns', [SaleReturnApiController::class, 'list_of_return']);
    Route::get('/returns/list', [SaleReturnApiController::class, 'list_of_return']);
    Route::get('/returns/export/listpdf', [SaleReturnApiController::class, 'exportListPdf']);
    Route::get('/returns/customers', [SaleReturnApiController::class, 'getSalesForReturn']);
    Route::get('/returns/sale/{saleId}', [SaleReturnApiController::class, 'saleDetails']);
    Route::get('/returns/{id}', [SaleReturnApiController::class, 'show'])->whereNumber('id');
    Route::post('/returns/store', [SaleReturnApiController::class, 'store']);
    Route::get('/returns/pdf/{returnId}', [SaleReturnApiController::class, 'pdf']);
    Route::post('/returns/scan-product', [SaleReturnApiController::class, 'scanProduct']);
    Route::post('/returns/scan-qr', [SaleReturnApiController::class, 'scanQr']);
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
    Route::get('/approvals/export/pdf', [ApprovalApiController::class, 'exportListPdf']);
    Route::get('/approvals/export/pdf-url', [ApprovalApiController::class, 'exportListPdfUrl']);
    Route::get('/reports/barcode-history/suggest', [ReportApiController::class, 'barcodeHistorySuggest']);
    Route::get('/reports/purchase-receiver-summary', [ReportApiController::class, 'purchaseReceiverSummary']);
    Route::get('/reports/purchase-receiver-summary/export/excel', [ReportApiController::class, 'purchaseReceiverSummaryExcel']);
    Route::get('/reports/purchase-receiver-summary/export/pdf', [ReportApiController::class, 'purchaseReceiverSummaryPdf']);
    Route::get('/reports/stock-position', [ReportApiController::class, 'stockPosition']);
    Route::get('/reports/stock-position/export/excel', [ReportApiController::class, 'stockPositionExcel']);
    Route::get('/reports/stock-position/export/pdf', [ReportApiController::class, 'stockPositionPdf']);
    Route::get('/reports/approval-outstanding', [ReportApiController::class, 'approvalOutstanding']);
    Route::get('/reports/approval-outstanding/{approval}/details', [ReportApiController::class, 'approvalOutstandingDetails'])->whereNumber('approval');
    Route::get('/reports/approval-outstanding/export/excel', [ReportApiController::class, 'approvalOutstandingExcel']);
    Route::get('/reports/approval-outstanding/export/pdf', [ReportApiController::class, 'approvalOutstandingPdf']);
    Route::get('/reports/outstanding-amount', [ReportApiController::class, 'outstandingAmount']);
    Route::get('/reports/outstanding-amount/export/excel', [ReportApiController::class, 'outstandingAmountExcel']);
    Route::get('/reports/outstanding-amount/export/pdf', [ReportApiController::class, 'outstandingAmountPdf']);
    Route::get('/reports/outstanding-amount/export/ledger-pdf', [ReportApiController::class, 'outstandingAmountLedgerPdf']);
    Route::get('/reports/sales-summary/export/excel', [ReportApiController::class, 'salesSummaryExcel']);
    Route::get('/reports/sales-summary/export/pdf', [ReportApiController::class, 'salesSummaryPdf']);
    Route::get('/reports/barcode-history/export/excel', [ReportApiController::class, 'barcodeHistoryExcel']);
    Route::get('/reports/barcode-history/export/pdf', [ReportApiController::class, 'barcodeHistoryPdf']);
    Route::get('/reports/barcode-history', [ReportApiController::class, 'barcodeHistory']);
    Route::get('/reports/sales-summary', [ReportApiController::class, 'salesSummary']);

    Route::post('/visiting-cards/extract', [VisitingCardApiController::class, 'extract']);
    Route::post('/visiting-cards/extract-bulk', [VisitingCardApiController::class, 'extractBulk']);
    Route::post('/visiting-cards', [VisitingCardApiController::class, 'store']);
    Route::post('/visiting-cards/bulk-save', [VisitingCardApiController::class, 'bulkSave']);
    Route::get('/visiting-cards', [VisitingCardApiController::class, 'index']);
    Route::get('/reports/visiting-cards/date-wise', [VisitingCardApiController::class, 'dateWiseReport']);
    Route::get('/reports/visiting-cards/export/excel', [VisitingCardApiController::class, 'exportExcel']);
    Route::get('/reports/visiting-cards/export/pdf', [VisitingCardApiController::class, 'exportPdf']);
    Route::get('/visiting-cards/export/excel', [VisitingCardApiController::class, 'exportExcel']);
    Route::get('/visiting-cards/export/pdf', [VisitingCardApiController::class, 'exportPdf']);
    Route::get('/visiting-cards/{id}', [VisitingCardApiController::class, 'show'])->whereNumber('id');
    Route::put('/visiting-cards/{id}', [VisitingCardApiController::class, 'update'])->whereNumber('id');
    Route::post('/visiting-cards/{id}', [VisitingCardApiController::class, 'update'])->whereNumber('id');
    Route::delete('/visiting-cards/{id}', [VisitingCardApiController::class, 'destroy'])->whereNumber('id');

    Route::prefix('background-remove')->group(function () {
        Route::get('list', [ProductBackgroundRemoveApiController::class, 'index']);
        Route::post('store', [ProductBackgroundRemoveApiController::class, 'store']);
        Route::post('update/{id}', [ProductBackgroundRemoveApiController::class, 'update']);
        Route::post('delete-image/{id}', [ProductBackgroundRemoveApiController::class, 'deleteImage']);
        Route::delete('delete/{id}', [ProductBackgroundRemoveApiController::class, 'destroy']);
    });
});

// Signed public PDF URL (for browser/app open without bearer header)
Route::get('/public/sales/{id}/pdf', [SaleApiController::class, 'publicPdf'])
    ->whereNumber('id')
    ->name('api.sales.pdf.public');
