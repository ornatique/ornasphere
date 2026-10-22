# Users, Persons and Roles: branch selection API

All endpoints require the existing Bearer token and `Accept: application/json`.
These are local changes; deploy the changed PHP files before testing the live site.

## Dropdown options

`GET /api/company/branches`

Use `data.companies` for dropdown options (`id`, `name`, `is_main`, `effective_active`).
Disable options with `effective_active: false`. Only a main-company administrator can select its branches.

## Combined lists

```
GET /api/users?management_branches[]=1&management_branches[]=2
GET /api/customers?management_branches[]=1&management_branches[]=2
GET /api/roles?management_branches[]=1&management_branches[]=2
```

Replace 1 and 2 with actual company IDs from the dropdown API. Persons use `/customers`.
Omitting selection retains the existing logged-in company scope. For one company, use `?company_id=2`.
Existing list aliases behave the same. The POST roles list endpoints accept JSON:

```json
{"management_branches": [1, 2]}
```

Response shape (illustrative role row; existing role permission fields are also retained):

```json
{
  "success": true,
  "data": [
    {"id": 15, "name": "Billing", "company_id": 2, "company_name": "Branch Two", "users_count": 3}
  ],
  "selected_company_ids": [1, 2]
}
```

Users retain the legacy `seat` object for the logged-in company. New `seats` contains company_id,
company_name, max_users and current_users per selected company. Never add all branches' users
against one company's seat limit. User list responses omit 2FA secrets, recovery codes and session credentials.

## Create, view, edit, delete and user actions

Use one `company_id` for the destination/owner. No bulk mutations are performed.
For example, append `?company_id=2` to the existing endpoint, or include it in the JSON body:

```json
{"company_id": 2, "name": "Billing", "permissions": []}
```

The example above is for `POST /api/roles`. Other actions retain their existing required fields.
For editing/deleting, use the `company_id` returned on the selected row:

```
GET /api/roles/15?company_id=2
PUT /api/roles/15?company_id=2
DELETE /api/roles/15?company_id=2
PUT /api/update_users/123?company_id=2
POST /api/users/123/reset-2fa?company_id=2
POST /api/users/123/toggle-status?company_id=2
PUT /api/customers/191?company_id=2
```

New users: `POST /api/create_users?company_id=2`; new persons: `POST /api/customers?company_id=2`.
Branch-specific form options: `GET /api/roles?company_id=2`,
`GET /api/category-persons?company_id=2`, `GET /api/permissions?company_id=2`.
The category and permission change covers their list endpoints only.

## Validation

- Selection must contain accessible active companies only; unauthorized/inactive selections return 403.
- Branch users and ordinary staff cannot select another company.
- Empty, invalid or duplicate branch IDs return 422.
- Sending both company_id and management_branches returns 422.
- Actions with multiple selected companies return 422; send the row's single company_id.
- An ID that does not belong to the selected company returns the existing not-found response.
- No database migration or new route is required.

## Deployment files

- app/Services/CompanyBranches.php
- app/Http/Controllers/Api/CompanyUserController.php
- app/Http/Controllers/Api/CustomerApiController.php
- app/Http/Controllers/Api/RoleApiController.php
- app/Http/Controllers/Api/CategoryPersonController.php
- app/Http/Controllers/Api/PermissionApiController.php

Tests: tests/Feature/CompanyBranchesTest.php. This document and tests are not needed for production runtime.
