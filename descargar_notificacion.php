<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

try {
    $pdo = Database::getConnection();
    asegurarTablaNotificaciones($pdo);
    $stmt = $pdo->prepare(
        'SELECT id_notificacion, cod_prestador, tipo_notificacion, archivo_adjunto, remitente_mail, destinatarios_mail
         FROM notificaciones_historial
         WHERE id_notificacion = :id
         LIMIT 1'
    );
    $stmt->execute(array(':id' => $id));
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

if (!$fila || !notificacionVisible($fila)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$nombre = basename(str_replace('\\', '/', trim((string) $fila['archivo_adjunto'])));
if ($nombre === '' || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$config = appConfig();
$base = isset($config['dirs']['comprobantes']) ? $config['dirs']['comprobantes'] : dirname(__FILE__) . '/archivos/comprobantes';
$baseReal = realpath($base);
$archivoReal = realpath($base . DIRECTORY_SEPARATOR . $nombre);
if ($baseReal === false || $archivoReal === false || !is_file($archivoReal) || strpos($archivoReal, $baseReal) !== 0) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($archivoReal));
readfile($archivoReal);
exit;
