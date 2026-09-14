<?php
header('Content-Type: application/json; charset=utf-8');

// Ruta del archivo de la Base de Datos SQLite
$dbFile = __DIR__ . '/inventario.db';

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Crear tablas si no existen
    $pdo->exec("CREATE TABLE IF NOT EXISTS prestamos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto TEXT NOT NULL,
        codigo TEXT,
        prestatario TEXT NOT NULL,
        fecha TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS historial (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto TEXT NOT NULL,
        codigo TEXT,
        prestatario TEXT NOT NULL,
        fecha TEXT NOT NULL,
        fechaDevolucion TEXT NOT NULL
    )");

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la BD: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Obtener todos los registros
if ($action === 'get_all') {
    $stmtP = $pdo->query("SELECT * FROM prestamos ORDER BY id DESC");
    $prestamos = $stmtP->fetchAll();

    $stmtH = $pdo->query("SELECT * FROM historial ORDER BY id DESC");
    $historial = $stmtH->fetchAll();

    echo json_encode(['status' => 'success', 'prestamos' => $prestamos, 'historial' => $historial]);
    exit;
}

// Añadir un nuevo préstamo
if ($action === 'add_prestamo') {
    $stmt = $pdo->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha) VALUES (:producto, :codigo, :prestatario, :fecha)");
    $stmt->execute([
        ':producto' => $input['producto'] ?? '',
        ':codigo' => $input['codigo'] ?? '',
        ':prestatario' => $input['prestatario'] ?? '',
        ':fecha' => $input['fecha'] ?? ''
    ]);
    echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);
    exit;
}

// Editar un préstamo existente
if ($action === 'edit_prestamo') {
    $stmt = $pdo->prepare("UPDATE prestamos SET producto = :producto, codigo = :codigo, prestatario = :prestatario, fecha = :fecha WHERE id = :id");
    $stmt->execute([
        ':id' => $input['id'],
        ':producto' => $input['producto'] ?? '',
        ':codigo' => $input['codigo'] ?? '',
        ':prestatario' => $input['prestatario'] ?? '',
        ':fecha' => $input['fecha'] ?? ''
    ]);
    echo json_encode(['status' => 'success']);
    exit;
}

// Devolver un préstamo (Mover a Historial)
if ($action === 'devolver_prestamo') {
    $id = $input['id'] ?? null;
    $fechaDevolucion = $input['fechaDevolucion'] ?? date('d/m/Y');

    if ($id) {
        $stmtSelect = $pdo->prepare("SELECT * FROM prestamos WHERE id = :id");
        $stmtSelect->execute([':id' => $id]);
        $item = $stmtSelect->fetch();

        if ($item) {
            $pdo->beginTransaction();
            $stmtInsert = $pdo->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion)");
            $stmtInsert->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'],
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':fechaDevolucion' => $fechaDevolucion
            ]);

            $stmtDelete = $pdo->prepare("DELETE FROM prestamos WHERE id = :id");
            $stmtDelete->execute([':id' => $id]);
            $pdo->commit();

            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Préstamo no encontrado']);
    exit;
}

// Vaciar historial
if ($action === 'vaciar_historial') {
    $pdo->exec("DELETE FROM historial");
    echo json_encode(['status' => 'success']);
    exit;
}

// Importar copia de seguridad (Remplazar BD completa)
if ($action === 'importar_backup') {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM prestamos");
    $pdo->exec("DELETE FROM historial");

    if (isset($input['prestamos']) && is_array($input['prestamos'])) {
        $stmt = $pdo->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha) VALUES (:producto, :codigo, :prestatario, :fecha)");
        foreach ($input['prestamos'] as $item) {
            $stmt->execute([
                ':producto' => $item['producto'] ?? '',
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'] ?? '',
                ':fecha' => $item['fecha'] ?? ''
            ]);
        }
    }

    if (isset($input['historial']) && is_array($input['historial'])) {
        $stmt = $pdo->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion)");
        foreach ($input['historial'] as $item) {
            $stmt->execute([
                ':producto' => $item['producto'] ?? '',
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'] ?? '',
                ':fecha' => $item['fecha'] ?? '',
                ':fechaDevolucion' => $item['fechaDevolucion'] ?? ''
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);