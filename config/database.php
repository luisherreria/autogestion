<?php

class Database
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getConnection()
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=vfpmedicos;charset=utf8';
        $opciones = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );

        try {
            self::$instance = new PDO($dsn, 'root', '', $opciones);
            self::$instance->exec("SET NAMES utf8");
        } catch (PDOException $e) {
            die('Error de conexión a la base de datos: ' . $e->getMessage());
        }

        return self::$instance;
    }
}
