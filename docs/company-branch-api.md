# Company and branch API

Base URL: `https://ornasphere.com/api`. Changes must be deployed before these new endpoints are available.

Authenticated requests require:
```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

## Profile and branch list

`GET /company/profile` now includes `data.can_manage_branches` and `data.parent_company_id`.

`GET /company/branches` returns `success` and `data` containing:
- `can_manage_branches`, `branch_limit`, `branch_count`, `can_create_branch`, `limit_message`.
- `companies`: authorized main/branch records with `id`, `name`, `slug`, `parent_company_id`, `is_main`, `branch_type`, `city`, `address_1`, `status`, `effective_active`, `login_url`, and inherited `plan` dates.

Main company admins see their company and children. Branch users see their own company. Disable Create Branch when `can_create_branch` is false and show `limit_message` when present. The server also enforces the limit, including inactive branches.

## Create branch

`POST /company/branches`
```json
{
  "name": "Madina Retail North",
  "branch_type": "retail",
  "city": "Ahmedabad",
  "address_1": "North Road",
  "admin_name": "Branch Manager",
  "email": "north-manager@example.com",
  "password": "ExamplePassword123!",
  "password_confirmation": "ExamplePassword123!"
}
```
Use `retail` or `manufacturing`. Password minimum 12 characters. Email must be globally unique. Success: HTTP 201:
```json
{"success":true,"message":"Branch created.","data":{"id":12,"name":"Madina Retail North","slug":"generated-branch-slug","status":true}}
```
IDs and slugs above are examples; use returned values. The new branch gets its own administrator and inherits the main company's plan. Only the main company admin may create branches.

## Edit branch

`PUT /company/branches/12`
```json
{"name":"Madina Retail North","branch_type":"retail","city":"Ahmedabad","address_1":"North Road","status":true}
```
Returns `success`, `message`, and `data` with `id`, `name`, `slug`, `status`. This does not edit the branch administrator's credentials.

## Activate / deactivate

`PATCH /company/branches/12/status`
```json
{"status":false}
```
Use `true` to activate. Response:
```json
{"success":true,"message":"Branch status updated.","data":{"id":12,"status":false}}
```
Only owned child branches can be changed. Deactivation prevents branch access; it does not delete records or release a branch slot. Parent deactivation blocks all children. Branch plans remain inherited; renewal is still performed by Superadmin on the parent company through the existing web flow.

## Dashboard

`GET /company/branch-dashboard?branch=all`

Use a returned company/branch slug instead of `all` for one company. Default for main admin is all; default for branch admin is its own branch. Returns `success` and `data` with `salesToday`, `salesMonth`, `returnsMonth`, `netMonth`, `users`, `customersCount`, `activeCustomersCount`, `approvalsOpen`, `pendingApprovalItems`, `labelsInStock`, monthly series and `recentActivity`. Dashboard permission is required. `metalRates` is the existing placeholder; use the existing live metal rates API for rates.

## Combined branch reports

New endpoints are separate from the existing `/reports/...` API contract. Existing endpoints are unchanged; use these for combined company/branch reports. Company admin role is required, including for branch admins (who remain restricted to their own branch).

`report_branches` is one slug, comma-separated slugs, or `all`. Omitted means the signed-in company. Only the main admin may select other owned branches or all.

`GET /company/branch-report-customers?report_branches=branch-one,branch-two`
```json
{"success":true,"data":[{"id":180,"name":"Customer Name","company_id":12,"branch_name":"Madina Retail North","register_filter_id":"ENCRYPTED_VALUE"}]}
```
Use `register_filter_id` as `customer_id` for the Approval & Return Register; other reports use the numeric customer id. URL-encode query values.

`GET /company/branch-reports/{report}?report_branches=all&from_date=2026-10-01&to_date=2026-10-14`

Report keys:
- `sales-summary`
- `purchase-receiver-summary`
- `stock-position`
- `worker-loss`
- `approval-outstanding`
- `outstanding-amount`
- `barcode-history`
- `approval-summary`
- `approval-return-register`

These share the web report calculations and filters. Most return DataTables JSON (`draw`, `recordsTotal`, `recordsFiltered`, `data`, and report-specific totals). Pagination uses `start=0&length=25` (maximum 100). Global search uses `search[value]=text`. Display fields may contain formatted numbers or web link HTML; use raw identifier/name fields in native UI, not web links.

Approval summary/register return `success`, `heading`, and `data` containing `rows` (Laravel paginator), `metrics`, `totals`. Use `page`; register also accepts `per_page` (10/25/50/100), plain `search`, `voucher`, and `entry_type` (`Approval`, `Approval Return`, `Sale from Approval`). Summary uses 25 rows per page. Worker loss's ledger mode returns `data.ledger` when requested by its existing web filters.

### Downloads

Append `export=pdf` or `export=csv` to the same endpoint and keep all branch/date/customer filters. Response is a file, not JSON. Approval Summary supports CSV only, matching the website. PDF headings show selected names and merged reports include branch labels where supported by the web templates.

### Approval register voucher details

`GET /company/branch-report-details/return/45?report_branches=all`

Type: `approval`, `return`, or `sale`; id is the source record ID. Optional `approval_no` restricts the voucher's lines to one approval. Response: `success`, `data.number`, `data.date`, `data.type`, `data.lines` (source item, related approval item, amount). The app can calculate quantity and weight summaries and filter labels/barcodes from these lines. Foreign/out-of-scope vouchers are rejected.

## Login and errors

Existing `POST /company/login` supports email/password and optional `company_slug`. Existing OTP setup and verification remain required. Inactive parents/branches are rejected at login, OTP verification and protected API access. This change does not allow repeated user email addresses.

- 401: missing/invalid authentication.
- 403: forbidden scope, role, inactive company or user.
- 404: unknown/out-of-scope branch or voucher.
- 422: validation errors, duplicate email/name, third branch, unsupported export.

Laravel validation response example:
```json
{"message":"Branch limit reached. You can create up to 2 branches. Please contact administration for additional branches.","errors":{"branch_limit":["Branch limit reached. You can create up to 2 branches. Please contact administration for additional branches."]}}
```

## Deploy

Upload new API controllers `CompanyBranchApiController.php`, `BranchReportApiController.php`; updated `Company/BranchController.php`, `Api/AuthController.php`, `CompanyBranches.php`, and `routes/api.php`. These depend on the previously delivered branch/report services, web ReportController, dashboard controller and PDF templates. Deploy those existing changes together if not already uploaded.

No additional migration for this update; the existing company branches migration must already be installed. Run `php artisan optimize:clear`. The mobile app must call these endpoints and render controls; this repository change does not update a separate mobile app codebase.
