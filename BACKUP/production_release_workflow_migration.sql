ALTER TABLE job_orders
    ADD COLUMN IF NOT EXISTS production_completed_at DATETIME DEFAULT NULL AFTER production_end_date,
    ADD COLUMN IF NOT EXISTS production_completed_by INT(11) DEFAULT NULL AFTER production_completed_at;

ALTER TABLE stock_transactions
    ADD COLUMN IF NOT EXISTS job_order_id INT(11) DEFAULT NULL AFTER order_id,
    ADD UNIQUE INDEX IF NOT EXISTS stock_transactions_job_order_unique (job_order_id);

SET @northlandia_stock_job_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_transactions'
      AND CONSTRAINT_NAME = 'stock_transactions_job_order_fk'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @northlandia_stock_job_fk_sql = IF(
    @northlandia_stock_job_fk = 0,
    'ALTER TABLE stock_transactions ADD CONSTRAINT stock_transactions_job_order_fk FOREIGN KEY (job_order_id) REFERENCES job_orders (job_order_id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SET @northlandia_stock_job_fk_noop = 1'
);
PREPARE northlandia_stock_job_fk_stmt FROM @northlandia_stock_job_fk_sql;
EXECUTE northlandia_stock_job_fk_stmt;
DEALLOCATE PREPARE northlandia_stock_job_fk_stmt;

SET @northlandia_production_user_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'job_orders'
      AND CONSTRAINT_NAME = 'job_orders_production_completed_by_fk'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @northlandia_production_user_fk_sql = IF(
    @northlandia_production_user_fk = 0,
    'ALTER TABLE job_orders ADD CONSTRAINT job_orders_production_completed_by_fk FOREIGN KEY (production_completed_by) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SET @northlandia_production_user_fk_noop = 1'
);
PREPARE northlandia_production_user_fk_stmt FROM @northlandia_production_user_fk_sql;
EXECUTE northlandia_production_user_fk_stmt;
DEALLOCATE PREPARE northlandia_production_user_fk_stmt;
