# QR Making API

All endpoints require the existing company login Bearer token and an active company.
Send `Authorization: Bearer YOUR_TOKEN` and `Accept: application/json`.
Company is taken from the signed-in user; do not send a company ID or slug.
Company admins have access; staff need qr-making-view/create/edit/delete for the corresponding operation.

| Method | Path | Purpose |
|---|---|---|
| GET | /api/qr-making | List; optional search, page, per_page (1–100) |
| GET | /api/qr-making/{id} | Details |
| POST | /api/qr-making | Create |
| PUT or POST | /api/qr-making/{id} | Update; POST supports logo upload |
| DELETE | /api/qr-making/{id} | Delete and invalidate printed link |
| POST | /api/qr-making/preview | Preview; optional id for existing QR (requires edit permission) |
| GET | /api/qr-making/{id}/image | SVG preview image |
| GET | /api/qr-making/{id}/svg | SVG download |
| GET | /api/qr-making/{id}/pdf | PDF download named after QR title |

## Create, update and preview fields

Send multipart/form-data when uploading a logo, otherwise JSON is supported.
Updates require the full form fields.

```json
{
  "title": "Ornatique Silver",
  "type": "web",
  "size": 350,
  "with_logo": 0,
  "web_url": "https://example.com"
}
```

- type: web or app.
- size: 50, 100, 150, 250, 350, 500.
- web: web_url is required (http/https).
- app: android_url and ios_url are required (https).
- with_logo: 1 to use a logo, 0 to remove/omit it.
- logo: JPG/PNG/WebP, max 2 MB, max 4000×4000. Required when enabling a logo without an existing saved logo. Omit on update to keep the existing logo.
- preview id: existing QR ID, optional. An unsaved preview is for appearance only.

Create returns HTTP 201, update/details return 200: `{ "success": true, "data": { ... } }`.
List data is a Laravel pagination object containing data, current_page, total, etc.
Preview returns `{ "image": "data:image/svg+xml;base64,..." }`.
Downloads return binary PDF/SVG, not JSON. Image/download URLs also require the Bearer token; fetch with authentication before displaying in an app.
The public qr_url is the scan destination and does not require authentication.
Validation failures return 422 with field errors; missing permission 403; missing/other-company QR 404.

## Upload for this update

- app/Http/Controllers/Company/QrMakingController.php
- app/Http/Controllers/Api/QrMakingApiController.php
- app/Http/Controllers/Api/PermissionApiController.php
- app/Http/Controllers/Api/RoleApiController.php
- resources/views/company/qr_making/index.blade.php
- routes/api.php

Run `php artisan optimize:clear` after upload. No new migration is required.

Web QR update: newly generated Web codes encode web_url directly, and qr_url returns that website URL. App codes retain the public device redirect. Existing printed codes do not change; download and print again. Editing or deleting a direct Web QR record cannot change an already printed code.
