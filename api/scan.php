<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- GUARDAR UN NUEVO ESCANEO (MÉTODO POST) ---
if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['userId']) || !isset($data['order']) || !isset($data['datetime'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO scans (userId, orderNumber, datetimeQR, scannedAt) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['userId'], $data['order'], $data['datetime'], date('c')]); // Usar fecha del servidor
        
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Escaneo guardado.']);

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            // Si el QR ya existe, buscamos quién lo escaneó para dar más detalles.
            $stmt = $pdo->prepare("SELECT userId, scannedAt FROM scans WHERE orderNumber = ? AND datetimeQR = ?");
            $stmt->execute([$data['order'], $data['datetime']]);
            $existingScan = $stmt->fetch(PDO::FETCH_ASSOC);

            $message = 'Este QR ya fue registrado.';
            if ($existingScan) {
                $scanDate = date_format(date_create($existingScan['scannedAt']), 'd/m/Y H:i');
                $message = "QR ya escaneado por {$existingScan['userId']} el {$scanDate}.";
            }
            
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => $message]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
        }
    }
}

// --- OBTENER ESCANEOS (MÉTODO GET) ---
if ($method == 'GET') {
    try {
        // Si se pide un userId, filtramos por ese usuario. Si no, devolvemos todo.
        if (isset($_GET['userId'])) {
            $stmt = $pdo->prepare("SELECT * FROM scans WHERE userId = ? ORDER BY scannedAt DESC");
            $stmt->execute([$_GET['userId']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM scans ORDER BY scannedAt DESC");
            $stmt->execute();
        }
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($scans);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener los datos: ' . $e->getMessage()]);
    }
}

// --- BORRAR UN ESCANEO (NUEVO MÉTODO DELETE) ---
if ($method == 'DELETE') {
    // El ID del escaneo a borrar viene por la URL (ej: ?id=123)
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta el ID del escaneo a eliminar.']);
        exit;
    }

    // El ID del usuario que solicita el borrado viene en el cuerpo
    $requestBody = json_decode(file_get_contents('php://input'), true);
    if (!isset($requestBody['userId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta la identificación del usuario.']);
        exit;
    }

    $scanIdToDelete = $_GET['id'];
    $requestingUserId = $requestBody['userId'];

    try {
        // Primero, verificamos que el escaneo exista y pertenezca al usuario que lo solicita
        $stmt = $pdo->prepare("SELECT userId FROM scans WHERE id = ?");
        $stmt->execute([$scanIdToDelete]);
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scan) {
            http_response_code(404); // Not Found
            echo json_encode(['success' => false, 'message' => 'El escaneo no existe.']);
            exit;
        }

        if ($scan['userId'] !== $requestingUserId) {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para borrar este escaneo.']);
            exit;
        }

        // Si todo es correcto, procedemos a borrar
        $deleteStmt = $pdo->prepare("DELETE FROM scans WHERE id = ?");
        $deleteStmt->execute([$scanIdToDelete]);

        echo json_encode(['success' => true, 'message' => 'Escaneo eliminado correctamente.']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos al eliminar.']);
    }
}
?>