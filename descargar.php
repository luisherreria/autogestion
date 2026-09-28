<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$config = appConfig();
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$archivo = isset($_GET['archivo']) ? str_replace('\\', '/', $_GET['archivo']) : '';
$ver = isset($_GET['modo']) && $_GET['modo'] === 'ver';
$partes = explode('/', $archivo);
$relativoValido = $archivo !== ''
    && strpos($archivo, '..') === false
    && $archivo[0] !== '/'
    && $archivo[0] !== '.'
    && count($partes) <= 2;

if ($relativoValido) {
    foreach ($partes as $parte) {
        if ($parte === '' || $parte === '.' || $parte === '..' || $parte[0] === '.') {
            $relativoValido = false;
        }
    }
}

if (!isset($config['dirs'][$tipo]) || !$relativoValido) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$carpeta = count($partes) === 2 ? $partes[0] : '';
if ($carpeta !== '' && !prestadorPuedeVerCarpeta(Database::getConnection(), $carpeta)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$baseReal = realpath($config['dirs'][$tipo]);
$ruta = $config['dirs'][$tipo] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $archivo);
$archivoReal = realpath($ruta);

if ($baseReal === false || $archivoReal === false || !is_file($archivoReal)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$baseReal = rtrim($baseReal, '\\/') . DIRECTORY_SEPARATOR;
if (strpos($archivoReal, $baseReal) !== 0) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$archivo = basename($archivo);

$extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
$tipos = array(
    'pdf' => 'application/pdf',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv; charset=UTF-8',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
);
$mime = isset($tipos[$extension]) ? $tipos[$extension] : 'application/octet-stream';
$disposicion = $ver ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposicion . '; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($archivoReal));
header('X-Content-Type-Options: nosniff');
readfile($archivoReal);
exit;
