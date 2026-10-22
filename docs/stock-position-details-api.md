# Stock Position Details API

All requests are GET with `Authorization: Bearer <token>`. No JSON body is required.

Base: `https://ornasphere.com/api/reports/stock-position`

| Response | Path |
| --- | --- |
| JSON details | `/details` |
| PDF | `/details/pdf` |
| Excel-compatible CSV | `/details/excel` |

`/details/export/pdf` and `/details/export/excel` are also supported.

Pass these query parameters to all three endpoints:

- `item_id`: required numeric item ID from the stock summary.
- `stock_type`: optional; omitted, null or empty defaults to `all`. Use `all` to combine stock types, or `finished_item`, `customer_received`, `raw_material`, `scrap`, or `repair` to filter. Each row and export identifies its stock type. Customer filtering still applies; casting stock without a customer is excluded when a customer is selected.
- `customer_id`: optional numeric party ID from the summary row. Pass it to restrict the details to that party.
- `image_status`: `all`, `with`, or `without`; defaults to all.
- `report_branches`: optional comma-separated authorized branch slugs or `all` for the main company admin. Omission uses the authenticated company. Branch users cannot select other companies.

Example (replace IDs with the selected stock-summary row):

```text
/api/reports/stock-position/details?item_id=7&stock_type=customer_received&customer_id=132&image_status=all
/api/reports/stock-position/details/pdf?item_id=7&stock_type=customer_received&customer_id=132&image_status=all
/api/reports/stock-position/details/excel?item_id=7&stock_type=customer_received&customer_id=132&image_status=all
```

JSON contains `success`, `item_name`, `stock_type_name`, `customer_name`,
`image_counts` (before image filtering), `summary`, `count`, and `data`.
Each detail row includes `branch_name`, `company_id`, `label_code`, weights,
quantity, source date and image status, using the web report calculations.
Exports return file downloads, not JSON. Excel export is CSV, not XLSX.

Deploy `routes/api.php` and `app/Http/Controllers/Api/ReportApiController.php`,
with the existing web report controller and ReportBranches service, then run
`php artisan optimize:clear` on the server.
