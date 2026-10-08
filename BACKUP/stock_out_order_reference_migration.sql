ALTER TABLE stock_transactions
    ADD COLUMN IF NOT EXISTS order_id INT(11) DEFAULT NULL AFTER inventory_id,
    ADD INDEX IF NOT EXISTS stock_transactions_order_id (order_id);

SET @northlandia_stock_order_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_transactions'
      AND CONSTRAINT_NAME = 'stock_transactions_order_fk'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @northlandia_stock_order_fk_sql = IF(
    @northlandia_stock_order_fk = 0,
    'ALTER TABLE stock_transactions ADD CONSTRAINT stock_transactions_order_fk FOREIGN KEY (order_id) REFERENCES orders (order_id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SET @northlandia_stock_order_fk_noop = 1'
);
PREPARE northlandia_stock_order_fk_stmt FROM @northlandia_stock_order_fk_sql;
EXECUTE northlandia_stock_order_fk_stmt;
