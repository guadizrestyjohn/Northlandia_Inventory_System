-- Northlandia Inventory System: persist Ready-Made and Custom-Made Order selection.
-- Target: MariaDB 10.4.32, consistent with the existing project migrations.
-- Review and test on a disposable database before applying to any live database.
-- Existing Orders are intentionally left NULL / NULL (legacy, unclassified).

ALTER TABLE orders
    ADD COLUMN product_type ENUM('Ready-Made', 'Custom-Made') NULL AFTER product_name,
    ADD COLUMN inventory_id INT(11) NULL AFTER product_type,
    ADD KEY orders_inventory_id_idx (inventory_id),
    ADD CONSTRAINT orders_inventory_id_fk
        FOREIGN KEY (inventory_id) REFERENCES inventory_items (inventory_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT orders_product_type_inventory_chk CHECK (
        (product_type IS NULL AND inventory_id IS NULL)
        OR (product_type = 'Ready-Made' AND inventory_id IS NOT NULL)
        OR (product_type = 'Custom-Made' AND inventory_id IS NULL)
    );
