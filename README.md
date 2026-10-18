# Northlandia Inventory System

Setup and configuration guide for the existing PHP MVC application.

## Requirements

- Windows with XAMPP installed.
- Apache and MySQL/MariaDB provided by XAMPP.
- PHP 8.0 or newer. The application uses PHP 8 language features such as `match` and `str_starts_with`.
- PHP extensions enabled for the Apache PHP runtime: `PDO`, `pdo_mysql`, `mbstring`, and `fileinfo`.
- MySQL or MariaDB able to import the supplied SQL dump. The dump identifies its source as MariaDB 10.4.32; a minimum compatible server version has not been independently verified.
- A web browser.

The current XAMPP Apache configuration uses `C:/xampp/htdocs` as its document root, enables `mod_rewrite`, and allows `.htaccess` rules there. If your XAMPP setup differs, verify those settings before running the application.

## Place the project in XAMPP

1. Extract or copy the project folder into:

   ```text
   C:\xampp\htdocs\Northlandia_Inventory_System
   ```

2. Confirm that the folder directly contains `app`, `public`, `northlandia_db.sql`, and this `README.md`.
3. Avoid an extra nested folder level. For example, the entry point should be at:

   ```text
   C:\xampp\htdocs\Northlandia_Inventory_System\public\index.php
   ```

## Create and import the database

The application configuration expects the database name `northlandia_db`. The SQL dump creates the tables but does not create or select the database for you.

1. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Create a database named `northlandia_db`. Use `utf8mb4` character encoding; `utf8mb4_general_ci` matches the table collation in the supplied dump.
4. Select `northlandia_db` in phpMyAdmin.
5. Open the **Import** tab, choose the project's `northlandia_db.sql`, and start the import.
6. Confirm that the import completes and the expected tables appear.

Import the dump only when setting up a database. Importing it into an existing working database may alter or duplicate data; back up existing data first and do not use a live database as a setup target.

## Configure the database connection

The connection constants are in `app/config/init.php`. Review the existing values there and set them to match your local MySQL/MariaDB installation:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'northlandia_db');
define('DB_USER', 'your-local-database-user');
define('DB_PASS', 'your-local-database-password');
define('DB_CHARSET', 'utf8mb4');
```

Replace the example username and password with your own local database credentials. Do not commit real credentials to a shared repository. The current configuration uses `localhost`, `northlandia_db`, and `utf8mb4`; the credentials must match your own XAMPP database setup.

## Open the application

With Apache running and the project in the folder above, open:

```text
http://localhost/Northlandia_Inventory_System/
```

The root `.htaccess` routes application requests through `public/index.php`. The Router then dispatches the URL to the MVC controller. Use the project root URL above; normal use does not require adding `/public` to the URL.

## Start and stop

- **Start:** Open XAMPP Control Panel, then click **Start** for Apache and MySQL. Visit the application URL above.
- **Stop:** When finished, return to XAMPP Control Panel and click **Stop** for Apache and MySQL.

## Troubleshooting

### Database connection failed

- Confirm MySQL is running in XAMPP.
- Confirm `northlandia_db` exists and the SQL import completed.
- Check `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in `app/config/init.php` against your local database configuration.
- If MySQL uses a non-default port or host, verify whether the existing connection configuration needs to be adjusted for that installation.

### Missing PHP extension or function

- Check the PHP configuration used by Apache in XAMPP's `php.ini` and enable `pdo_mysql`, `mbstring`, and `fileinfo` if they are disabled.
- Confirm PDO is enabled as well.
- Restart Apache after changing `php.ini`.
- If the error mentions `match` or `str_starts_with`, verify that Apache is using PHP 8.0 or newer.

### 404 errors, routes, or assets do not load

- Confirm the project is directly inside `C:\xampp\htdocs\Northlandia_Inventory_System` and use the URL with that exact folder name.
- Check that Apache's `mod_rewrite` module is enabled and `AllowOverride All` (or an equivalent setting permitting rewrite rules) applies to the XAMPP document root. The current project relies on its root and `public/.htaccess` rewrite rules.
- Restart Apache after changing its configuration.

### phpMyAdmin or Apache does not open

- Confirm Apache and MySQL show as running in XAMPP.
- Check for port conflicts with other local web or database services. The exact port configuration depends on the local XAMPP installation and must be verified there.

## Configuration facts and items to verify

- Verified in this project: `public/index.php` loads `app/config/init.php` and `app/autoload.php`, then dispatches through `Core\Router`.
- Verified in this project: the configured database name is `northlandia_db`; the SQL dump contains table definitions but no `CREATE DATABASE` or `USE` statement.
- Verified in the SQL dump header: it was generated with MariaDB 10.4.32 and PHP 8.2.12. These are dump-generation details, not a confirmed minimum server version.
- Verify the PHP version, required extensions, database credentials, and Apache rewrite configuration on the machine where the application will run.
