# Order Management API / Postman

Import `postman/order-management.postman_collection.json` into Postman. Login is deliberately not included: use your existing company login token.

For **form-data** instead of raw JSON, import `postman/order-management-form-data.postman_collection.json` as a new standalone collection. It includes the same eight examples and no login. Its Create/Other/Update bodies use nested form-data keys such as `items[0][item_set_id]` and `new_customer[name]`. Update sends POST with `_method=PUT`, supported by the existing endpoint; keep `_method` checked. Let Postman generate Content-Type and its multipart boundary. GET filters stay in Params.

The form-data version initializes orderDate and checks required IDs before sending. Set customerId and itemSetId from Options/Search, or enter actual IDs directly in the body. Optional item pricing fields are unchecked initially to use label defaults. Import the whole collection: copying only its request folder into another collection loses its variables. This collection change requires no new backend deployment or migration.

Set collection variables:

| Variable | Value |
| --- | --- |
| `baseUrl` | Application root, such as `http://127.0.0.1:8000` or your live origin; no `/api` or trailing slash |
| `token` | Bearer token from your existing login |
| `customerId` | Customer ID returned by Options |
| `itemSetId` | Label's `item_set_id` returned by Item Search |
| `itemId` | Optional item type ID from Options |
| `orderId` | Numeric order ID; filled automatically after successful Create |
| `keyword` | HUID, QR, barcode or item name |
| `orderDate` | YYYY-MM-DD; defaults to today's date when empty |
| `fromDate`, `toDate` | Optional list range, YYYY-MM-DD |

An active Postman environment with variables of the same name overrides collection variables. Check the resolved values before sending requests. Never share a populated token.

## Endpoints

All requests use `Authorization: Bearer <token>` and, except PDF, `Accept: application/json`.

| Method | Path | Purpose / permission |
| --- | --- | --- |
| GET | `/api/order-management` | Paginated list; `view` |
| GET | `/api/order-management/options` | Customers and item types; any of `view`, `create`, `edit` |
| GET | `/api/order-management/item-search` | Available labels; any of `view`, `create`, `edit` |
| GET | `/api/order-management/{id}` | Saved details and edit data; `view` or `edit` |
| POST | `/api/order-management` | Create; `create` |
| PUT | `/api/order-management/{id}` | Full update; `edit` |
| GET | `/api/order-management/{id}/pdf` | Inline PDF; `view` |

Permission names have the `order-management-` prefix. Company admins retain access. The Sales module must be enabled. Existing single-token/device, company-active, office-access and archive checks remain in force. All records are scoped to the token's own company; cross-company selection and `management_branches` are not supported by this module.

List filters: `search`, `customer_id`, `from_date`, `to_date`, `page`, `per_page` (1–100; default 20). Search supports customer names and partial displayed order numbers, including `om`. Dates are inclusive and filters combine. Records are in `data.data`, with pagination metadata in `data`.

Item-search filters: `keyword`, optional `item_id`, repeated `exclude[]` for selected label IDs (up to 200). Returns at most 50 available final labels. Existing saved rows are available from Details; their current available quantity is returned separately from the order's selected quantity.

Item Search and Details include current label `image_url` and `wholesale_price`. Wholesale Price is the label batch price (as in the Item Set list), is null when disabled or absent, and cannot be overridden through this API. These display fields do not change saved order totals. PDF shows Image before Qty and omits the six detailed purity/rate columns. Its Wholesale Price column appears only if an included label has a positive allowed wholesale price; API image_url remains available for app previews.

`sale_price` now displays the current label's actual Sale Price, separate from `wholesale_price`. Use `billing_sale_price` for the effective per-piece price when calculating fixed-price totals (wholesale takes priority when enabled). For example, Sale Price 1200 and Wholesale Price 900 display separately while quantity 2 totals 1800. Details return the saved effective billing price in `billing_sale_price`. Existing saved orders and label prices are not rewritten by this display correction.

## Save

Send `price_mode=sale` or `price_mode=wholesale` in JSON or form-data. New orders default to `sale`; updates without this field preserve the saved mode (legacy null orders retain their previous wholesale-first behavior when enabled). Options returns `price_modes` and `default_price_mode`; Details/List/Save return `price_mode`. The choice applies to all fixed-price rows. The server selects trusted per-piece label prices, with the other available price as fallback. Wholesale mode is rejected when the company setting is disabled. API PDF shows the saved selection and its price column. This addition needs the new nullable `managed_orders.price_mode` column; it does not reprice old orders.

The collection includes JSON examples for an existing customer and `customer_id: "other"` with `new_customer`. Set the IDs and actual weights/rates before sending. Only a new customer's name is required. Email, ten-digit mobile number, city, address and six-digit pincode are optional. The customer and order are created in one transaction; validation failure does not leave a customer behind.

Each order needs `order_date`, `customer_id` and 1–200 distinct `items` containing `item_set_id` and integer `quantity`. Optional inputs: `gross_weight`, `other_weight`, `purity`, `waste_percent`, `metal_rate`, `labour_rate`, `other_amount`, item `remarks`, and order `remarks`. Omit optional pricing fields to use label defaults. The server recalculates derived amounts and controls fixed sale prices. Update sends the complete desired item array. Orders do not reserve or sell stock.

Success format: `{ "success": true, "data": ... }`; create returns HTTP 201. Details and save responses contain `number`, customer, items, totals and `pdf_url`. That PDF URL still requires the Authorization header; use **Send and Download** in Postman.

Existing errors: 401 login/session, 403 permission/company/module, 404 missing or inaccessible order, 422 validation, 423 archive lock. Validation includes `errors` keyed by field.

## Deployment

Deploy these server files together:

```
app/Http/Controllers/Api/OrderManagementApiController.php
app/Http/Controllers/Company/OrderManagementController.php
routes/api.php
```

Then clear the route cache using `php artisan route:clear`. The earlier Order Management tables/pricing migration and `2026_11_25_140000_add_price_mode_to_managed_orders.php` must be installed. The latter adds only the nullable `price_mode` column. The Postman JSON and this document are for the API consumer and do not need to be served publicly.

Order Qty can exceed available stock. It must be an integer from 1 to 1,000,000. The label must still be a final label owned by the token company; the order never reduces stock. This applies to both Create and Update.
