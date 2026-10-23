<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=normativas');
    exit;
}

mostrarModuloDocumental('normativas', 'Normativa del prestador');
