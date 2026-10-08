-- Add a payment classification so the initial downpayment receipt is retained
-- in history but not added a second time to orders.downpayment.
-- Existing receipts default to Additional; reconcile any legacy receipt that
-- duplicates an order's downpayment before relying on balances for that order.

ALTER TABLE receipt_records
    ADD COLUMN IF NOT EXISTS payment_type
        ENUM('Downpayment', 'Additional') NOT NULL DEFAULT 'Additional'
        AFTER amount;
