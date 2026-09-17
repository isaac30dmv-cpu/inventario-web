<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$file = 'datos.json';

if (!file_exists($file)) {
    $initialData = ['prestamos' => [], 'historial' => []];
    file_put_contents($file, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$data = json_decode(file_get_contents($file), true) ?: ['prestamos' => [], 'historial' => []];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    case 'get_all':
        echo json_encode([
            'status' => 'success', 
            'prestamos' => $data['prestamos'] ?? [], 
            'historial' => $data['historial'] ?? []
        ]);
        break;

    case 'add_prestamo':
        if (!empty($input['producto'])) {
            $nuevoItem = [
                'id' => (string)(time() . rand(100, 999)),
                'producto' => trim($input['producto']),
                'codigo' => trim($input['codigo'] ?? ''),
                'categoria' => trim($input['categoria'] ?? ''),
                'estadoDisponibilidad' => trim($input['estadoDisponibilidad'] ?? 'disponible'),
                'prestatario' => trim($input['prestatario'] ?? ''),
                'estadoFisico' => trim($input['estadoFisico'] ?? 'operativo'),
                'observaciones' => trim($input['observaciones'] ?? ''),
                'fecha' => $input['fecha'] ?? date('Y-m-d')
            ];
            
            if (!isset($data['prestamos'])) $data['prestamos'] = [];
            array_unshift($data['prestamos'], $nuevoItem);
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success', 'item' => $nuevoItem]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'El nombre del producto es obligatorio.']);
        }
        break;

    case 'edit_prestamo':
        if (!empty($input['id'])) {
            $encontrado = false;
            foreach ($data['prestamos'] as &$item) {
                if ($item['id'] == $input['id']) {
                    $item['producto'] = trim($input['producto']);
                    $item['codigo'] = trim($input['codigo'] ?? '');
                    $item['categoria'] = trim($input['categoria'] ?? '');
                    $item['estadoDisponibilidad'] = trim($input['estadoDisponibilidad'] ?? 'disponible');
                    $item['prestatario'] = trim($input['prestatario'] ?? '');
                    $item['estadoFisico'] = trim($input['estadoFisico'] ?? 'operativo');
                    $item['observaciones'] = trim($input['observaciones'] ?? '');
                    $item['fecha'] = $input['fecha'] ?? date('Y-m-d');
                    $encontrado = true;
                    break;
                }
            }
            if ($encontrado) {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipo no encontrado.']);
            }
        }
        break;

    case 'devolver_prestamo':
        if (!empty($input['id'])) {
            $idDevolver = $input['id'];
            $fechaDev = $input['fechaDevolucion'] ?? date('Y-m-d');
            $nuevosPrestamos = [];

            foreach ($data['prestamos'] as $item) {
                if ($item['id'] == $idDevolver) {
                    $itemHistorial = $item;
                    $itemHistorial['fechaDevolucion'] = $fechaDev;
                    if (!isset($data['historial'])) $data['historial'] = [];
                    array_unshift($data['historial'], $itemHistorial);
                } else {
                    $nuevosPrestamos[] = $item;
                }
            }

            $data['prestamos'] = $nuevosPrestamos;
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success']);
        }
        break;

    case 'delete_prestamo':
        if (!empty($input['id'])) {
            $idDelete = $input['id'];
            $data['prestamos'] = array_values(array_filter($data['prestamos'], function($item) use ($idDelete) {
                return $item['id'] != $idDelete;
            }));
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success']);
        }
        break;

    case 'importar_backup':
        if (isset($input['prestamos']) && isset($input['historial'])) {
            $data['prestamos'] = $input['prestamos'];
            $data['historial'] = $input['historial'];
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Estructura de copia no válida.']);
        }
        break;

    case 'vaciar_historial':
        $data['historial'] = [];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}
?>