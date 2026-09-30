<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'mensaje' => 'Solicitud inválida.'));
    exit;
}

$id = isset($_POST['id_notificacion']) ? (int) $_POST['id_notificacion'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'mensaje' => 'Notificación inválida.'));
    exit;
}

$email = '';
if (isset($_SESSION['email']) && trim($_SESSION['email']) !== '') {
    $email = trim($_SESSION['email']);
} elseif (isset($_SESSION['usuario'])) {
    $email = trim($_SESSION['usuario']);
}
if (strlen($email) > 100) {
    $email = substr($email, 0, 100);
}

$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
if ($codigo === '' && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $codigo = isset($_SESSION['usuario']) ? trim($_SESSION['usuario']) : '';
}
if (strlen($codigo) > 50) {
    $codigo = substr($codigo, 0, 50);
}

try {
    $pdo = Database::getConnection();
    asegurarTablaNotificaciones($pdo);
    asegurarTablaAuditoria($pdo);

    $stmt = $pdo->prepare(
        'SELECT id_notificacion, cod_prestador, asunto_mail, tipo_notificacion, cuerpo_html, archivo_adjunto,
                remitente_mail, destinatarios_mail, fecha_emision
         FROM notificaciones_historial
         WHERE id_notificacion = :id
         LIMIT 1'
    );
    $stmt->execute(array(':id' => $id));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila || !notificacionVisible($fila)) {
        echo json_encode(array('ok' => false, 'mensaje' => 'No se encontró la notificación.'));
        exit;
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE notificaciones_historial
         SET estado_lectura = 1, fecha_lectura = NOW(), usuario_lector = :lector
         WHERE id_notificacion = :id'
    );
    $stmtUpdate->execute(array(
        ':lector' => $email,
        ':id' => $id,
    ));

    $detalle = 'Leyó la notificación ' . $id;
    if (strlen($detalle) > 255) {
        $detalle = substr($detalle, 0, 255);
    }
    $stmtAudit = $pdo->prepare(
        'INSERT INTO auditoria_log
            (codigo_prestador, usuario_email, fecha_hora, modulo, accion_detalle)
         VALUES
            (:codigo, :email, :fecha, :modulo, :detalle)'
    );
    $stmtAudit->execute(array(
        ':codigo' => $codigo,
        ':email' => $email,
        ':fecha' => date('Y-m-d H:i:s'),
        ':modulo' => 'notificaciones',
        ':detalle' => $detalle,
    ));

    $adjunto = basename(str_replace('\\', '/', trim((string) $fila['archivo_adjunto'])));
    $remitente = preg_replace('/\s+/', ' ', str_replace(array('<', '>'), '', trim((string) $fila['remitente_mail'])));
    $destinatarios = preg_replace('/\s+/', ' ', str_replace(array('<', '>'), '', trim((string) $fila['destinatarios_mail'])));
    $remitente = trim($remitente);
    $destinatarios = trim($destinatarios, " \t,");
    $dias = array('domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado');
    $marca = strtotime($fila['fecha_emision']);
    $fechaFormateada = '';
    if ($marca !== false && $marca > 0) {
        $fechaFormateada = $dias[(int) date('w', $marca)] . ' ' . date('j/n/Y H:i', $marca);
    }
    $inicial = strtoupper(substr($remitente, 0, 1));
    if ($inicial === '') {
        $inicial = 'M';
    }

    $cabeceraCorreo = '
    <div class="flex items-start justify-between border-b border-slate-200 pb-4 mb-4">
        <div class="flex items-center min-w-0">
            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold text-lg mr-3">
                ' . h($inicial) . '
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 break-all">' . h($remitente) . '</p>
                <p class="text-xs text-gray-500 break-all">Para: ' . h($destinatarios) . '</p>
            </div>
        </div>
        <div class="text-right flex-shrink-0 pl-4">
            <p class="text-xs text-gray-500 whitespace-nowrap">' . h($fechaFormateada) . '</p>
        </div>
    </div>';

    echo json_encode(array(
        'ok' => true,
        'asunto' => trim((string) $fila['asunto_mail']),
        'cuerpo_html' => $cabeceraCorreo . (string) $fila['cuerpo_html'],
        'archivo_adjunto' => $adjunto,
    ));
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'mensaje' => 'No se pudo abrir la notificación.'));
}
exit;
