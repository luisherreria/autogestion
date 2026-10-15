<?php

require_once dirname(__FILE__) . '/bootstrap.php';

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] != 1) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $_SESSION['categoria_descripcion'] = '';
} else {
    $filaCategoria = filaPermisoCategoria(isset($_SESSION['categoria']) ? $_SESSION['categoria'] : '');
    $_SESSION['categoria_descripcion'] = $filaCategoria['descripcion'];
}
aplicarFiltroCategoriaLiquidaciones();
