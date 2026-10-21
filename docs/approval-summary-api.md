# Approval Summary

Normal company: `GET /api/reports/approval-summary`

Branch report (company admins): `GET /api/company/branch-reports/approval-summary`

Headers: Authorization: Bearer YOUR_TOKEN; Accept: application/json.

Normal users require `report-approval-summary-view`; their report is restricted to their own company. Branch admins/main company admins retain existing branch scope rules.

Filters: `customer_id` (numeric ID from response customers; omit for all), `from_date`, `to_date` (YYYY-MM-DD), `voucher`, `page` (25 rows per page). Branch endpoint additionally accepts `report_branches` (authorized slugs separated by commas or `all`).

JSON: `success`, `heading`, `data.rows` (paginator), `data.metrics`, `data.totals`, `data.customers`, `data.selected_customer_id`.

Append `export=pdf` or `export=csv` for all filtered rows, retaining the same filters. PDF uses separate issued, returned, sold and pending tables for legibility.

Deploy updated controllers, routes, permission catalogs, sidebar and templates. Run the single permission migration:
`php artisan migrate --path=database/migrations/2026_10_15_140000_add_approval_summary_view_permission.php --force`
Then `php artisan permission:cache-reset`, `php artisan route:clear`, `php artisan view:clear`.

Assign Approval Summary → View to the intended roles. Existing Approval Outstanding permissions are not automatically expanded.
