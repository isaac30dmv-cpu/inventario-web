<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$archivoDatos = 'datos.json';

// Inicializar archivo de datos si no existe
if (!file_exists($archivoDatos)) {
    $dataInicial = ['prestamos' => [], 'historial' => []];
    file_put_contents($archivoDatos, json_encode($dataInicial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$contenido = file_get_contents($archivoDatos);
$data = json_decode($contenido, true) ?: ['prestamos' => [], 'historial' => []];

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

function guardarDatos($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

switch ($action) {
    case 'get_all':
        echo json_encode([
            'status' => 'success', 
            'prestamos' => $data['prestamos'] ?? [], 
            'historial' => $data['historial'] ?? []
        ]);
        break;

    case 'add_prestamo':
        $nuevo = [
            'id' => uniqid(),
            'producto' => $input['producto'] ?? '',
            'codigo' => $input['codigo'] ?? '',
            'estadoDisponibilidad' => $input['estadoDisponibilidad'] ?? 'disponible',
            'prestatario' => $input['prestatario'] ?? '',
            'estadoFisico' => $input['estadoFisico'] ?? 'operativo',
            'observaciones' => $input['observaciones'] ?? '',
            'fecha' => $input['fecha'] ?? date('Y-m-d')
        ];
        $data['prestamos'][] = $nuevo;
        guardarDatos($archivoDatos, $data);
        echo json_encode(['status' => 'success', 'item' => $nuevo]);
        break;

    case 'edit_prestamo':
        $id = $input['id'] ?? null;
        $encontrado = false;
        foreach ($data['prestamos'] as &$p) {
            if ((string)$p['id'] === (string)$id) {
                $p['producto'] = $input['producto'] ?? $p['producto'];
                $p['codigo'] = $input['codigo'] ?? $p['codigo'];
                $p['estadoDisponibilidad'] = $input['estadoDisponibilidad'] ?? $p['estadoDisponibilidad'];
                $p['prestatario'] = $input['prestatario'] ?? $p['prestatario'];
                $p['estadoFisico'] = $input['estadoFisico'] ?? $p['estadoFisico'];
                $p['observaciones'] = $input['observaciones'] ?? $p['observaciones'];
                $p['fecha'] = $input['fecha'] ?? $p['fecha'];
                $encontrado = true;
                break;
            }
        }
        if ($encontrado) {
            guardarDatos($archivoDatos, $data);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Equipo no encontrado']);
        }
        break;

    case 'delete_prestamo':
        $id = $input['id'] ?? null;
        $conteoInicial = count($data['prestamos']);
        
        $data['prestamos'] = array_values(array_filter($data['prestamos'], function($item) use ($id) {
            return (string)$item['id'] !== (string)$id;
        }));

        if (count($data['prestamos']) < $conteoInicial) {
            guardarDatos($archivoDatos, $data);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se encontró el equipo para eliminar.']);
        }
        break;

    case 'devolver_prestamo':
        $id = $input['id'] ?? null;
        $fechaDev = $input['fechaDevolucion'] ?? date('Y-m-d');
        
        foreach ($data['prestamos'] as $p) {
            if ((string)$p['id'] === (string)$id) {
                $registroHistorial = [
                    'producto' => $p['producto'],
                    'codigo' => $p['codigo'],
                    'prestatario' => $p['prestatario'],
                    'observaciones' => $p['observaciones'],
                    'fecha' => $p['fecha'],
                    'fechaDevolucion' => $fechaDev
                ];
                $data['historial'][] = $registroHistorial;
                break;
            }
        }
        guardarDatos($archivoDatos, $data);
        echo json_encode(['status' => 'success']);
        break;

    case 'vaciar_historial':
        $data['historial'] = [];
        guardarDatos($archivoDatos, $data);
        echo json_encode(['status' => 'success']);
        break;

    case 'importar_backup':
        if (isset($input['prestamos']) && isset($input['historial'])) {
            $data['prestamos'] = $input['prestamos'];
            $data['historial'] = $input['historial'];
            guardarDatos($archivoDatos, $data);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Estructura de copia de seguridad no válida']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}