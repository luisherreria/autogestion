<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false));
    exit;
}

$modulo = isset($_POST['modulo']) ? trim($_POST['modulo']) : '';
$detalle = isset($_POST['detalle']) ? trim($_POST['detalle']) : '';
if ($modulo === '') {
    $modulo = 'general';
}
if (strlen($modulo) > 100) {
    $modulo = substr($modulo, 0, 100);
}
if (strlen($detalle) > 255) {
    $detalle = substr($detalle, 0, 255);
}

$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
if ($codigo === '' && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $codigo = isset($_SESSION['usuario']) ? trim($_SESSION['usuario']) : '';
}

$email = '';
if (isset($_SESSION['email']) && trim($_SESSION['email']) !== '') {
    $email = trim($_SESSION['email']);
} elseif (isset($_SESSION['mail_auto']) && trim($_SESSION['mail_auto']) !== '') {
    $email = trim($_SESSION['mail_auto']);
} elseif (isset($_SESSION['usuario'])) {
    $email = trim($_SESSION['usuario']);
}
if (strlen($email) > 255) {
    $email = substr($email, 0, 255);
}
if (strlen($codigo) > 50) {
    $codigo = substr($codigo, 0, 50);
}

try {
    $pdo = Database::getConnection();
    asegurarTablaAuditoria($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO auditoria_log
            (codigo_prestador, usuario_email, fecha_hora, modulo, accion_detalle)
         VALUES
            (:codigo, :email, :fecha, :modulo, :detalle)'
    );
    $stmt->execute(array(
        ':codigo' => $codigo,
        ':email' => $email,
        ':fecha' => date('Y-m-d H:i:s'),
        ':modulo' => $modulo,
        ':detalle' => $detalle,
    ));
    echo json_encode(array('ok' => true));
} catch (Exception $e) {
    echo json_encode(array('ok' => false));
}
exit;
