-- Upgrade the older northlandia_db.sql dump to the current application schema.
-- This is additive and preserves all existing Orders, Stock, and record rows.
-- Take and verify a full database backup before applying to a live database.
-- Preflight: every linked Stock Out must have a valid Order, Job Order, and user;
-- no Job Order may have multiple historical Stock Out rows.

ALTER TABLE inventory_items
    ADD COLUMN item_description VARCHAR(255) NULL AFTER item_name,
    MODIFY COLUMN size VARCHAR(40) DEFAULT NULL,
    ADD COLUMN width_cm DECIMAL(10,2) DEFAULT NULL AFTER size,
    ADD COLUMN height_cm DECIMAL(10,2) DEFAULT NULL AFTER width_cm,
    ADD COLUMN total_stocks DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER quantity;

-- The old dump has no description field. Leave those legacy descriptions blank;
-- do not infer or invent item descriptions.
UPDATE inventory_items
SET item_description = ''
WHERE item_description IS NULL;

ALTER TABLE inventory_items
    MODIFY COLUMN item_description VARCHAR(255) NOT NULL;

-- Initialize the new cumulative field from the current on-hand quantity.
UPDATE inventory_items
SET total_stocks = quantity;

-- Backfill structured dimensions only when the existing legacy size text is
-- unambiguous and explicitly expressed in centimeters. Preserve `size` as-is.
UPDATE inventory_items
SET width_cm = CAST(TRIM(SUBSTRING_INDEX(LOWER(size), 'x', 1)) AS DECIMAL(10,2)),
    height_cm = CAST(
        TRIM(REPLACE(SUBSTRING_INDEX(LOWER(size), 'x', -1), 'cm', ''))
        AS DECIMAL(10,2)
    )
WHERE size REGEXP '^[[:space:]]*[0-9]+([.][0-9]+)?[[:space:]]*[xX][[:space:]]*[0-9]+([.][0-9]+)?[[:space:]]*cm[[:space:]]*$';

