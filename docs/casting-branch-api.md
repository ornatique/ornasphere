# Casting APIs: company/branch selection

These changes are local and must be uploaded before testing the live site.
Use existing authentication: `Authorization: Bearer <token>` and `Accept: application/json`.

## Branch dropdown

`GET /api/company/branches`

Use data.companies (id, name, effective_active). Disable inactive options.
Only a main-company admin can access its active child branches. Branch users remain in their own workspace.

## List endpoints

| Casting submenu | GET endpoint |
|---|---|
| Buch | /api/vacuum-buchs |
| Process | /api/vacuum-processes |
| Vacuum | /api/vouchers |
| Casting Heating | /api/casting-heating |
| Casting Metal Issue | /api/casting-metal-issue |
| Casting Receive | /api/casting-receive |
| Tree Cutting Issue Office | /api/tree-cutting-office |
| Tree Cutting Issue | /api/tree-cutting-issue |
| Tree Cutting Receive | /api/tree-cutting-receive |
| Casting Sorting | /api/casting-sorting |
| Voucher History | /api/voucher-history |

Append `?management_branches[]=1&management_branches[]=2` for combined lists.
Replace IDs with actual values from the branch dropdown. A single branch may use `?company_id=2`.
No selection preserves the original logged-in-company behavior. Existing list route aliases also work.
Records are grouped by company, retaining each company's existing sort order and calculations.
The normal stage lists retain their existing date defaults (typically today); pass from_date/to_date for older records.

Example:

```
GET /api/casting-heating?management_branches[]=1&management_branches[]=2&from_date=2026-10-01&to_date=2026-10-31
```

Response shape (illustrative; existing fields are retained):

```json
{
  "success": true,
  "selected_company_ids": [1, 2],
  "data": [
    {
      "id": 123,
      "company_id": 2,
      "company_name": "Branch Two",
      "voucher_no": "FI-VV-001",
      "total_pcs": 5,
      "in_bhati_pcs": 3
    }
  ]
}
```

Voucher History retains its existing `data.vouchers`, `data.selected_voucher_id`, `data.voucher` and `data.history` fields.
Its options endpoint `/api/voucher-history/vouchers` also supports multiple branches.
History options and selected voucher include company_id/company_name.

## Combined PDF and Excel

Add `export=pdf` or `export=excel` to any list endpoint above:

```
GET /api/casting-heating?management_branches[]=1&management_branches[]=2&export=pdf
GET /api/casting-heating?management_branches[]=1&management_branches[]=2&export=excel
GET /api/voucher-history?management_branches[]=1&management_branches[]=2&export=excel
```

The response is a binary PDF or XLSX download, not JSON. In Flutter/Dio use `ResponseType.bytes`
or `download()` and save using the corresponding extension. Check the HTTP status before saving;
validation and authorization failures still return JSON.
All matching rows are exported, with Company / Branch on every row and selected companies in the heading.
Existing list filters are applied. History exports all matching vouchers and stage details in the selected branches,
without the dropdown limit; voucher_id controls the on-screen detail only, not the combined export.
Individual voucher PDF endpoints still return just that voucher.
The legacy `/voucher-history/{id}/excel` endpoint retains its existing CSV format.

## Single-company actions

For create/update/delete/show/PDF and form options, pass ONE company_id. Keep the endpoint's existing payload.
Never send multiple branches for a mutation. Use company_id from the row being acted on.

```
GET /api/casting-heating/123?company_id=2
GET /api/casting-heating/123/pdf?company_id=2
PUT /api/casting-heating/123?company_id=2
GET /api/vacuum-buchs/options?company_id=2
GET /api/vacuum-processes/options?company_id=2
```

Example JSON for updating a process (`PUT /api/vacuum-processes/15`):

```json
{"company_id": 2, "name": "Casting Process"}
```

Single company_id may be in the query or body. Do not send both company_id and management_branches.
Invalid/empty/duplicate selection or multiple companies for an action returns 422.
Unrelated/inactive branches return 403. A record outside the selected company returns its existing 404.
These controls do not change the authenticated user's company.

## Upload these 12 files

- app/Services/CastingApiBranches.php
- app/Http/Controllers/Api/VacuumBuchApiController.php
- app/Http/Controllers/Api/VacuumProcessApiController.php
- app/Http/Controllers/Api/VacuumVoucherApiController.php
- app/Http/Controllers/Api/CastingHeatingApiController.php
- app/Http/Controllers/Api/CastingMetalIssueApiController.php
- app/Http/Controllers/Api/CastingReceiveApiController.php
- app/Http/Controllers/Api/TreeCuttingOfficeApiController.php
- app/Http/Controllers/Api/TreeCuttingIssueApiController.php
- app/Http/Controllers/Api/TreeCuttingReceiveApiController.php
- app/Http/Controllers/Api/CastingSortingApiController.php
- app/Http/Controllers/Api/VoucherHistoryApiController.php

Existing dependencies from the previous updates must also be present:
- app/Services/CompanyBranches.php
- app/Services/CastingListExport.php
- app/Services/ReportXlsx.php
- resources/views/company/casting_exports/list.blade.php

No new routes or migrations. No cache clear is required for these PHP controller/service changes.
Tests updated: tests/Feature/CastingBranchSelectionTest.php (not required for deployment).
