# Received amount checkbox rule

Unchecked Payment adjustment: retain/display received cash but do not subtract it from this bill's pending debit. Checked: subtract received cash (net of refunds). For a bill of Rs 1,800 with Rs 500 received, pending is Rs 1,800 unchecked and Rs 1,300 checked. This does not create another receipt or convert recorded receipts into advance credit.

Upload together:
- app/Models/Sale.php
- app/Http/Controllers/Company/SaleController.php
- app/Http/Controllers/Api/SaleApiController.php
- app/Http/Controllers/Company/ReportController.php
- app/Http/Controllers/Api/ReportApiController.php
- resources/views/company/sales/create.blade.php
- resources/views/company/sales/show.blade.php
- resources/views/company/sales/invoice_pdf.blade.php
- resources/views/company/reports/pdf/outstanding_amount.blade.php

Run `php artisan view:clear`. No migration. Existing bills follow their saved checkbox state; save after changing the checkbox to update settlement entries.