CREATE TABLE job_order_outputs (
    output_id INT(11) NOT NULL AUTO_INCREMENT,
    job_order_id INT(11) NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    item_description VARCHAR(255) NOT NULL,
    width_cm DECIMAL(10,2) NOT NULL,
    height_cm DECIMAL(10,2) NOT NULL,
    reported_quantity DECIMAL(10,2) NOT NULL,
    submission_key CHAR(36) NOT NULL,
    reported_by INT(11) NOT NULL,
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    inventory_id INT(11) DEFAULT NULL,
    verification_status ENUM('Reported','Verified','Rejected') NOT NULL DEFAULT 'Reported',
    verified_by INT(11) DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    PRIMARY KEY (output_id),
    UNIQUE KEY job_order_outputs_submission_key_unique (submission_key),
    KEY job_order_outputs_job_order_idx (job_order_id),
    KEY job_order_outputs_inventory_idx (inventory_id),
    KEY job_order_outputs_reported_by_idx (reported_by),
    KEY job_order_outputs_verified_by_idx (verified_by),
    CONSTRAINT job_order_outputs_quantity_positive CHECK (reported_quantity > 0 AND reported_quantity = FLOOR(reported_quantity)),
    CONSTRAINT job_order_outputs_dimensions_positive CHECK (width_cm > 0 AND height_cm > 0),
    CONSTRAINT job_order_outputs_job_order_fk FOREIGN KEY (job_order_id) REFERENCES job_orders (job_order_id),
    CONSTRAINT job_order_outputs_inventory_fk FOREIGN KEY (inventory_id) REFERENCES inventory_items (inventory_id),
    CONSTRAINT job_order_outputs_reported_by_fk FOREIGN KEY (reported_by) REFERENCES users (user_id),
    CONSTRAINT job_order_outputs_verified_by_fk FOREIGN KEY (verified_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Retain the previous mismatch-approval schema/history. The current application
-- does not expose that workflow, and this migration inserts no approval data.
CREATE TABLE job_order_output_mismatch_approvals (
    approval_id INT(11) NOT NULL AUTO_INCREMENT,
    output_id INT(11) NOT NULL,
    order_id INT(11) NOT NULL,
    job_order_id INT(11) NOT NULL,
    inventory_id INT(11) NOT NULL,
    mismatch_type ENUM('ProductOrDimensions','QuantityVariance','ProductAndQuantity') NOT NULL,
    order_product_name_snapshot VARCHAR(100) NOT NULL,
    order_specifications_snapshot TEXT DEFAULT NULL,
    order_quantity_snapshot DECIMAL(10,2) NOT NULL,
    output_product_name_snapshot VARCHAR(100) NOT NULL,
    output_description_snapshot VARCHAR(255) NOT NULL,
    output_width_cm_snapshot DECIMAL(10,2) NOT NULL,
    output_height_cm_snapshot DECIMAL(10,2) NOT NULL,
    output_quantity_snapshot DECIMAL(10,2) NOT NULL,
    inventory_item_name_snapshot VARCHAR(100) NOT NULL,
    inventory_description_snapshot VARCHAR(255) NOT NULL,
    inventory_width_cm_snapshot DECIMAL(10,2) DEFAULT NULL,
    inventory_height_cm_snapshot DECIMAL(10,2) DEFAULT NULL,
    reason TEXT NOT NULL,
    requested_release_quantity DECIMAL(10,2) NOT NULL,
    authorized_release_quantity DECIMAL(10,2) DEFAULT NULL,
    request_key CHAR(36) NOT NULL,
    requested_by INT(11) NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decision ENUM('Pending','Approved','Rejected','Invalidated') NOT NULL DEFAULT 'Pending',
    approved_by INT(11) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    PRIMARY KEY (approval_id),
    UNIQUE KEY job_order_output_approvals_request_key_unique (request_key),
    KEY job_order_output_approvals_output_idx (output_id, decision),
    KEY job_order_output_approvals_order_idx (order_id),
    KEY job_order_output_approvals_job_order_idx (job_order_id),
    KEY job_order_output_approvals_inventory_idx (inventory_id),
    KEY job_order_output_approvals_requested_by_fk (requested_by),
    KEY job_order_output_approvals_approved_by_fk (approved_by),
    CONSTRAINT job_order_output_approvals_quantity_check CHECK (
        requested_release_quantity >= 0 AND
        (authorized_release_quantity IS NULL OR authorized_release_quantity >= 0) AND
        (approved_by IS NULL OR approved_by <> requested_by)
    ),
    CONSTRAINT job_order_output_approvals_output_fk FOREIGN KEY (output_id) REFERENCES job_order_outputs (output_id),
    CONSTRAINT job_order_output_approvals_order_fk FOREIGN KEY (order_id) REFERENCES orders (order_id),
    CONSTRAINT job_order_output_approvals_job_order_fk FOREIGN KEY (job_order_id) REFERENCES job_orders (job_order_id),
    CONSTRAINT job_order_output_approvals_inventory_fk FOREIGN KEY (inventory_id) REFERENCES inventory_items (inventory_id),
    CONSTRAINT job_order_output_approvals_requested_by_fk FOREIGN KEY (requested_by) REFERENCES users (user_id),
    CONSTRAINT job_order_output_approvals_approved_by_fk FOREIGN KEY (approved_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE job_order_releases (
    release_id INT(11) NOT NULL AUTO_INCREMENT,
    job_order_id INT(11) NOT NULL,
    order_id INT(11) NOT NULL,
    request_key CHAR(36) DEFAULT NULL,
    request_hash CHAR(64) DEFAULT NULL,
    released_by INT(11) NOT NULL,
    released_at DATETIME NOT NULL,
    PRIMARY KEY (release_id),
    UNIQUE KEY job_order_releases_job_order_unique (job_order_id),
    UNIQUE KEY job_order_releases_order_unique (order_id),
    UNIQUE KEY job_order_releases_request_key_unique (request_key),
    KEY job_order_releases_released_by_idx (released_by),
    CONSTRAINT job_order_releases_job_order_fk FOREIGN KEY (job_order_id) REFERENCES job_orders (job_order_id),
    CONSTRAINT job_order_releases_order_fk FOREIGN KEY (order_id) REFERENCES orders (order_id),
    CONSTRAINT job_order_releases_released_by_fk FOREIGN KEY (released_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE stock_transactions
    ADD COLUMN production_output_id INT(11) DEFAULT NULL AFTER job_order_id,
    ADD COLUMN release_id INT(11) DEFAULT NULL AFTER production_output_id,
    ADD COLUMN idempotency_key CHAR(36) DEFAULT NULL AFTER user_id,
    ADD KEY stock_transactions_production_output_idx (production_output_id),
    ADD KEY stock_transactions_release_idx (release_id),
    ADD UNIQUE KEY stock_transactions_idempotency_key_unique (idempotency_key);

-- Preserve existing linked releases: one historical header per Order/Job Order,
-- and attach each existing Stock Out row to that header.
INSERT INTO job_order_releases
    (job_order_id, order_id, request_key, request_hash, released_by, released_at)
SELECT st.job_order_id, st.order_id, NULL, NULL, MIN(st.user_id), MIN(st.transaction_date)
FROM stock_transactions st
WHERE st.transaction_type = 'Stock Out'
  AND st.job_order_id IS NOT NULL
  AND st.order_id IS NOT NULL
GROUP BY st.job_order_id, st.order_id;

UPDATE stock_transactions st
INNER JOIN job_order_releases r ON r.job_order_id = st.job_order_id
SET st.release_id = r.release_id
WHERE st.transaction_type = 'Stock Out'
  AND st.job_order_id IS NOT NULL
  AND st.order_id = r.order_id;

ALTER TABLE stock_transactions
    ADD CONSTRAINT stock_transactions_production_output_fk
        FOREIGN KEY (production_output_id) REFERENCES job_order_outputs (output_id),
    ADD CONSTRAINT stock_transactions_release_fk
        FOREIGN KEY (release_id) REFERENCES job_order_releases (release_id),
    DROP INDEX stock_transactions_job_order_unique,
    ADD KEY stock_transactions_job_order_idx (job_order_id);
