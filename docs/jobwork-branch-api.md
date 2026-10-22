# Jobwork API branch selection

All requests require the existing Bearer token and Accept: application/json. Existing endpoint aliases remain supported. No routes or migrations added.

## List endpoints

- GET /api/labour-formulas
- GET /api/production-costs
- GET /api/production-steps
- GET /api/jobwork-issues
- GET /api/jobwork-receive (issue-linked and direct receipts)
- GET /api/jobwork-receive/direct (direct receipts only)

Without selection, the authenticated user's company is used. Main-company administrators can select their own company and authorized active branches. Branch users cannot access sibling/unrelated companies.

Example (replace IDs with IDs from GET /api/company/branches):

```http
GET /api/jobwork-receive?management_branches[]=1&management_branches[]=2
```

Example response shape (existing row fields remain; values below are illustrative):

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "voucher_no": "MJ-JI-123",
      "company_id": 2,
      "company_name": "CG Road Branch"
    }
  ],
  "selected_company_ids": [1, 2]
}
```

## One company for actions and form options

Use company_id for show, create, update, delete, single-voucher PDF, raw stock and form options. Never send both company_id and management_branches. Mutations reject multiple companies with 422. Unauthorized branches return 403. A record outside the selected company is not found.

```http
GET /api/jobwork-issues/123?company_id=2
GET /api/jobwork-issues/raw-stock?company_id=2
GET /api/production-steps/options?company_id=2
GET /api/jobwork-receive/direct/options?company_id=2
GET /api/jobwork-receive/pdf/123?company_id=2
PUT /api/labour-formulas/15
```

Example PUT JSON:

```json
{
  "company_id": 2,
  "name": "Branch labour formula",
  "status": true
}
```

For other create/update requests, keep the existing module payload and add company_id. Jobwork Issue action URLs now include the record's company_id. Use each returned row's company_id for other record actions. Record IDs for direct receipts and issue-linked receipts belong to different resources; keep using the correct existing route.

## PDF / Excel

Every list endpoint supports export=pdf or export=excel, with the same branch selection and existing filters:

```http
GET /api/jobwork-issues?management_branches[]=1&management_branches[]=2&export=pdf
GET /api/jobwork-receive?management_branches[]=1&management_branches[]=2&export=excel
GET /api/production-steps?company_id=2&export=excel
```

Exports return binary PDF/XLSX, not JSON. Flutter must request bytes and save the response. Reports contain the branch heading and Company / Branch column. Existing /api/jobwork-issues/export/pdf and /export/excel endpoints now use this same multi-branch export; Excel now returns XLSX instead of the previous CSV. Existing filters remain supported per endpoint.

## Upload files

- `app/Http/Controllers/Api/LabourFormulaApiController.php`
- `app/Http/Controllers/Api/ProductionCostApiController.php`
- `app/Http/Controllers/Api/ProductionStepApiController.php`
- `app/Http/Controllers/Api/JobworkIssueApiController.php`
- `app/Http/Controllers/Api/JobworkReceiveApiController.php`
- `app/Services/JobworkApiBranches.php`

Existing dependencies from prior web/Casting changes:

- app/Services/CompanyBranches.php
- app/Services/CastingListExport.php
- app/Services/ReportXlsx.php
- resources/views/company/casting_exports/list.blade.php

Tests updated: tests/Feature/JobworkBranchSelectionTest.php and tests/Feature/JobworkRawStockTest.php. Jobwork checks: 19 tests, 134 assertions passed. A wider CompanyBranchesTest run has two unrelated failures (missing fixture is_active column and branch-creation count mismatch).

Changes are local; deploy the files to enable this on the live server.
