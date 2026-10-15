<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('ok' => false, 'error' => 'Acceso denegado.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'Método no permitido.'));
    exit;
}

if (isset($_POST['id_categoria'])) {
    $idCategoria = (int) $_POST['id_categoria'];
    $verPagos = (isset($_POST['ver_pagos']) && (string) $_POST['ver_pagos'] === '1') ? 1 : 0;
    $descripcion = isset($_POST['descripcion']) ? trim((string) $_POST['descripcion']) : '';
    if (function_exists('mb_substr')) {
        $descripcion = mb_substr($descripcion, 0, 100, 'UTF-8');
    } elseif (strlen($descripcion) > 100) {
        $descripcion = substr($descripcion, 0, 100);
    }
    if ($idCategoria <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'Categoría inválida.'));
        exit;
    }
    try {
        $pdo = Database::getConnection();
        asegurarTablaPermisosCategorias($pdo);
        $stmt = $pdo->prepare(
            'UPDATE a_permisos_categorias SET ver_pagos = :ver_pagos, descripcion = :descripcion WHERE id = :id'
        );
        $stmt->execute(array(
            ':ver_pagos' => $verPagos,
            ':descripcion' => $descripcion,
            ':id' => $idCategoria,
        ));
        $existe = $pdo->prepare('SELECT id FROM a_permisos_categorias WHERE id = :id LIMIT 1');
        $existe->execute(array(':id' => $idCategoria));
        if (!$existe->fetch()) {
            echo json_encode(array('ok' => false, 'error' => 'No se encontró la categoría.'));
            exit;
        }
        echo json_encode(array('ok' => true, 'id' => $idCategoria, 'ver_pagos' => $verPagos, 'descripcion' => $descripcion));
    } catch (Exception $e) {
        echo json_encode(array('ok' => false, 'error' => 'No se pudo guardar el permiso.'));
    }
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Registro inválido.'));
    exit;
}

$modulos = mapaModulosPermiso();
$valores = array();
$asignaciones = array();
$params = array(':id' => $id);
foreach ($modulos as $columna => $modulo) {
    $valor = (isset($_POST[$columna]) && (string) $_POST[$columna] === '1') ? 1 : 0;
    $valores[$columna] = $valor;
    $asignaciones[] = $columna . ' = :' . $columna;
    $params[':' . $columna] = $valor;
}

try {
    $pdo = Database::getConnection();
    asegurarTablaPermisos($pdo);
    $stmt = $pdo->prepare(
        'UPDATE a_permisos SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
    );
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        $existe = $pdo->prepare('SELECT id FROM a_permisos WHERE id = :id LIMIT 1');
        $existe->execute(array(':id' => $id));
        if (!$existe->fetch()) {
            echo json_encode(array('ok' => false, 'error' => 'No se encontró el permiso.'));
            exit;
        }
    }
    echo json_encode(array('ok' => true, 'id' => $id, 'permisos' => $valores));
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'error' => 'No se pudo guardar el permiso.'));
}
