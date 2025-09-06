<?php
// Evita que se acceda directamente a este archivo
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    http_response_code(403);
    die('Acceso prohibido.');
}

try {
    $db_path = __DIR__ . '/database.db';
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = wal;');

    // --- TABLA DE ESCANEOS ---
    // CAMBIO IMPORTANTE: La restricción UNIQUE ahora es sobre orderNumber y datetimeQR.
    // Esto previene que CUALQUIER usuario escanee un QR que ya está en el sistema.
    $pdo->exec("CREATE TABLE IF NOT EXISTS scans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        userId TEXT NOT NULL,
        orderNumber TEXT NOT NULL,
        datetimeQR TEXT NOT NULL,
        scannedAt TEXT NOT NULL,
        UNIQUE(orderNumber, datetimeQR) -- Evita duplicados a nivel GLOBAL
    )");

    // --- TABLA DE USUARIOS (con contraseña en texto plano para desarrollo) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- Insertar usuarios por defecto si la tabla está vacía ---
    $user_count = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();

    if ($user_count == 0) {
        $insert_users_sql = "INSERT INTO users (username, password, role) VALUES 
            ('admin', 'adminpassword', 'admin'),
            ('oscar', 'pass1', 'operator'),
            ('operario2', 'pass2', 'operator')";
        $pdo->exec($insert_users_sql);
    }

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]));
}
?>