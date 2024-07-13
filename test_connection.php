<?php
// test_connection.php

try {
    $pdo = new \PDO('mysql:host=127.0.0.1;dbname=user_data', 'root', 'Welcome12345@');
    echo "Connection successful!";
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
