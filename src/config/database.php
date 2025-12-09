<?php
function db_connect()
{
    static $conn;

    if (!isset($conn)) {
        $config = [
            'host' => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'cafe1',
            'port' => 3306
        ];

        $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);

        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }

        $conn->set_charset("utf8mb4");
    }

    return $conn;
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

