<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (session_id() === '') {
    session_start();
}

require_once dirname(__FILE__) . '/../config/database.php';
require_once dirname(__FILE__) . '/util.php';
