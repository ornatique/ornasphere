# Person opening balances

Create/Edit Person now includes an Opening Balances section for customers and workers:
- Effective opening date.
- Cash amount (rupees, two decimals).
- Gold, silver and other metal (grams, three decimals).
- Separate Dr/Cr selection for each balance. Dr means the person owes the company; Cr means the company owes the person.

Worker metal uses net grams, matching Worker Metal Ledger. Customer metal uses fine grams, matching Receive / Return / Purchase. Enter brought-forward balances only, excluding transactions already recorded in the system.

Customer balances include these entries through the existing advance ledger. Linked workers' metal openings are included in detailed/daily Worker Metal Ledger and exports. Worker cash openings are displayed separately as dated opening amounts, not as a calculated current cash balance.

Saving again replaces only this person's opening entries. Other ledger transactions remain intact. Zero removes the corresponding opening. An opening dated before the report From Date contributes to its opening row; one within the period appears as an entry. Entries later than To Date are excluded.

## API

Existing customer create/update requests accept this optional object. Customer show/create/update responses include it:

```json
{
  "opening": {
    "date": "2026-10-01",
    "cash": 50000,
    "cash_side": "cr",
    "gold": 0,
    "gold_side": "dr",
    "silver": 500,
    "silver_side": "dr",
    "other": 0,
    "other_side": "dr"
  }
}
```

All fields inside `opening` are required when supplied. Omit the object to preserve existing openings, including for older API clients. Company scope comes from the existing authenticated controller context.

## Deployment

No new migration. Requires the existing `customer_advance_ledgers` table and `job_workers.person_id` relationship.

Upload these changed/new files together:
- app/Services/PersonOpeningBalance.php
- app/Services/WorkerMetalLedger.php
- app/Http/Controllers/Company/CustomerController.php
- app/Http/Controllers/Api/CustomerApiController.php
- resources/views/company/customers/create.blade.php
- resources/views/company/customers/edit.blade.php
- resources/views/company/customers/partials/opening_balance.blade.php
- resources/views/company/reports/partials/worker_metal_ledger.blade.php

Clear compiled views with `php artisan view:clear` after deployment.
