# Item Management: web and API branches

## Web

Items List, Label Config, Label Items, Label Printing and Other Charges now support the shared searchable multiple-company/branch selector. Click View to load selected companies. Lists show Company / Branch. Edit/delete/image actions target the record owner, including the Label Items edit modal. New records belong to the company named on the current page.

PDF/Excel report buttons export all matching filtered rows with a branch heading and Company / Branch column. Label Items bulk batches remain grouped by company, item and date. Label Printing selection is carried through preview and print; only selected authorized stock records are marked printed. Physical sticker templates retain their existing dimensions; the branch-labelled report export is separate from sticker printing.

## API selection

Use the existing Bearer token and Accept: application/json. Get selectable company IDs from GET /api/company/branches.

Combined lists:

```http
GET /api/items?management_branches[]=1&management_branches[]=2
GET /api/label-configs?management_branches[]=1&management_branches[]=2
GET /api/other-charges?management_branches[]=1&management_branches[]=2
GET /api/item-sets/qr-list?management_branches[]=1&management_branches[]=2&per_page=100
GET /api/item-sets/bulk-list?management_branches[]=1&management_branches[]=2
```

Without selection, the authenticated user's company is used. Main-company admins may select their own active branches. Unrelated companies are rejected. Use company_id=2 for one branch. Do not send both company_id and management_branches.

Example response (illustrative IDs/values; existing item fields are preserved):

```json
{
  "success": true,
  "data": [
    {"id": 42, "item_name": "Silver Chain", "company_id": 2, "company_name": "CG Road Branch"}
  ],
  "selected_company_ids": [1, 2],
  "available_items": []
}
```

Label Config keeps available_items, with company identity on each option. QR and bulk lists retain their existing status:true response key. QR pagination is global across all selected companies:

```json
{
  "status": true,
  "selected_company_ids": [1, 2],
  "data": [
    {"id": 77, "company_id": 2, "company_name": "CG Road Branch", "qr_code": "CG-077"}
  ],
  "pagination": {"current_page": 1, "per_page": 100, "total": 1, "last_page": 1, "has_more": false}
}
```

Bulk rows include company_id, company_name and details_params.company_id. Image-action URLs carry company_id.

## Actions and drafts

Show, create, update, delete, draft loading/saving/finalization, image actions, and charge options/calculation target exactly one company. Multiple-company actions return 422. Keep the existing payload and add company_id in JSON or the query string:

```http
GET /api/items/42?company_id=2
PUT /api/update_items/42?company_id=2
GET /api/itemsets_show/77?company_id=2
POST /api/item-sets
```

Example draft request:

```json
{"company_id": 2, "item_id": 42}
```

## Reports and printing

Add export=pdf or export=excel to any list URL above. Existing filters are retained. Exports return binary PDF/XLSX, not JSON, and contain all matching records regardless of pagination.

```http
GET /api/item-sets/qr-list?management_branches[]=1&management_branches[]=2&export=excel
GET /api/items?management_branches[]=1&management_branches[]=2&export=pdf
POST /api/item-sets/print/pdf
```

New sticker printing request:

```json
{
  "management_branches": [1, 2],
  "ids": [77, 78],
  "label_format": "compact",
  "start_position": 1
}
```

Use actual finalized label IDs from qr-list. Up to 500 distinct IDs are accepted. label_format is compact or double_barcode; start_position is 1–22. The response is PDF bytes; labels are marked printed and first-print dates retained. IDs outside the selected companies or unfinalized labels return 422. In Flutter use a bytes response type and save the file.

## Upload files

- `app/Http/Controllers/Company/ItemController.php`
- `app/Http/Controllers/Company/LabelConfigController.php`
- `app/Http/Controllers/Company/ItemSetController.php`
- `app/Http/Controllers/Company/OtherChargeController.php`
- `app/Http/Controllers/Api/ItemController.php`
- `app/Http/Controllers/Api/LabelConfigController.php`
- `app/Http/Controllers/Api/ItemSetController.php`
- `app/Http/Controllers/Api/OtherChargeController.php`
- `app/Http/Controllers/Api/ItemLabelPrintController.php`
- `app/Http/Middleware/EnsureCompanyIsActive.php`
- `app/Services/ItemManagementApiBranches.php`
- `app/Services/ItemManagementListExport.php`
- `routes/api.php`
- `resources/views/company/items/index.blade.php`
- `resources/views/company/label_config/index.blade.php`
- `resources/views/company/other_charge/index.blade.php`
- `resources/views/company/item_sets/list.blade.php`
- `resources/views/company/item_sets/qr_list.blade.php`
- `resources/views/company/item_sets/print_preview.blade.php`
- `resources/views/company_layout/management_branch_selector.blade.php`
- `resources/views/company_layout/item_management_exports.blade.php`

Existing dependencies from earlier module changes:

- app/Services/CompanyBranches.php
- app/Services/CastingListExport.php
- app/Services/ReportXlsx.php
- resources/views/company/casting_exports/list.blade.php
- resources/views/company_layout/admin.blade.php

No migration required. Deploy routes/api.php too: the sticker-print endpoint is new. Refresh a cached route table with php artisan route:cache and compile views with php artisan view:cache. Do not clear the application cache containing inactive-token responses.

## Validation

Six new branch tests passed (49 assertions): web row ownership, print selection, API pagination and bulk scope, configuration metadata, full exports, cross-company rejection, and authorized PDF printing. Existing API item receipt and Jobwork tests also passed. PHP syntax and Blade compilation passed. Live-site browser verification/deployment was not performed.
