<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$nombre = isset($_GET['archivo']) ? basename(str_replace('\\', '/', $_GET['archivo'])) : '';
if ($nombre === '' || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
if (!$esAdmin) {
    $codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
    $partes = explode('_', $nombre);
    $prestadorArchivo = isset($partes[1]) ? trim($partes[1]) : '';
    if ($codigo === '' || strcasecmp($prestadorArchivo, $codigo) !== 0) {
        header('HTTP/1.1 404 Not Found');
        echo 'Archivo no disponible';
        exit;
    }
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
