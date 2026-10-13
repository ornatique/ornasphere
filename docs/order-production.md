# Customer orders, CAD and RPT (web)

## Deploy

Deploy the new controller, models, support class, route file, migration and `resources/views/company/orders` views, together with the changes to:

- `routes/web.php`
- `app/Http/Middleware/CompanyRoutePermission.php`
- `app/Http/Controllers/Company/CompanyPermissionController.php`
- `app/Http/Controllers/Company/CompanyRoleController.php`
- `app/Http/Controllers/Company/CustomerAdvanceController.php`
- `resources/views/company_layout/sidebar.blade.php`
- `resources/views/company/sales/advance_ledger.blade.php`

From the Laravel project directory, run only this feature's migration:

```sh
php artisan migrate --path=database/migrations/2026_10_02_000001_create_order_production_tables.php --force
php artisan route:clear
php artisan view:clear
```

The existing customer, item, user and customer-advance tables must already exist. The migration creates seven new tables; it does not rewrite stock or ledger balances. No queue worker or frontend build is required. Keep `storage/app/private` writable and private. PHP upload limits must allow the desired files (application limit: CAD 50 MB, images 12 MB).

Company admins have access automatically. Open Roles / Permissions to register the new modules and assign staff permissions: `customer-order`, `cad-design`, `rpt-production` (view/create/edit). Give production staff customer-order view access for inherited specifications and attachment downloads. Voucher entry continues to require the existing sale-advance permission. Records are retained rather than deleted.

## Use

1. Customer Orders → New Customer Order. Select an active Customer/Party and enter one or more items. Save a draft, add reference images, review and confirm. Drafts can be edited; confirmation locks the specifications.
2. From each order item, assign a designer and due date. Open the CAD revision, start designing, upload source/print/preview files and enter estimated finished weight per piece. A print file and weight estimate are required for submission.
3. Review a submitted CAD. Record the approver and remarks, or request a revision. Approved and revision-requested records remain immutable. Create a new revision from the order item when changes are needed. Every revision requires its own attachments.
4. Open the latest approved CAD to plan an RPT batch. Enter operator, machine, material and planned pieces. Move through Printing → Printed → Quality Check → Complete quality check. Record actual pattern weight separately from finished metal weight.
5. Accepted plus rejected cannot exceed printed; all printed pieces must be inspected before completion. Active batches reserve planned quantity. Completed batches reserve only accepted quantity, so reprints can cover failed/unprinted pieces. Existing batches retain the exact approved CAD revision, even when a newer design is created.
6. The order page shows progress per item and an activity history. All accepted patterns remain available for casting; this first scope does not post a casting handover or change metal stock. A later casting integration must consume accepted quantities transactionally and record handover references.
7. Receive Advance / Add Voucher opens the existing Receive / Return / Purchase form with the order and customer selected. Each saved entry is linked in the same transaction as the existing voucher/ledger entry. Changing the customer to someone else rejects the save. Cash and fine metal are never combined into a single quantity.

Existing billing still consumes the existing customer-level advance ledger. The new order link is traceability, not a second balance or an order-specific advance reservation. No additional adjustment is posted by CAD or RPT. Orders with production underway can be put on hold; cancellation is restricted to before any RPT batch exists. Cancelling an order does not refund vouchers automatically.

## Validation

`tests/Feature/OrderProductionTest.php` uses an isolated SQLite in-memory database and exercises partial prints/reprints, draft edits, revision preservation, private files, company separation, permission enforcement, quantity limits, order holds, and cash/metal receipt linkage with transaction rollback. It also renders the feature screens with a minimal test layout. It does not run migrations against the configured customer database or prove a live MySQL deployment.

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
php artisan test --compact tests/Feature/OrderProductionTest.php
```
