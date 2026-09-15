<?php
header('Content-Type: application/json; charset=utf-8');

$dbFile = __DIR__ . '/inventario.db';

try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tablas si no existen
    $db->exec("CREATE TABLE IF NOT EXISTS prestamos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto TEXT NOT NULL,
        codigo TEXT,
        prestatario TEXT NOT NULL,
        fecha TEXT NOT NULL,
        categoria TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS historial (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto TEXT NOT NULL,
        codigo TEXT,
        prestatario TEXT NOT NULL,
        fecha TEXT NOT NULL,
        fechaDevolucion TEXT NOT NULL,
        categoria TEXT
    )");

    // Asegurar que la columna 'categoria' existe en bases de datos creadas anteriormente
    try {
        $db->exec("ALTER TABLE prestamos ADD COLUMN categoria TEXT");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE historial ADD COLUMN categoria TEXT");
    } catch (Exception $e) {}

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'get_all':
        $stmtP = $db->query("SELECT * FROM prestamos ORDER BY id DESC");
        $prestamos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        $stmtH = $db->query("SELECT * FROM historial ORDER BY id DESC");
        $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'prestamos' => $prestamos,
            'historial' => $historial
        ]);
        break;

    case 'add_prestamo':
        $stmt = $db->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha, categoria) VALUES (:producto, :codigo, :prestatario, :fecha, :categoria)");
        $stmt->execute([
            ':producto' => $input['producto'] ?? '',
            ':codigo' => $input['codigo'] ?? '',
            ':prestatario' => $input['prestatario'] ?? '',
            ':fecha' => $input['fecha'] ?? '',
            ':categoria' => $input['categoria'] ?? ''
        ]);
        echo json_encode(['status' => 'success', 'id' => $db->lastInsertId()]);
        break;

    case 'edit_prestamo':
        $stmt = $db->prepare("UPDATE prestamos SET producto = :producto, codigo = :codigo, prestatario = :prestatario, fecha = :fecha, categoria = :categoria WHERE id = :id");
        $stmt->execute([
            ':id' => $input['id'],
            ':producto' => $input['producto'] ?? '',
            ':codigo' => $input['codigo'] ?? '',
            ':prestatario' => $input['prestatario'] ?? '',
            ':fecha' => $input['fecha'] ?? '',
            ':categoria' => $input['categoria'] ?? ''
        ]);
        echo json_encode(['status' => 'success']);
        break;

    case 'devolver_prestamo':
        $id = $input['id'] ?? 0;
        $fechaDevolucion = $input['fechaDevolucion'] ?? date('d/m/Y');

        $stmtSelect = $db->prepare("SELECT * FROM prestamos WHERE id = :id");
        $stmtSelect->execute([':id' => $id]);
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $stmtInsert = $db->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion, categoria) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion, :categoria)");
            $stmtInsert->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'],
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':fechaDevolucion' => $fechaDevolucion,
                ':categoria' => $item['categoria'] ?? ''
            ]);

            $stmtDelete = $db->prepare("DELETE FROM prestamos WHERE id = :id");
            $stmtDelete->execute([':id' => $id]);

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado.']);
        }
        break;

    case 'vaciar_historial':
        $db->exec("DELETE FROM historial");
        echo json_encode(['status' => 'success']);
        break;

    case 'importar_backup':
        $db->exec("DELETE FROM prestamos");
        $db->exec("DELETE FROM historial");

        $stmtP = $db->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha, categoria) VALUES (:producto, :codigo, :prestatario, :fecha, :categoria)");
        foreach ($input['prestamos'] as $item) {
            $stmtP->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':categoria' => $item['categoria'] ?? ''
            ]);
        }

        $stmtH = $db->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion, categoria) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion, :categoria)");
        foreach ($input['historial'] as $item) {
            $stmtH->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':fechaDevolucion' => $item['fechaDevolucion'] ?? '',
                ':categoria' => $item['categoria'] ?? ''
            ]);
        }

        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}