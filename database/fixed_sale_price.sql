-- Alternative to the Laravel migration. Run ONE method only, after a backup.
ALTER TABLE item_sets ADD COLUMN sale_price DECIMAL(15,2) NULL;
ALTER TABLE sale_items ADD COLUMN sale_price DECIMAL(15,2) NULL;
ALTER TABLE approval_items ADD COLUMN sale_price DECIMAL(15,2) NULL;
-- NULL keeps existing weight-based behavior. Positive amounts are fixed per unit.
