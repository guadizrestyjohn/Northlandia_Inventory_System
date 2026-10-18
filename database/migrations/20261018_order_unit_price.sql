-- Northlandia Inventory System: store the agreed VAT-inclusive price per piece.
-- Target: MariaDB 10.4.32. Test on a disposable database before deployment.
-- Existing Orders intentionally remain NULL; do not infer historical prices.
-- Apply after 20261018_order_product_type_inventory_link.sql when deploying
-- against a database that also needs the Ready-Made / Custom-Made Order fields.

ALTER TABLE orders
    ADD COLUMN unit_price DECIMAL(10,2) NULL DEFAULT NULL AFTER quantity;

-- Rollback (only after review):
-- ALTER TABLE orders DROP COLUMN unit_price;
-- Warning: dropping the column permanently removes Unit Prices recorded after
-- this migration. Back up the database before applying or rolling it back.
