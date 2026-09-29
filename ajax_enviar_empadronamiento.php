<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!tienePermiso('ver_empadronamiento')) {
    echo json_encode(array('ok' => false, 'mensaje' => 'Acceso denegado.'));
    exit;
}

$estadoPadron = isset($_POST['estado_padron']) ? trim($_POST['estado_padron']) : '';
if ($estadoPadron === 'activo') {
    $codigoTpl = 'EMPADRONAMIENTO_ACTIVO';
} elseif ($estadoPadron === 'fuera_padron') {
    $codigoTpl = 'EMPADRONAMIENTO_FUERA_PADRON';
} elseif ($estadoPadron === 'consulta') {
    $codigoTpl = 'EMPADRONAMIENTO_CONSULTA';
} else {
    echo json_encode(array('ok' => false, 'mensaje' => 'El estado del padrón no es válido.'));
    exit;
}

$institucion = isset($_POST['institucion']) ? trim($_POST['institucion']) : '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$carnet = isset($_POST['carnet']) ? trim($_POST['carnet']) : '';
$obraSocial = isset($_POST['obra_social']) ? trim($_POST['obra_social']) : '';
$plan = isset($_POST['plan']) ? trim($_POST['plan']) : '';
$busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';
$consulta = isset($_POST['consulta']) ? trim($_POST['consulta']) : '';
$internacion = isset($_POST['internacion']) ? trim($_POST['internacion']) : '';
if ($internacion === 'si') {
    $internacionTexto = 'Sí';
} elseif ($internacion === 'no') {
    $internacionTexto = 'No';
} else {
    $internacionTexto = '';
}

if ($institucion === '') {
    $institucion = isset($_SESSION['nombre']) ? trim($_SESSION['nombre']) : '';
}
if ($estadoPadron === 'activo' && $telefono === '') {
    echo json_encode(array('ok' => false, 'mensaje' => 'Ingrese un teléfono de contacto.'));
    exit;
}
if ($estadoPadron === 'activo' && $internacionTexto === '') {
    echo json_encode(array('ok' => false, 'mensaje' => 'Indique si se trata de una internación.'));
    exit;
}
if ($estadoPadron === 'consulta' && $consulta === '') {
    echo json_encode(array('ok' => false, 'mensaje' => 'Escriba la consulta.'));
    exit;
}

$reemplazos = array(
    '{{institucion}}' => $institucion,
    '{{telefono}}' => $telefono,
    '{{nombre}}' => $nombre,
    '{{dni}}' => $dni,
    '{{carnet}}' => $carnet,
    '{{obra_social}}' => $obraSocial,
    '{{plan}}' => $plan,
    '{{busqueda}}' => $busqueda,
    '{{consulta}}' => $consulta,
    '{{internacion}}' => $internacionTexto,
    '{{codigo}}' => isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '',
);

