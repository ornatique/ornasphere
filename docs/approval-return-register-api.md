# Approval & Return Register API

## Normal company report (staff or companies without branches)

`GET /api/reports/approval-return-register`

Requires a company admin or the `report-approval-outstanding-view` permission. No branch is required. The authenticated user's company is used automatically. Do not send `report_branches=all`; selections other than the current company are rejected with 403. The response, filters, pagination, pending details, PDF and CSV exports match the branch report below.

Details: `GET /api/reports/approval-return-register/{approval|return|sale}/{voucher_id}/details`. Only vouchers in the current company can be accessed. Optional `approval_no` filters item lines.

Examples:

```text
GET /api/reports/approval-return-register?page=1&per_page=10
GET /api/reports/approval-return-register?search=Customer&from_date=2026-10-01&to_date=2026-10-15
GET /api/reports/approval-return-register?export=pdf
```

Use `Authorization: Bearer <token>` and `Accept: application/json`. Missing report permission returns 403, not an empty result. The company-admin branch endpoint remains unchanged.

## Branch reports

Uses the existing authenticated company-admin endpoint:

`GET /api/company/branch-reports/approval-return-register`

Headers: `Authorization: Bearer <token>` and `Accept: application/json`.

Query parameters:
- `report_branches`: comma-separated authorized company slugs, or `all`; defaults to current company.
- `customer_id`: encrypted `filter_value` from `data.customers`. Omit for all customers.
- `from_date`, `to_date`: YYYY-MM-DD.
- `voucher`: partial transaction or approval voucher number.
- `entry_type`: Approval, Approval Return, or Sale from Approval. Omit for all.
- `search`: plain search text.
- `page`: page number; `per_page`: 10, 25, 50, or 100.
- `export`: pdf or csv for a file response instead of JSON.

JSON retains `success`, `heading`, and `data.rows` (Laravel paginator), `data.metrics`, and `data.totals`.

Added fields:
- Each transaction row includes `pending_qty` (current quantity for approval rows; null for return/sale rows) and `branch_name`.
- `data.pending_items`: all matching current pending items, with approval `voucher_id`, `voucher_no`, `entry_date`, `company_id`, customer name, item name, QR code, HUID, gross/other/net/fine weights and amount.
- `data.pending_count`: count of those items, independent of transaction pagination.
- `data.pending_scope`: explanation of the snapshot scope.
- `data.customers`: id, encrypted filter_value, name, company_id and branch_name for the selected branches.
- `data.selected_customer_id`, `data.entry_types`, `data.columns`: form selection and column labels. The app may toggle visible columns locally.

Pending totals appear under `data.totals["Pending Approval (current)"]`. Pending means current item status, not issued minus returned minus sold during the filtered period. It uses approval issue dates and ignores the transaction entry_type filter.

For branches use `GET /api/company/branches`. For voucher details use `GET /api/company/branch-report-details/{approval|return|sale}/{voucher_id}`, preserving `report_branches` and optionally `approval_no`.

For PDF/CSV call this same report endpoint with `export=pdf` or `export=csv` and preserve the filters. PDF includes pending-item details; CSV currently includes pending totals alongside transaction rows.

Deploy the updated BranchReportApiController together with the existing ApprovalReturnRegister service, ReportController, ReportBranches service and report templates. No new route or migration is required. The mobile/client app must render this response to reproduce the screenshot.
