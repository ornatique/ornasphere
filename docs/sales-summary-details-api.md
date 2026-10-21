# Sales summary details API

Base URL: `https://ornasphere.com/api`

Use `Authorization: Bearer <token>` on every request. Replace `366` with the
numeric sale ID returned by the sales summary, not its voucher number.
By default these endpoints access sales belonging to the authenticated user's company.
The main company admin can pass `report_branches=branch-slug` or comma-separated
authorized branch slugs, or `report_branches=all`. The sale must belong to one
of those selected companies. Branch users cannot access sibling branches.
For example: `/reports/sales-summary/366/details/pdf?report_branches=all`.
Use the same query for JSON and Excel exports. Each request exports one sale ID,
not all vouchers from the selected branches. JSON sale metadata includes
`company_id` and `branch_name`; the PDF title identifies the voucher's branch,
and CSV has a final Company / Branch column.

| Purpose | GET path | Response |
| --- | --- | --- |
| Details | `/reports/sales-summary/366/details` | JSON |
| PDF | `/reports/sales-summary/366/details/pdf` | PDF download |
| Excel-compatible CSV | `/reports/sales-summary/366/details/excel` | CSV download |

The existing `/details/export/pdf` and `/details/export/excel` paths also work.
No request body is required. Use `Accept: application/json` for details,
`Accept: application/pdf` for PDF, and `Accept: text/csv` for CSV.
In Postman, use **Send and Download** for exports.

A missing sale or a sale belonging to another company returns 404.
The Excel endpoint exports CSV, not an XLSX workbook.

After deploying the updated `routes/api.php`, run `php artisan optimize:clear`
on the server to remove stale cached routes. Keep the previous report controller
and branch-service fixes deployed as well.
