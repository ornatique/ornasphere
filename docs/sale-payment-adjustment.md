# Sale payment adjustment

With Payment adjustment enabled, matching metal credit covers priced metal weight first. Only uncovered priced metal is charged in cash. Cash credit covers the remaining cash payable after receipts/refunds, up to the available credit. Excess advance remains available. Cash is not converted into metal.

Without a metal rate, the item posts its fine weight as a metal debit even if Payment adjustment is unchecked. Its cash charge is labour plus other charges. Matching metal credits offset the debit in the customer's ledger.

New sales default to Payment adjustment unchecked. The web form submits an explicit zero when unchecked, and one when checked. Disabling it leaves cash advances and priced-metal credits unused. Existing sales retain their saved setting. API updates that omit the field preserve the saved setting; send `use_silver_balance: false` to disable it.

Sale `net_total` is cash payable after applied cash credit. `received_amount` remains actual money received. PDF Total Amount shows cash charges before advance; Advance Adjusted is separate. API `advance_usage.cash_used` identifies applied cash. Item totals reflect cash charges after metal settlement.

Opening/advance entries are retained. Saving replaces only the sale's settlement entries; a zero cash settlement entry marks the new calculation. No database migration is required. Existing bills are not bulk rewritten: review and save an existing sale to apply the new rules.

## Upload together

- app/Services/SaleSettlement.php (new; required)
- app/Http/Controllers/Company/SaleController.php
- app/Http/Controllers/Api/SaleApiController.php
- resources/views/company/sales/create.blade.php
- resources/views/company/sales/invoice_pdf.blade.php

The previously supplied app/Services/PersonOpeningBalance.php must also be current. After upload run `php artisan view:clear`.

Validation: focused settlement/PDF tests cover partial metal coverage, cash credit, no-rate lines with stale metal amounts, receipts, disabled adjustment, and repeated saves.
