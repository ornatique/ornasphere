ALTER TABLE `item_sets`
ADD COLUMN `sale_price` DECIMAL(15,2) NULL;

ALTER TABLE `sale_items`
ADD COLUMN `sale_price` DECIMAL(15,2) NULL;

ALTER TABLE `approval_items`
ADD COLUMN `sale_price` DECIMAL(15,2) NULL;

ALTER TABLE approval_items
ADD COLUMN sale_price DECIMAL(15,2) NULL AFTER total_amount;

this one i ass tehsale_price 

ALTER TABLE `item_sets`
  ADD COLUMN `quantity` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `net_weight`,
  ADD COLUMN `available_quantity` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `quantity`;

ALTER TABLE `approval_items`
  ADD COLUMN `qty` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `item_id`;

ALTER TABLE `sale_carts`
  ADD COLUMN `qty` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `itemset_id`;

ALTER TABLE `approval_carts`
  ADD COLUMN `qty` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `itemset_id`;

ALTER TABLE `return_carts`
  ADD COLUMN `qty` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `approval_item_id`;

ALTER TABLE `sale_return_items`
  ADD COLUMN `qty` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `return_amount`;


UPDATE `item_sets`
SET `available_quantity` =
  CASE WHEN `is_sold` = 1 THEN 0 ELSE `quantity` END;

UPDATE `sale_return_items` AS sri
JOIN `sale_items` AS si ON si.`id` = sri.`sale_item_id`
SET sri.`qty` = GREATEST(1, COALESCE(si.`qty`, 1))
WHERE sri.`qty` = 1;

UPDATE `sale_return_items` AS sri
JOIN `approval_items` AS ai ON ai.`id` = sri.`approval_item_id`
SET sri.`qty` = GREATEST(1, COALESCE(ai.`qty`, 1))
WHERE sri.`qty` = 1;

ALTER TABLE `companies`
    ADD COLUMN `wholesale_price_enabled` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `item_sets`
    ADD COLUMN `wholesale_price` DECIMAL(15,2) NULL AFTER `sale_price`;


ALTER TABLE `worker_access_logs`
ADD COLUMN `allowed_radius_meters` INT UNSIGNED NULL
AFTER `distance_meters`;


ALTER TABLE `password_set_tokens`
ADD COLUMN `used_at` TIMESTAMP NULL DEFAULT NULL AFTER `expires_at`;

ALTER TABLE `sales`
ADD COLUMN `price_mode` VARCHAR(16) NOT NULL DEFAULT 'sale'
AFTER `show_previous_bills`;

CREATE TABLE managed_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,


    PRIMARY KEY (id),
    INDEX managed_orders_company_id_order_date_index
        (company_id, order_date),


    CONSTRAINT managed_orders_company_id_foreign
        FOREIGN KEY (company_id) REFERENCES companies (id),


    CONSTRAINT managed_orders_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;



//changes  on 25/11/2026
CREATE TABLE managed_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    managed_order_id BIGINT UNSIGNED NOT NULL,
    item_set_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    huid VARCHAR(255) NULL,
    qr_code VARCHAR(255) NULL,
    quantity INT UNSIGNED NOT NULL,
    gross_weight DECIMAL(15,3) NOT NULL DEFAULT 0,
    net_weight DECIMAL(15,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,


    PRIMARY KEY (id),
    UNIQUE KEY managed_order_items_managed_order_id_item_set_id_unique
        (managed_order_id, item_set_id),


    CONSTRAINT managed_order_items_company_id_foreign
        FOREIGN KEY (company_id) REFERENCES companies (id),


    CONSTRAINT managed_order_items_managed_order_id_foreign
        FOREIGN KEY (managed_order_id) REFERENCES managed_orders (id)
        ON DELETE CASCADE,


    CONSTRAINT managed_order_items_item_set_id_foreign
        FOREIGN KEY (item_set_id) REFERENCES item_sets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;




ALTER TABLE managed_order_items
    ADD COLUMN other_weight DECIMAL(24,3) NULL,
    ADD COLUMN purity DECIMAL(24,3) NULL,
    ADD COLUMN waste_percent DECIMAL(24,3) NULL,
    ADD COLUMN net_purity DECIMAL(24,3) NULL,
    ADD COLUMN total_fine_weight DECIMAL(24,3) NULL,
    ADD COLUMN metal_rate DECIMAL(24,2) NULL,
    ADD COLUMN metal_amount DECIMAL(24,2) NULL,
    ADD COLUMN labour_rate DECIMAL(24,2) NULL,
    ADD COLUMN labour_amount DECIMAL(24,2) NULL,
    ADD COLUMN other_amount DECIMAL(24,2) NULL,
    ADD COLUMN sale_price DECIMAL(24,2) NULL,
    ADD COLUMN total_amount DECIMAL(24,2) NULL,
    ADD COLUMN remarks VARCHAR(1000) NULL;

ALTER TABLE managed_orders ADD COLUMN price_mode VARCHAR(16) NULL;
