<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, password, role FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Comprobación directa de la contraseña (NO SEGURO)
        if ($user && $data['password'] === $user['password']) {
            // Éxito
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso.',
                'user' => [
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            // Fallo
            http_response_code(401); // 401 Unauthorized
            echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
    }
}
?>