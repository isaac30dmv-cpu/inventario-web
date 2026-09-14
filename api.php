<?php
session_start();
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
        fecha TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS historial (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto TEXT NOT NULL,
        codigo TEXT,
        prestatario TEXT NOT NULL,
        fecha TEXT NOT NULL,
        fechaDevolucion TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL
    )");

    // Crear usuario admin por defecto si la tabla de usuarios está vacía
    $stmtUserCount = $db->query("SELECT COUNT(*) FROM usuarios");
    if ($stmtUserCount->fetchColumn() == 0) {
        $defaultUser = 'admin';
        $defaultPass = password_hash('admin', PASSWORD_DEFAULT);
        $stmtInitUser = $db->prepare("INSERT INTO usuarios (username, password, role) VALUES (:user, :pass, 'admin')");
        $stmtInitUser->execute([':user' => $defaultUser, ':pass' => $defaultPass]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Función para verificar si el usuario es administrador
function verificarAdmin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Se requieren permisos de Administrador.']);
        exit;
    }
}

switch ($action) {
    case 'login':
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Por favor, introduce usuario y contraseña.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'username' => $user['username'],
                'role' => $user['role']
            ];
            echo json_encode(['status' => 'success', 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña incorrectos.']);
        }
        break;

    case 'logout':
        unset($_SESSION['user']);
        session_destroy();
        echo json_encode(['status' => 'success']);
        break;

    case 'check_auth':
        echo json_encode([
            'status' => 'success',
            'user' => $_SESSION['user'] ?? null
        ]);
        break;

    case 'get_all':
        $stmtP = $db->query("SELECT * FROM prestamos ORDER BY id DESC");
        $prestamos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        $stmtH = $db->query("SELECT * FROM historial ORDER BY id DESC");
        $historial = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'prestamos' => $prestamos,
            'historial' => $historial,
            'user' => $_SESSION['user'] ?? null
        ]);
        break;

    case 'add_prestamo':
        verificarAdmin();
        $stmt = $db->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha) VALUES (:producto, :codigo, :prestatario, :fecha)");
        $stmt->execute([
            ':producto' => $input['producto'] ?? '',
            ':codigo' => $input['codigo'] ?? '',
            ':prestatario' => $input['prestatario'] ?? '',
            ':fecha' => $input['fecha'] ?? ''
        ]);
        echo json_encode(['status' => 'success', 'id' => $db->lastInsertId()]);
        break;

    case 'edit_prestamo':
        verificarAdmin();
        $stmt = $db->prepare("UPDATE prestamos SET producto = :producto, codigo = :codigo, prestatario = :prestatario, fecha = :fecha WHERE id = :id");
        $stmt->execute([
            ':id' => $input['id'],
            ':producto' => $input['producto'] ?? '',
            ':codigo' => $input['codigo'] ?? '',
            ':prestatario' => $input['prestatario'] ?? '',
            ':fecha' => $input['fecha'] ?? ''
        ]);
        echo json_encode(['status' => 'success']);
        break;

    case 'devolver_prestamo':
        verificarAdmin();
        $id = $input['id'] ?? 0;
        $fechaDevolucion = $input['fechaDevolucion'] ?? date('d/m/Y');

        $stmtSelect = $db->prepare("SELECT * FROM prestamos WHERE id = :id");
        $stmtSelect->execute([':id' => $id]);
        $item = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $stmtInsert = $db->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion)");
            $stmtInsert->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'],
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':fechaDevolucion' => $fechaDevolucion
            ]);

            $stmtDelete = $db->prepare("DELETE FROM prestamos WHERE id = :id");
            $stmtDelete->execute([':id' => $id]);

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado.']);
        }
        break;

    case 'vaciar_historial':
        verificarAdmin();
        $db->exec("DELETE FROM historial");
        echo json_encode(['status' => 'success']);
        break;

    case 'importar_backup':
        verificarAdmin();
        $db->exec("DELETE FROM prestamos");
        $db->exec("DELETE FROM historial");

        $stmtP = $db->prepare("INSERT INTO prestamos (producto, codigo, prestatario, fecha) VALUES (:producto, :codigo, :prestatario, :fecha)");
        foreach ($input['prestamos'] as $item) {
            $stmtP->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha']
            ]);
        }

        $stmtH = $db->prepare("INSERT INTO historial (producto, codigo, prestatario, fecha, fechaDevolucion) VALUES (:producto, :codigo, :prestatario, :fecha, :fechaDevolucion)");
        foreach ($input['historial'] as $item) {
            $stmtH->execute([
                ':producto' => $item['producto'],
                ':codigo' => $item['codigo'] ?? '',
                ':prestatario' => $item['prestatario'],
                ':fecha' => $item['fecha'],
                ':fechaDevolucion' => $item['fechaDevolucion'] ?? ''
            ]);
        }

        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}