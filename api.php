<?php
header('Content-Type: application/json; charset=utf-8');

$file = 'datos.json';

// Si no existe el archivo JSON, lo creamos con estructura vacía
if (!file_exists($file)) {
    $initialData = ['prestamos' => [], 'historial' => []];
    file_put_contents($file, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$data = json_decode(file_get_contents($file), true) ?: ['prestamos' => [], 'historial' => []];
$action = $_GET['action'] ?? '';

// Leer cuerpo de la petición en JSON
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {

    case 'get_all':
        echo json_encode(['status' => 'success', 'prestamos' => $data['prestamos'], 'historial' => $data['historial']]);
        break;

    case 'add_prestamo':
        if (!empty($input['producto'])) {
            $nuevoItem = [
                'id' => time() . rand(100, 999),
                'producto' => trim($input['producto']),
                'codigo' => trim($input['codigo'] ?? ''),
                'categoria' => trim($input['categoria'] ?? ''),
                'estadoDisponibilidad' => trim($input['estadoDisponibilidad'] ?? 'disponible'),
                'prestatario' => trim($input['prestatario'] ?? ''),
                'estadoFisico' => trim($input['estadoFisico'] ?? 'operativo'),
                'observaciones' => trim($input['observaciones'] ?? ''),
                'fecha' => $input['fecha'] ?? date('Y-m-d')
            ];
            
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
                    $item['fecha'] = $input['fecha'];
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

            foreach ($data['prestamos'] as $item) {
                if ($item['id'] == $idDevolver) {
                    $itemHistorial = $item;
                    $itemHistorial['fechaDevolucion'] = $fechaDev;
                    array_unshift($data['historial'], $itemHistorial);
                    break;
                }
            }

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