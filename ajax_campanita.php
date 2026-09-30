<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

$cantidad = 0;
try {
    $pdo = Database::getConnection();
    asegurarTablaNotificaciones($pdo);
    $filtro = filtroNotificaciones(true);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cantidad
         FROM notificaciones_historial
         WHERE ' . $filtro['where']
    );
    $stmt->execute($filtro['parametros']);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fila) {
        $cantidad = (int) $fila['cantidad'];
    }
} catch (Exception $e) {
    $cantidad = 0;
}

echo json_encode(array('cantidad' => $cantidad));
exit;
