<?php
require_once 'config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ]);
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}