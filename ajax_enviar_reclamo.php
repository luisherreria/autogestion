<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No autorizado.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Solicitud no válida.'));
    exit;
}

$destinatario = isset($_POST['destinatario']) ? trim($_POST['destinatario']) : '';
$asunto = isset($_POST['asunto']) ? trim($_POST['asunto']) : '';
$mensaje = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';
$adjuntosPost = (isset($_POST['adjuntos']) && is_array($_POST['adjuntos'])) ? $_POST['adjuntos'] : array();

if ($destinatario === '') {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Ingrese un destinatario.'));
    exit;
}
if ($asunto === '') {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Ingrese un asunto.'));
    exit;
}
if (strlen($asunto) > 255) {
    $asunto = substr($asunto, 0, 255);
}
if (strlen($mensaje) > 20000) {
    $mensaje = substr($mensaje, 0, 20000);
}

$correos = correosReclamo($destinatario);
if (count($correos) === 0) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'El destinatario no es un correo válido.'));
    exit;
}
$destinatariosLog = implode(', ', $correos);

try {
    $pdo = Database::getConnection();
    $stmtSmtp = $pdo->prepare('SELECT * FROM t_config_smtp WHERE metodo_envio = :metodo LIMIT 1');
    $stmtSmtp->execute(array(':metodo' => 'SMTPC'));
    $smtp = $stmtSmtp->fetch(PDO::FETCH_ASSOC);
    if (!$smtp || trim((string) $smtp['host']) === '') {
        registrarLogReclamo($pdo, $destinatariosLog, $asunto, 'ERROR', 'No hay configuración SMTP para el método SMTPC.');
        registrarAuditoriaReclamo($pdo, 'Error al enviar reclamo de saldos: sin SMTP');
        echo json_encode(array('status' => 'error', 'mensaje' => 'No hay un servidor de correo configurado.'));
        exit;
    }

    if (!cargarPhpMailerReclamo()) {
        registrarLogReclamo($pdo, $destinatariosLog, $asunto, 'ERROR', 'PHPMailer no está disponible.');
        registrarAuditoriaReclamo($pdo, 'Error al enviar reclamo de saldos: PHPMailer no disponible');
        echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo preparar el envío de correo.'));
        exit;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = trim((string) $smtp['host']);
    $mail->Port = (int) $smtp['puerto'];
    $mail->SMTPAuth = trim((string) $smtp['usuario']) !== '';
    $mail->Username = trim((string) $smtp['usuario']);
    $mail->Password = (string) $smtp['password'];
    $mail->SMTPSecure = ((int) $smtp['puerto'] === 465) ? 'ssl' : 'tls';
    $mail->Timeout = 20;

    $desde = trim((string) $smtp['remitente_email']);
    if ($desde === '') {
        $desde = trim((string) $smtp['usuario']);
    }
    $mail->setFrom($desde, trim((string) $smtp['remitente_nombre']));
    foreach ($correos as $correo) {
        $mail->addAddress($correo);
    }

    $mail->isHTML(true);
    $mail->Subject = $asunto;
    $mail->Body = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
    $mail->AltBody = $mensaje;

    $adjuntos = rutasAdjuntosReclamo($adjuntosPost);
    foreach ($adjuntos as $ruta) {
        if (file_exists($ruta)) {
            $mail->addAttachment($ruta);
        }
    }

    try {
        $mail->send();
        $estado = 'ENVIADO';
        $mensajeServidor = 'ÉXITO: Correo despachado';
    } catch (Exception $e) {
        $estado = 'ERROR';
        $mensajeServidor = trim((string) $mail->ErrorInfo);
        if ($mensajeServidor === '') {
            $mensajeServidor = $e->getMessage();
        }
    }

    registrarLogReclamo($pdo, $destinatariosLog, $asunto, $estado, $mensajeServidor);
    if ($estado === 'ENVIADO') {
        registrarAuditoriaReclamo($pdo, 'Envío de reclamo de saldos a ' . $destinatariosLog);
        echo json_encode(array('status' => 'ok', 'mensaje' => 'Enviado'));
    } else {
        registrarAuditoriaReclamo($pdo, 'Error al enviar reclamo de saldos');
        echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo enviar el correo.'));
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo enviar el correo.'));
}

function correosReclamo($lista)
{
    $partes = preg_split('/[;,\/\s]+/', (string) $lista);
    if (!is_array($partes)) {
        return array();
    }
    $salida = array();
    foreach ($partes as $correo) {
        $correo = trim($correo);
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $salida[$correo] = $correo;
        }
    }
    return array_values($salida);
}

function rutasAdjuntosReclamo($lista)
{
    $permitidas = basesAdjuntosReclamo();
    $salida = array();
    $cantidad = 0;
    foreach ($lista as $ruta) {
        if ($cantidad >= 40) {
            break;
        }
        $real = rutaAdjuntoReclamoValida($ruta, $permitidas);
        if ($real === '' || isset($salida[$real])) {
            continue;
        }
        $salida[$real] = $real;
        $cantidad++;
    }
    return array_values($salida);
}

function basesAdjuntosReclamo()
{
    $bases = array();
    $tango = realpath(dirname(__FILE__) . '/../uploads/pdftango');
    if ($tango !== false) {
        $bases[] = $tango;
    }
    $config = appConfig();
    $comprobantes = isset($config['dirs']['comprobantes']) ? $config['dirs']['comprobantes'] : dirname(__FILE__) . '/archivos/comprobantes';
    $baseComp = realpath($comprobantes);
    if ($baseComp !== false) {
        $bases[] = $baseComp;
    }
    return $bases;
}

function rutaAdjuntoReclamoValida($ruta, $bases)
{
    $ruta = trim(str_replace('\\', '/', (string) $ruta));
    if ($ruta === '' || strpos($ruta, '..') !== false || strpos($ruta, "\0") !== false) {
        return '';
    }
    if (strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== 'pdf') {
        return '';
    }
    if (!file_exists($ruta)) {
        return '';
    }
    $real = realpath($ruta);
    if ($real === false || !is_file($real)) {
        return '';
    }
    $realNorm = strtolower(str_replace('\\', '/', $real));
    foreach ($bases as $base) {
        $baseNorm = strtolower(rtrim(str_replace('\\', '/', $base), '/'));
        if ($realNorm === $baseNorm || strpos($realNorm, $baseNorm . '/') === 0) {
            return $real;
        }
    }
    return '';
}

function cargarPhpMailerReclamo()
{
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }
    $src = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src';
    if (!is_file($src . DIRECTORY_SEPARATOR . 'PHPMailer.php')) {
        return false;
    }
    require_once $src . DIRECTORY_SEPARATOR . 'Exception.php';
    require_once $src . DIRECTORY_SEPARATOR . 'PHPMailer.php';
    require_once $src . DIRECTORY_SEPARATOR . 'SMTP.php';
    return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

function registrarLogReclamo($pdo, $destinatarios, $asunto, $estado, $mensajeServidor)
{
    $stmt = $pdo->prepare(
        'INSERT INTO t_log_emails
            (fecha, modulo_origen, codigo_plantilla, destinatarios, asunto, estado, mensaje_servidor)
         VALUES
            (:fecha, :modulo_origen, :codigo_plantilla, :destinatarios, :asunto, :estado, :mensaje_servidor)'
    );
    $stmt->execute(array(
        ':fecha' => date('Y-m-d H:i:s'),
        ':modulo_origen' => 'Conciliador de Saldos',
        ':codigo_plantilla' => 'RECLAMO_SALDOS',
        ':destinatarios' => $destinatarios,
        ':asunto' => substr($asunto, 0, 255),
        ':estado' => ($estado === 'ENVIADO') ? 'ENVIADO' : 'ERROR',
        ':mensaje_servidor' => $mensajeServidor,
    ));
}

function registrarAuditoriaReclamo($pdo, $detalle)
{
    asegurarTablaAuditoria($pdo);
    $codigo = isset($_SESSION['codigo']) ? trim((string) $_SESSION['codigo']) : '';
    if ($codigo === '' && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $codigo = isset($_SESSION['usuario']) ? trim((string) $_SESSION['usuario']) : '';
    }
    $email = '';
    if (isset($_SESSION['email']) && trim((string) $_SESSION['email']) !== '') {
        $email = trim((string) $_SESSION['email']);
    } elseif (isset($_SESSION['usuario'])) {
        $email = trim((string) $_SESSION['usuario']);
    }
    $idUsuario = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($idUsuario !== '') {
        $detalle = $detalle . ' | usuario ' . $idUsuario;
    }
    if (strlen($codigo) > 50) {
        $codigo = substr($codigo, 0, 50);
    }
    if (strlen($email) > 255) {
        $email = substr($email, 0, 255);
    }
    if (strlen($detalle) > 255) {
        $detalle = substr($detalle, 0, 255);
    }
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
        ':modulo' => 'Conciliador de Saldos',
        ':detalle' => $detalle,
    ));
}
