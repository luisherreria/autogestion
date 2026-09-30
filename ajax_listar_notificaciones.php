<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$data = array();
try {
    $pdo = Database::getConnection();
    asegurarTablaNotificaciones($pdo);
    $filtro = filtroNotificaciones(false);
    $stmt = $pdo->prepare(
        'SELECT id_notificacion, fecha_emision, asunto_mail, tipo_notificacion, estado_lectura
         FROM notificaciones_historial
         WHERE ' . $filtro['where'] . '
         ORDER BY fecha_emision DESC, id_notificacion DESC'
    );
    $stmt->execute($filtro['parametros']);
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $marca = strtotime($fila['fecha_emision']);
        $fecha = ($marca !== false) ? date('d/m/Y H:i', $marca) : '';
        $data[] = array(
            'id' => (int) $fila['id_notificacion'],
            'fecha' => $fecha,
            'fecha_orden' => ($marca !== false) ? date('Y-m-d H:i:s', $marca) : '',
            'asunto' => trim((string) $fila['asunto_mail']),
            'tipo' => trim((string) $fila['tipo_notificacion']),
            'leida' => ((int) $fila['estado_lectura'] === 1) ? 1 : 0,
        );
    }
} catch (Exception $e) {
    $data = array();
}

echo json_encode(array('data' => $data));
exit;
