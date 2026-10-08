-- Records Management schema migration for northlandia_db (MariaDB/InnoDB).
-- Additive and re-runnable: creates dedicated record entities and copies legacy
-- records without deleting or changing business_records or operational tables.
-- Review and back up the database before running this script.

CREATE TABLE IF NOT EXISTS purchase_records (
    purchase_record_id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(50) NOT NULL,
    supplier_name VARCHAR(150) DEFAULT NULL,
    purchase_date DATE NOT NULL,
    items_details TEXT NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Recorded',
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (purchase_record_id),
    UNIQUE KEY purchase_records_reference_no (reference_no),
    KEY purchase_records_purchase_date (purchase_date),
    KEY purchase_records_created_by (created_by),
    CONSTRAINT purchase_records_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales_records (
    sales_record_id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(50) NOT NULL,
    order_id INT(11) DEFAULT NULL,
    sales_date DATE NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Recorded',
    description TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sales_record_id),
    UNIQUE KEY sales_records_reference_no (reference_no),
    KEY sales_records_order_id (order_id),
    KEY sales_records_sales_date (sales_date),
    KEY sales_records_created_by (created_by),
    CONSTRAINT sales_records_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT sales_records_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS receipt_records (
    receipt_record_id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(50) NOT NULL,
    order_id INT(11) DEFAULT NULL,
    receipt_date DATE NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    payment_type ENUM('Downpayment','Additional') NOT NULL DEFAULT 'Additional',
    status VARCHAR(30) NOT NULL DEFAULT 'Recorded',
    description TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (receipt_record_id),
    UNIQUE KEY receipt_records_reference_no (reference_no),
    KEY receipt_records_order_id (order_id),
    KEY receipt_records_receipt_date (receipt_date),
    KEY receipt_records_created_by (created_by),
    CONSTRAINT receipt_records_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT receipt_records_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS invoice_records (
    invoice_record_id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(50) NOT NULL,
    order_id INT(11) DEFAULT NULL,
    invoice_date DATE NOT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Recorded',
    description TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invoice_record_id),
    UNIQUE KEY invoice_records_reference_no (reference_no),
    KEY invoice_records_order_id (order_id),
    KEY invoice_records_invoice_date (invoice_date),
    KEY invoice_records_created_by (created_by),
    CONSTRAINT invoice_records_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (order_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT invoice_records_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bidding_document_records (
    bidding_document_record_id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    client_name VARCHAR(150) DEFAULT NULL,
    submission_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Recorded',
    description TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bidding_document_record_id),
    UNIQUE KEY bidding_document_records_reference_no (reference_no),
    KEY bidding_document_records_submission_date (submission_date),
    KEY bidding_document_records_created_by (created_by),
    CONSTRAINT bidding_document_records_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users (user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copy manually maintained documents from the old generic table. Source rows
-- stay in place until a later, separately reviewed retirement migration.
INSERT INTO purchase_records
    (reference_no, purchase_date, items_details, amount, status, created_by, created_at)
SELECT COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id)),
       br.record_date, COALESCE(br.description, ''), br.amount,
       'Recorded', br.created_by, br.created_at
FROM business_records br
WHERE br.record_type = 'Purchase'
  AND NOT EXISTS (
      SELECT 1 FROM purchase_records pr
      WHERE pr.reference_no = COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id))
  );

INSERT INTO sales_records
    (reference_no, order_id, sales_date, amount, status, description, created_by, created_at)
SELECT COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id)),
       br.order_id, br.record_date, br.amount,
       'Recorded', br.description, br.created_by, br.created_at
FROM business_records br
WHERE br.record_type = 'Sales'
  AND NOT EXISTS (
      SELECT 1 FROM sales_records sr
      WHERE sr.reference_no = COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id))
  );

-- The current Records query also exposes completed Orders as historical Sales.
-- Keep those existing history rows with their original generated references;
-- amount remains NULL because orders has no total-sale field.
INSERT INTO sales_records
    (reference_no, order_id, sales_date, amount, status, description, created_by, created_at)
SELECT CONCAT('SALE-ORD-', o.order_id), o.order_id, o.order_date, NULL,
       o.status,
       CONCAT_WS(' | ', CONCAT('Product: ', o.product_name),
           CONCAT('Quantity: ', o.quantity), NULLIF(o.specifications, ''),
           CONCAT('Delivery: ', COALESCE(o.delivery_method, 'Not specified'))),
       NULL, o.created_at
FROM orders o
WHERE o.status = 'Completed'
  AND NOT EXISTS (
      SELECT 1 FROM sales_records sr
      WHERE sr.order_id = o.order_id
         OR sr.reference_no = CONCAT('SALE-ORD-', o.order_id)
  );

INSERT INTO receipt_records
    (reference_no, order_id, receipt_date, amount, status, description, created_by, created_at)
SELECT COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id)),
       br.order_id, br.record_date, br.amount,
       'Recorded', br.description, br.created_by, br.created_at
FROM business_records br
WHERE br.record_type = 'Receipt'
  AND NOT EXISTS (
      SELECT 1 FROM receipt_records rr
      WHERE rr.reference_no = COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id))
  );

INSERT INTO invoice_records
    (reference_no, order_id, invoice_date, amount, status, description, created_by, created_at)
SELECT COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id)),
       br.order_id, br.record_date, br.amount,
       'Recorded', br.description, br.created_by, br.created_at
FROM business_records br
WHERE br.record_type = 'Invoice'
  AND NOT EXISTS (
      SELECT 1 FROM invoice_records ir
      WHERE ir.reference_no = COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id))
  );

INSERT INTO bidding_document_records
    (reference_no, title, submission_date, status, description, created_by, created_at)
SELECT COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id)),
       LEFT(COALESCE(NULLIF(br.description, ''), br.reference_no,
           CONCAT('Legacy bidding document ', br.record_id)), 150),
       br.record_date, 'Recorded', br.description, br.created_by, br.created_at
FROM business_records br
WHERE br.record_type = 'Bidding Document'
  AND NOT EXISTS (
      SELECT 1 FROM bidding_document_records bdr
      WHERE bdr.reference_no = COALESCE(NULLIF(br.reference_no, ''), CONCAT('LEGACY-BR-', br.record_id))
  );
