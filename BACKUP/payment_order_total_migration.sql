-- Add the order total required by payment summaries and release validation.
-- Existing orders receive 0.00 because their historical totals are not stored.
-- Back up the database and enter known totals for existing orders before
-- relying on payment status or release validation for those orders.

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS order_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER specifications;