try {
    $pdo = Database::getConnection();
    asegurarPlantillasEmpadronamiento($pdo);

    $stmtPlantilla = $pdo->prepare(
        'SELECT codigo, asunto, cuerpo, destinatarios, cc, cco, activo, metodo_envio
         FROM t_plantillas_emails
         WHERE TRIM(codigo) = :codigo
         LIMIT 1'
    );
    $stmtPlantilla->execute(array(':codigo' => $codigoTpl));
    $plantilla = $stmtPlantilla->fetch();
    if (!$plantilla || (int) $plantilla['activo'] !== 1) {
        echo json_encode(array('ok' => false, 'mensaje' => 'No hay una plantilla activa para este aviso.'));
        exit;
    }

    $metodo = trim((string) $plantilla['metodo_envio']);
    if ($metodo === '') {
        $metodo = 'SMTP';
    }
    $stmtSmtp = $pdo->prepare(
        'SELECT host, puerto, usuario, password, remitente_email, remitente_nombre
         FROM t_config_smtp
         WHERE metodo_envio = :metodo
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmtSmtp->execute(array(':metodo' => $metodo));
    $smtp = $stmtSmtp->fetch();
    if (!$smtp || trim((string) $smtp['host']) === '') {
        registrarLogEmail($pdo, $codigoTpl, '', '', 'ERROR', 'No hay configuración SMTP para el método ' . $metodo);
        echo json_encode(array('ok' => false, 'mensaje' => 'No hay un servidor de correo configurado.'));
        exit;
    }

    $asunto = aplicarReemplazosEmpadronamiento((string) $plantilla['asunto'], $reemplazos);
    $cuerpo = aplicarReemplazosEmpadronamiento((string) $plantilla['cuerpo'], $reemplazos);
    if ($estadoPadron === 'consulta') {
        $destinatarios = array('autorizaciones@comedica.com.ar');
    } else {
        $destinatarios = separarCorreosEmpadronamiento($plantilla['destinatarios']);
    }
    $copias = separarCorreosEmpadronamiento($plantilla['cc']);
    $ocultas = separarCorreosEmpadronamiento($plantilla['cco']);
    $destinatariosLog = implode(', ', array_merge($destinatarios, $copias, $ocultas));

    if (count($destinatarios) === 0) {
        registrarLogEmail($pdo, $codigoTpl, $destinatariosLog, $asunto, 'ERROR', 'La plantilla no tiene destinatarios válidos.');
        echo json_encode(array('ok' => false, 'mensaje' => 'La plantilla no tiene destinatarios.'));
        exit;
    }

    if (!cargarPhpMailerEmpadronamiento()) {
        registrarLogEmail($pdo, $codigoTpl, $destinatariosLog, $asunto, 'ERROR', 'PHPMailer no está disponible.');
        echo json_encode(array('ok' => false, 'mensaje' => 'No se pudo preparar el envío de correo.'));
        exit;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
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
        foreach ($destinatarios as $correo) {
            $mail->addAddress($correo);
        }
        foreach ($copias as $correo) {
            $mail->addCC($correo);
        }
        foreach ($ocultas as $correo) {
            $mail->addBCC($correo);
        }

        $esHtml = preg_match('/<[a-z][\s\S]*>/i', $cuerpo) === 1;
        $mail->isHTML($esHtml);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;
        $mail->send();

        $estado = 'ENVIADO';
        $mensajeServidor = 'ÉXITO: Correo despachado correctamente';
        registrarLogEmail($pdo, $codigoTpl, $destinatariosLog, $asunto, $estado, $mensajeServidor);
        echo json_encode(array('ok' => true, 'mensaje' => 'Correo enviado exitosamente.'));
    } catch (Exception $e) {
        $estado = 'ERROR';
        $mensajeServidor = $e->getMessage();
        if (isset($mail) && is_object($mail) && trim((string) $mail->ErrorInfo) !== '') {
            $mensajeServidor = $mail->ErrorInfo;
        }
        registrarLogEmail($pdo, $codigoTpl, $destinatariosLog, $asunto, $estado, $mensajeServidor);
        echo json_encode(array('ok' => false, 'mensaje' => 'No se pudo enviar el correo.'));
    }
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'mensaje' => 'No se pudo enviar el correo.'));
}

function aplicarReemplazosEmpadronamiento($texto, $reemplazos)
{
    foreach ($reemplazos as $token => $valor) {
        $texto = str_replace($token, $valor, $texto);
    }
    return $texto;
}

