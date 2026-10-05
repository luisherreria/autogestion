<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acceso denegado.';
    exit;
}

$codigoDestino = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
$emailDestino = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($codigoDestino === '' || $emailDestino === '') {
    echo 'Faltan los datos del prestador.';
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT CODIGO, NOMBRE, MAIL, MAIL_AUTO, MAIL_DEB, MAIL_PAGO, MAILCONTRA
         FROM cartilla.ebamp
         WHERE TRIM(CODIGO) = :codigo
         LIMIT 1'
    );
    $stmt->execute(array(':codigo' => $codigoDestino));
    $prestador = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo 'No se pudo abrir el acceso.';
    exit;
}

if (!$prestador) {
    echo 'No se encontró el prestador.';
    exit;
}

$tipos = tiposCorreoDelEmail($prestador, $emailDestino);
$mailPrincipal = isset($prestador['MAIL']) ? $prestador['MAIL'] : '';
if (count($tipos) === 0 && emailEstaEnLista($mailPrincipal, $emailDestino)) {
    foreach (columnasCorreoPrestador() as $columna => $tipo) {
        if (trim((string) $prestador[$columna]) !== '') {
            $tipos[] = $tipo;
        }
    }
    if (count($tipos) === 0) {
        $tipos = array_values(columnasCorreoPrestador());
    }
}
if (count($tipos) === 0) {
    echo 'Ese correo no pertenece al prestador.';
    exit;
}

if (empty($_SESSION['admin_original'])) {
    $_SESSION['admin_backup'] = array(
        'usuario' => isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '',
        'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '',
        'nombre' => isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '',
    );
}
$_SESSION['admin_original'] = 1;

session_regenerate_id(true);
$_SESSION['logueado'] = 1;
$_SESSION['rol'] = 'prestador';
$_SESSION['es_admin'] = 0;
$_SESSION['usuario'] = $emailDestino;
$_SESSION['email'] = $emailDestino;
$_SESSION['codigo'] = trim((string) $prestador['CODIGO']);
$_SESSION['nombre'] = trim((string) $prestador['NOMBRE']);
$_SESSION['mail_auto'] = trim((string) $prestador['MAIL_AUTO']);
$_SESSION['mail_deb'] = trim((string) $prestador['MAIL_DEB']);
$_SESSION['mail_pago'] = trim((string) $prestador['MAIL_PAGO']);
$_SESSION['mailcontra'] = trim((string) $prestador['MAILCONTRA']);
$_SESSION['tipos_correo'] = $tipos;
$_SESSION['permisos'] = permisosPorTipos($pdo, $tipos);
$_SESSION['usuario_nombre'] = '';
$_SESSION['usuario_apellido'] = '';
$_SESSION['mostrar_bienvenida'] = 1;

try {
    $stmtPerfil = $pdo->prepare(
        'SELECT nombre, apellido
         FROM usuarios_prestadores
         WHERE codigo_prestador = :codigo AND email_login = :email
         LIMIT 1'
    );
    $stmtPerfil->execute(array(
        ':codigo' => $_SESSION['codigo'],
        ':email' => $_SESSION['email'],
    ));
    $perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);
    if ($perfil && trim($perfil['nombre']) !== '' && trim($perfil['apellido']) !== '') {
        $_SESSION['usuario_nombre'] = trim($perfil['nombre']);
        $_SESSION['usuario_apellido'] = trim($perfil['apellido']);
    }
} catch (Exception $e) {
    $_SESSION['usuario_nombre'] = '';
    $_SESSION['usuario_apellido'] = '';
}

completarPrestadorNotificaciones($pdo);
header('Location: index.php');
exit;
