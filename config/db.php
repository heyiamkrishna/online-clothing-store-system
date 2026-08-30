<?php

// 1. Fetch environment variables with local fallback defaults
$host     = getenv('DB_HOST')     ?: '127.0.0.1';
$port     = getenv('DB_PORT')     ?: 3306;
$dbname   = getenv('DB_NAME')     ?: 'your_local_db_name';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

// 2. PDO options for performance, error handling, and cloud SSL
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// If using cloud MySQL that requires SSL (e.g., TiDB Cloud / Aiven)
if (getenv('DB_SSL') === 'true' || getenv('DB_HOST')) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = true;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false; // Adjust based on provider requirements
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log the detailed error to Render server logs
    error_log("Database Connection Error: " . $e->getMessage());

    // Display a generic message to users (avoids leaking credentials)
    if (getenv('APP_ENV') === 'production' || getenv('RENDER')) {
        die("Service unavailable. Database connection failed.");
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}