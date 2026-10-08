<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    public static function connect()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHARSET;

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            ]);

            return $pdo;

        } catch (PDOException $e) {
            throw new \Exception('Database connection failed.');
        }
    }
}