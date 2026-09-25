<?php

require_once dirname(__FILE__) . '/bootstrap.php';

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] != 1) {
    header('Location: login.php');
    exit;
}