function separarCorreosEmpadronamiento($lista)
{
    $lista = trim((string) $lista);
    if ($lista === '') {
        return array();
    }
    $partes = preg_split('/[,;]+/', $lista);
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

function cargarPhpMailerEmpadronamiento()
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

function registrarLogEmail($pdo, $codigoPlantilla, $destinatarios, $asunto, $estado, $mensajeServidor)
{
    $stmt = $pdo->prepare(
        'INSERT INTO t_log_emails
            (fecha, modulo_origen, codigo_plantilla, destinatarios, asunto, estado, mensaje_servidor)
         VALUES
            (NOW(), :modulo_origen, :codigo_plantilla, :destinatarios, :asunto, :estado, :mensaje_servidor)'
    );
    $stmt->execute(array(
        ':modulo_origen' => 'Empadronamiento',
        ':codigo_plantilla' => $codigoPlantilla,
        ':destinatarios' => $destinatarios,
        ':asunto' => substr($asunto, 0, 255),
        ':estado' => ($estado === 'ENVIADO') ? 'ENVIADO' : 'ERROR',
        ':mensaje_servidor' => $mensajeServidor,
    ));
}

function asegurarPlantillasEmpadronamiento($pdo)
{
    $destino = '';
    $filaDestino = $pdo->query(
        "SELECT destinatarios FROM t_plantillas_emails WHERE codigo = 'ALERTA_FACTURA_ALTA' LIMIT 1"
    )->fetch();
    if ($filaDestino && trim((string) $filaDestino['destinatarios']) !== '') {
        $destino = trim($filaDestino['destinatarios']);
    }

    $plantillas = array(
        array(
            'codigo' => 'EMPADRONAMIENTO_ACTIVO',
            'nombre' => 'Empadronamiento activo',
            'asunto' => 'Verificación de empadronamiento - {{institucion}}',
            'cuerpo' => "Verificación de empadronamiento\n\nInstitución: {{institucion}}\nCódigo: {{codigo}}\nTeléfono: {{telefono}}\nAfiliado: {{nombre}}\nDNI: {{dni}}\nCarnet: {{carnet}}\nObra social: {{obra_social}}\nPlan: {{plan}}\nInternación: {{internacion}}\nBúsqueda: {{busqueda}}\n",
        ),
        array(
            'codigo' => 'EMPADRONAMIENTO_FUERA_PADRON',
            'nombre' => 'Empadronamiento fuera de padrón',
            'asunto' => 'Notificación fuera de padrón - {{institucion}}',
            'cuerpo' => "Notificación fuera de padrón\n\nInstitución: {{institucion}}\nCódigo: {{codigo}}\nTeléfono: {{telefono}}\nBúsqueda: {{busqueda}}\n\nEl afiliado no se encuentra en el padrón activo.\n",
            'destinatarios' => $destino,
        ),
        array(
            'codigo' => 'EMPADRONAMIENTO_CONSULTA',
            'nombre' => 'Consulta de empadronamiento',
            'asunto' => 'Consulta de empadronamiento - {{busqueda}}',
            'cuerpo' => "Consulta de empadronamiento\n\nInstitución: {{institucion}}\nCódigo: {{codigo}}\nTeléfono: {{telefono}}\nBúsqueda: {{busqueda}}\n\n{{consulta}}\n",
            'destinatarios' => 'autorizaciones@comedica.com.ar',
        ),
    );

    $existe = $pdo->prepare('SELECT id FROM t_plantillas_emails WHERE codigo = :codigo LIMIT 1');
    $insertar = $pdo->prepare(
        'INSERT INTO t_plantillas_emails
            (codigo, nombre_uso, asunto, cuerpo, destinatarios, cc, cco, activo, metodo_envio)
         VALUES
            (:codigo, :nombre, :asunto, :cuerpo, :destinatarios, :cc, :cco, 1, :metodo)'
    );
    foreach ($plantillas as $plantilla) {
        $existe->execute(array(':codigo' => $plantilla['codigo']));
        if ($existe->fetch()) {
            continue;
        }
        $insertar->execute(array(
            ':codigo' => $plantilla['codigo'],
            ':nombre' => $plantilla['nombre'],
            ':asunto' => $plantilla['asunto'],
            ':cuerpo' => $plantilla['cuerpo'],
            ':destinatarios' => isset($plantilla['destinatarios']) ? $plantilla['destinatarios'] : $destino,
            ':cc' => '',
            ':cco' => '',
            ':metodo' => 'SMTP',
        ));
    }
    $pdo->exec(
        "UPDATE t_plantillas_emails
         SET cuerpo = REPLACE(cuerpo, 'Obra social: {{obra_social}}', 'Obra social: {{obra_social}}\nPlan: {{plan}}')
         WHERE codigo = 'EMPADRONAMIENTO_ACTIVO'
           AND cuerpo NOT LIKE '%{{plan}}%'"
    );
}
