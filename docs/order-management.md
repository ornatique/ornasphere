# Order Management

Open `/company/{slug}/order-management` from the Order Management sidebar entry.

- List and search orders by customer or order number; add and edit orders.
- The list has an All Customers dropdown next to live search. Changing the customer or typing filters the table without a Search button or full-page reload; pagination keeps both filters.
- From Date and To Date filter by order date, including both boundaries. Either date can be left blank. They combine with customer and order-number search, and pagination preserves all filters. Order search accepts `OM-000001`, `000001` or `1`.
- Order-number search also matches partial displayed numbers while typing, for example `om`, `OM-` and `om-000`. Company, customer and date filters still apply.
- The list's PDF action opens a printable order in a new tab, including saved customer/item details, quantities, rates and totals. It uses view permission and does not update records.
- The PDF omits Purity, Waste %, Net Purity, Fine Wt, Metal Rate and Labour Rate. Wholesale Price is shown only when at least one selected label has a positive price and the company setting allows it. The order form keeps its final Image column and click-to-open preview; the PDF shows its Image column immediately before Qty, using the existing Stock Gallery image embedding service. Missing images show No image. Wholesale Price uses the current label's batch price, matching the Item Set list, and respects the company's wholesale-price setting; it is display-only and does not change saved totals. Form images use the current label image. Missing form images show No image.
- Select an existing active sale party, or select **Other — Create new customer**. Name is required; email, mobile, city and address are optional. Saving creates the customer in the Customer category and links it to the order in one transaction. Validation failure creates neither record.
- Filter by item name or search HUID, QR, barcode or item name. Suggestions open directly below the scan field, with the label and item name on separate lines. Selecting a result adds a row, closes suggestions and clears the scan field; scanning an exact code followed by Enter adds its single exact match. Selected labels are excluded from subsequent searches, including saved edit rows. Removing a row makes it searchable again.
- Selected rows show the label and item name, quantity and weights, with summary cards above. The lower Search Item Name field filters selected rows by name, HUID, QR or barcode without removing them from the order.
- Selected item rows also have Approval-style editable weights, purity, waste, metal/labour rates, other amount and remarks. Net purity, fine weight and amounts are recalculated on the server when saving. The All buttons copy the first row's field to the remaining rows. Fixed label sale prices remain server-controlled.
- Other customer city/address fields reuse the existing Google Places integration and can fill the pincode. They need the same configured Google Maps key and Places access as the Customer page; manual entry remains available.
- Only final labels with available quantity in the signed-in user's company can be selected. The server validates company ownership and positive quantities (up to 1,000,000). Order quantity can exceed available stock because orders do not reserve or sell it; existing stock is unchanged.
- Saving orders does not reserve, sell or change stock. Order Management is separate from the disabled manufacturing Customer Orders/CAD/RPT workflow.
- Access follows the Sales module setting. Company admins have access; staff need `order-management-view`, `order-management-create` and/or `order-management-edit`. The Permissions page generates these entries using the existing permission setup. Archive mode continues to block writes.
- New order tables are included in company backup ownership and relationship rules.

## Deployment

Order price selection: new orders default to Sale Price. Choose Sale Price or Wholesale Price above the customer fields to change fixed-price totals; the selected price column is shown on the form/PDF. Selection is saved as `managed_orders.price_mode` and returned by the API. Wholesale requires the company setting; missing selected prices fall back to the other available price. Labels store batch prices, so billing divides by the original label quantity before applying order quantity. Existing orders with a null mode keep the previous wholesale-first default when enabled and are not rewritten by the migration.

For this price-selection change, deploy `OrderManagementController.php`, `OrderManagementApiController.php`, `OrderManagementPricing.php`, `public/js/order-management.js`, and the Order Management form/PDF views together with `2026_11_25_140000_add_price_mode_to_managed_orders.php`. Run only that new migration if the earlier tables are already installed. For manual phpMyAdmin setup, the equivalent new schema change is `ALTER TABLE managed_orders ADD COLUMN price_mode VARCHAR(16) NULL;` (run once only; do not run the migration again after manually adding the column).

If `/js/order-management.js` returns 404 but `/public/js/order-management.js` opens successfully on the host, set `ORDER_MANAGEMENT_ASSET_PREFIX=public` in that host's `.env`. Deploy `config/app.php` and both Order Management form/index views, then run `php artisan config:clear` and `php artisan view:clear`. This setting changes only the two Order Management script URLs. Local `artisan serve` keeps the default empty prefix. Both JavaScript files must remain in Laravel's `public/js/` folder.

Deploy all of the following files together:

```
app/Http/Controllers/Company/OrderManagementController.php
app/Http/Controllers/Company/CompanyPermissionController.php
app/Http/Middleware/CompanyRoutePermission.php
app/Models/ManagedOrder.php
app/Models/ManagedOrderItem.php
app/Services/OrderManagementPricing.php
config/company_archive.php
config/company_modules.php
config/app.php
database/migrations/2026_11_25_120000_create_managed_orders_tables.php
database/migrations/2026_11_25_130000_add_pricing_to_managed_order_items.php
database/migrations/2026_11_25_140000_add_price_mode_to_managed_orders.php
public/js/order-management.js
public/js/order-management-list.js
resources/views/company/order_management/index.blade.php
resources/views/company/order_management/list.blade.php
resources/views/company/order_management/form.blade.php
resources/views/company/order_management/pdf.blade.php
resources/views/company/customers/partials/address_autofill.blade.php
resources/views/company_layout/sidebar.blade.php
routes/order_management.php
routes/web.php
```

From the Laravel application root, run only this new migration:

```sh
php artisan migrate --path=database/migrations/2026_11_25_120000_create_managed_orders_tables.php --force
php artisan migrate --path=database/migrations/2026_11_25_130000_add_pricing_to_managed_order_items.php --force
php artisan migrate --path=database/migrations/2026_11_25_140000_add_price_mode_to_managed_orders.php --force
php artisan optimize:clear
```

This migration creates `managed_orders` and `managed_order_items`. It does not update existing business records. Deploy the archive configuration together with the migration; unknown tables are intentionally blocked by the backup system. Restore or cancel any pending old archive before changing the schema: an archive from the previous schema may require compatibility review after deployment.

Tests: `tests/Feature/OrderManagementTest.php`, using SQLite in memory. Never run these fixture tests against an imported or live database.
