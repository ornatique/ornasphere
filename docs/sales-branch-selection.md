# Sales web and API branch selection

Web menus: Sale on Approval, Approval Return, Sales Estimate, Receive / Return / Purchase. Select one or more companies/branches and click View. Each list and PDF/Excel report shows Company / Branch. Existing row actions target the owning branch. New records belong to the company in the page URL. Approval voucher and item views remain supported. Reports export all matching rows, not just one page.

## APIs

Use the existing Bearer token and Accept: application/json. Company choices come from GET /api/company/branches. Replace sample IDs with real IDs.

```http
GET /api/approvals?management_branches[]=1&management_branches[]=2
GET /api/returns?management_branches[]=1&management_branches[]=2&source_type=approval
GET /api/sale-list?management_branches[]=1&management_branches[]=2
GET /api/sales/advance-ledger/vouchers?management_branches[]=1&management_branches[]=2&per_page=25
```

The returns endpoint retains its existing all-return behavior unless source_type=approval is provided. Receive / Return / Purchase pagination is global across selected companies. Other list responses retain their existing row fields and add company identity:

```json
{
  "success": true,
  "data": [{"id": 123, "voucher_no": "MJ-SL-123", "company_id": 2, "company_name": "CG Road Branch"}],
  "selected_company_ids": [1, 2]
}
```

Add export=pdf or export=excel to the list URLs for binary PDF/XLSX. Existing list PDF endpoints also support branch selection. Flutter should request bytes for exports. Filter parameters such as from_date, to_date and customer_id remain endpoint-specific.

Use company_id for one-company actions, form options, carts, record PDFs, and party balances/entries. Keep existing payload fields and add company_id:

```http
GET /api/sales/123?company_id=2
GET /api/sales/advance-ledger/summary?company_id=2&customer_id=180
GET /api/sales/advance-ledger/vouchers/pdf/123?company_id=2
```

Example existing receive-amount request with branch selection:

```json
{
  "company_id": 2,
  "customer_id": 180,
  "entry_date": "2026-10-21",
  "entry_type": "receive_amount",
  "payment_mode": "cash",
  "amount": 500
}
```

No selection defaults to the authenticated company. Main-company admins may access authorized active branches. Unrelated company selection is rejected. Multiple-company mutations return 422; do not send company_id and management_branches together. The authenticated actor is not changed.

## Upload files

- `app/Http/Controllers/Company/SaleController.php`
- `app/Http/Controllers/Company/ApprovalController.php`
- `app/Http/Controllers/Company/SaleReturnController.php`
- `app/Http/Controllers/Company/CustomerAdvanceController.php`
- `app/Http/Controllers/Api/SaleApiController.php`
- `app/Http/Controllers/Api/ApprovalApiController.php`
- `app/Http/Controllers/Api/SaleReturnApiController.php`
- `app/Http/Controllers/Api/CustomerAdvanceApiController.php`
- `app/Http/Middleware/EnsureCompanyIsActive.php`
- `app/Services/SalesApiBranches.php`
- `app/Services/SalesBranchExport.php`
- `resources/views/company_layout/management_branch_selector.blade.php`
- `resources/views/company_layout/sales_exports.blade.php`
- `resources/views/company/sales/index.blade.php`
- `resources/views/company/approval/index.blade.php`
- `resources/views/company/returns/index.blade.php`
- `resources/views/company/sales/advance_index.blade.php`

Dependencies from previous modules: CompanyBranches.php, CastingListExport.php, ReportXlsx.php, resources/views/company/casting_exports/list.blade.php, and company_layout/admin.blade.php. No new routes or migrations. Compile views after upload with php artisan view:cache. Do not clear the application cache holding inactive-token responses.

Tests: SalesBranchSelectionTest.php, ApiItemReceiptTest.php (authenticated fixture updated), ApprovalReturnEditTest.php and SaleSettlementTest.php. Targeted run passed 13 tests / 77 assertions. PHP syntax and Blade compilation passed. Live deployment and browser verification were not performed.
