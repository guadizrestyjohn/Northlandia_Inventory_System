CREATE TABLE IF NOT EXISTS `photo_documentation_records` (
  `photo_id` int(11) NOT NULL AUTO_INCREMENT,
  `storage_name` varchar(40) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(40) NOT NULL,
  `file_extension` varchar(8) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`photo_id`),
  UNIQUE KEY `photo_documentation_storage_name` (`storage_name`),
  KEY `photo_documentation_created_at` (`created_at`),
  KEY `photo_documentation_uploaded_by` (`uploaded_by`),
  CONSTRAINT `photo_documentation_uploaded_by_fk`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
