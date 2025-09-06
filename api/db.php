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
        UNIQUE(orderNumber, datetimeQR)
    )");

    // --- TABLA DE USUARIOS (con contraseña en texto plano para desarrollo) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // === Pedidos / Albaranes (mínimo viable) ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        orderNumber TEXT NOT NULL UNIQUE,
        customerName TEXT,
        status TEXT DEFAULT 'PENDIENTE',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        dnNumber TEXT UNIQUE,
        status TEXT DEFAULT 'EN_PACKING',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(order_id) REFERENCES orders(id)
    )");

    // === Tokens QR firmados (pedido/albarán) ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE,
        issued_at TEXT DEFAULT CURRENT_TIMESTAMP,
        revoked INTEGER DEFAULT 0
    )");

    // === Eventos de escaneo/acción por rol ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS scan_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        userId TEXT NOT NULL,
        role TEXT NOT NULL,
        kind TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        payload_json TEXT,
        scannedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(role, kind, entity_id, action)
    )");

    // --- Insertar usuarios por defecto si la tabla está vacía ---
    $user_count = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();

    if ($user_count == 0) {
        $insert_users_sql = "INSERT INTO users (username, password, role) VALUES
            ('admin', 'adminpassword', 'admin'),
            ('atencion', 'pass', 'atencion'),
            ('caja', 'pass', 'caja'),
            ('picker1', 'pass', 'picking'),
            ('packer1', 'pass', 'packing'),
            ('log1', 'pass', 'logistica'),
            ('almacen1', 'pass', 'almacen')";
        $pdo->exec($insert_users_sql);
    }

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]));
}
?>
