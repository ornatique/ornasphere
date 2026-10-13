# Negative raw stock

Web and API jobwork issue create/update now deduct the full issued net weight even when stock is insufficient. Existing receipt stock is allocated first; remaining weight is stored in `jobwork_raw_stock_allocations` with a null `voucher_item_id`.

Deploy the updated service, web/API report controllers, issue form and the existing web/API issue controllers (which must call `syncIssue($issue, true)`). Run:

```sh
php artisan migrate --force
php artisan optimize:clear
```

New migration: `2026_10_13_160000_allow_raw_stock_debt.php`. It makes the receipt link nullable without deleting existing allocations. Do not roll it back while debt allocations remain.

Example: zero stock minus a 100 g issue = -100 g. A linked 50 g raw return gives -50 g. A later 200 g receipt gives 150 g, without resaving the issue. Returns are capped at the issued weight for stock calculations. API request fields are unchanged; shortage responses now have `stock_deducted: true`.

Stock Position shows uncovered net grams as a separate **Raw Stock Shortage** row without a customer, alongside existing receipt rows. Customer-filtered reports exclude this unassigned shortage. These rows are net-gram adjustments (gross mirrors net; other/fine are zero), not a purity-based valuation.

Previously saved issues that skipped deduction are not automatically rewritten. Re-save those issues after deployment to record their stock deductions; issues already received require reconciliation rather than bypassing the edit guard.
