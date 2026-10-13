# QR Making deployment

Upload these files, preserving directories:

- app/Models/MarketingQr.php
- app/Http/Controllers/Company/QrMakingController.php
- app/Http/Middleware/CompanyRoutePermission.php
- app/Http/Controllers/Company/CompanyPermissionController.php
- app/Http/Controllers/Company/CompanyRoleController.php
- resources/views/company_layout/sidebar.blade.php
- resources/views/company/qr_making/ (all four Blade files)
- routes/web.php
- database/migrations/2026_10_06_000001_create_marketing_qrs_table.php

Run from the Laravel project directory:

```sh
php artisan migrate --path=database/migrations/2026_10_06_000001_create_marketing_qrs_table.php --force
php artisan optimize:clear
```

Open Permissions or Roles to register the QR Making permissions, then assign View, Create, Edit and Delete as needed. Company admins have access automatically. The application already requires endroid/qr-code and dompdf; no new Composer packages are needed.

Set APP_URL to the public HTTPS domain. Keep that domain and the public /q/{token} route available for printed QR codes. Logo files use private local storage under storage/app/private/qr-logos (or the configured local disk root). Ensure the local storage disk is writable. No storage symlink is required for these logos.

Web codes redirect to the saved URL. App codes redirect Android/iPhone visitors to the configured store; other devices see both buttons. Updating a QR retains its token. Deleting a QR invalidates its printed copies. Preview on a new unsaved record is for appearance only; save and scan the downloaded code before printing, especially at small sizes or with logos.

Local validation includes company isolation, permissions, URL validation, destination edits, logo add/remove, SVG and PDF generation. The migration has not been run on the production server by Codex.

Web QR update: Web codes now encode the website URL directly. Download again after upload. Old printed codes retain their original content; direct Web codes cannot be changed or disabled by editing/deleting their ERP record. App codes continue using the token redirect.
