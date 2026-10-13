-- READ ONLY: SELECTs and session variables only. No records are changed.
-- STEP 1: Run this to identify the correct company and item-set ID.
SELECT x.id AS itemset_id, x.company_id, c.name AS company_name,
       x.qr_code, x.barcode, x.HUID, i.item_name,
       x.is_sold, x.created_at, x.updated_at
FROM item_sets x
LEFT JOIN companies c ON c.id = x.company_id
LEFT JOIN items i ON i.id = x.item_id
WHERE x.qr_code = 'MSBG386' OR x.barcode = 'MSBG386';

-- STEP 2: Replace 0 with the itemset_id from Step 1. Run the rest together.
SET @itemset_id = 0;
SET @company_id = (SELECT company_id FROM item_sets WHERE id = @itemset_id);
SET @label = 'MSBG386';

-- Current saved stock state. is_sold=0 alone does NOT prove availability:
-- check pending approvals and draft carts below too.
SELECT x.*, i.item_name, u.name AS created_by_name
FROM item_sets x
LEFT JOIN items i ON i.id=x.item_id
LEFT JOIN users u ON u.id=x.created_by
WHERE x.id=@itemset_id AND x.company_id=@company_id;

-- Voucher timeline, oldest first. Status is the CURRENT status of each row,
-- not a reconstruction of every edit it has ever had.
SELECT * FROM (
 SELECT x.created_at AS recorded_at, DATE(x.created_at) AS voucher_date,
 'ITEM CREATED' AS event, 'item_sets' AS source_table, x.id AS row_id,
 x.qr_code AS voucher_no, NULL AS party, NULL AS current_status,
 x.net_weight AS weight, NULL AS amount
 FROM item_sets x WHERE x.id=@itemset_id AND x.company_id=@company_id
 UNION ALL
 SELECT a.created_at, h.approval_date, 'APPROVAL', 'approval_items', a.id,
 h.approval_no, c.name, a.status, a.net_weight, a.total_amount
 FROM approval_items a
 JOIN approval_headers h ON h.id=a.approval_id AND h.company_id=@company_id
 LEFT JOIN customers c ON c.id=h.customer_id AND c.company_id=h.company_id
 WHERE a.itemset_id=@itemset_id OR (a.itemset_id IS NULL AND a.qr_code=@label)
 UNION ALL
 SELECT s.created_at, h.sale_date, 'SALE', 'sale_items', s.id,
 h.voucher_no, c.name, NULL, s.net_weight, s.total_amount
 FROM sale_items s
 JOIN sales h ON h.id=s.sale_id AND h.company_id=@company_id
 LEFT JOIN approval_items a ON a.id=s.approval_item_id
 LEFT JOIN customers c ON c.id=h.customer_id AND c.company_id=h.company_id
 WHERE s.itemset_id=@itemset_id OR a.itemset_id=@itemset_id
 UNION ALL
 SELECT r.created_at, h.return_date,
 CASE WHEN h.source_type='approval' THEN 'APPROVAL RETURN' ELSE 'SALES RETURN' END,
 'sale_return_items', r.id, h.return_voucher_no, c.name, NULL, NULL, r.return_amount
 FROM sale_return_items r
 JOIN sale_returns h ON h.id=r.sale_return_id AND h.company_id=@company_id
 LEFT JOIN sale_items s ON s.id=r.sale_item_id
 LEFT JOIN approval_items a ON a.id=COALESCE(r.approval_item_id,s.approval_item_id)
 LEFT JOIN sales sh ON sh.id=s.sale_id AND sh.company_id=h.company_id
 LEFT JOIN approval_headers ah ON ah.id=a.approval_id AND ah.company_id=h.company_id
 LEFT JOIN customers c ON c.id=COALESCE(ah.customer_id,sh.customer_id) AND c.company_id=h.company_id
 WHERE s.itemset_id=@itemset_id OR a.itemset_id=@itemset_id
    OR (a.itemset_id IS NULL AND a.qr_code=@label)
 UNION ALL
 SELECT v.created_at, h.voucher_date, h.entry_type, 'customer_advance_voucher_items', v.id,
 h.voucher_no, c.name, NULL, v.net_weight, v.total_amount
 FROM customer_advance_voucher_items v
 JOIN customer_advance_vouchers h ON h.id=v.voucher_id AND h.company_id=@company_id
 LEFT JOIN customers c ON c.id=h.customer_id AND c.company_id=h.company_id
 WHERE v.itemset_id=@itemset_id OR (v.itemset_id IS NULL AND v.label_code=@label)
) history ORDER BY recorded_at, source_table, row_id;

-- Current pending approvals: identifies the party holding an approval item.
SELECT h.approval_no, h.approval_date, c.name AS customer, a.*
FROM approval_items a
JOIN approval_headers h ON h.id=a.approval_id AND h.company_id=@company_id
LEFT JOIN customers c ON c.id=h.customer_id AND c.company_id=h.company_id
WHERE (a.itemset_id=@itemset_id OR (a.itemset_id IS NULL AND a.qr_code=@label))
  AND a.status='pending';

-- Draft carts are NOT posted vouchers.
SELECT 'APPROVAL CART' AS draft_type, c.* FROM approval_carts c
WHERE c.company_id=@company_id AND c.itemset_id=@itemset_id;
SELECT 'SALE CART' AS draft_type, c.* FROM sale_carts c
LEFT JOIN approval_items a ON a.id=c.approval_item_id
WHERE c.company_id=@company_id AND (c.itemset_id=@itemset_id OR a.itemset_id=@itemset_id);
SELECT 'RETURN CART' AS draft_type, c.* FROM return_carts c
LEFT JOIN sale_items s ON s.id=c.sale_item_id
LEFT JOIN approval_items a ON a.id=c.approval_item_id
WHERE c.company_id=@company_id AND (s.itemset_id=@itemset_id OR a.itemset_id=@itemset_id);

-- Supplementary text search: may include partial-code matches; inspect results.
-- Only finds edits/deletions if their retained description contains this code.
SELECT created_at, module, action, description, user_id
FROM audit_logs
WHERE company_id=@company_id AND description LIKE CONCAT('%',@label,'%')
ORDER BY created_at, id;
