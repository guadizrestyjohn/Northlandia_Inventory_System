-- Phase 3: support a Job-Order-free Ready-Made Customer Release.
-- Target the current MariaDB orders and job_order_releases structures.
-- This migration is intentionally not executed automatically.
-- Existing Orders retain NULL approval values; existing release rows are preserved.

ALTER TABLE orders
    ADD COLUMN customer_approval ENUM('Pending', 'Approved', 'Rejected') NULL DEFAULT NULL
        AFTER inventory_id;

ALTER TABLE job_order_releases
    MODIFY COLUMN job_order_id INT(11) NULL;

-- Rollback guidance (review first):
-- Reverting the release column to NOT NULL is safe only when no Ready-Made
-- releases have been recorded with a NULL job_order_id:
--   SELECT COUNT(*) FROM job_order_releases WHERE job_order_id IS NULL;
-- If the count is nonzero, do not run a NOT NULL rollback; preserve the release
-- history and obtain a reviewed data migration plan instead.
-- The orders.customer_approval column may be dropped only after confirming no
-- Ready-Made release depends on it and taking a verified backup.
