<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('ok' => false, 'mensaje' => 'Acceso denegado.', 'items' => array()));
    exit;
}

$texto = '';
if (isset($_POST['q'])) {
    $texto = trim($_POST['q']);
} elseif (isset($_GET['q'])) {
    $texto = trim($_GET['q']);
}

if (strlen($texto) < 2) {
    echo json_encode(array('ok' => true, 'items' => array(), 'mas' => false));
    exit;
}

try {
    $pdo = Database::getConnection();
    $resultado = buscarPrestadores($pdo, $texto, 15);
    echo json_encode(array(
        'ok' => true,
        'items' => $resultado['items'],
        'mas' => $resultado['mas'],
    ));
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'mensaje' => 'No se pudo buscar el prestador.', 'items' => array()));
}
exit;
