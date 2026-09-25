<?php

function appConfig()
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__FILE__) . '/../config/app.php';
    }
    return $config;
}

function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatearFecha($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '';
    }
    $marca = strtotime($valor);
    if ($marca === false) {
        return $valor;
    }
    return date('d/m/Y', $marca);
}

function formatearImporte($valor)
{
    return '$ ' . number_format((float) $valor, 2, ',', '.');
}

function urlDescarga($tipo, $archivo, $ver)
{
    $url = 'descargar.php?tipo=' . rawurlencode($tipo) . '&archivo=' . rawurlencode($archivo);
    if ($ver) {
        $url .= '&modo=ver';
    }
    return $url;
}

function listarArchivos($directorio)
{
    $items = array();
    if (!is_dir($directorio)) {
        return $items;
    }
    $nombres = scandir($directorio);
    if ($nombres === false) {
        return $items;
    }
    foreach ($nombres as $nombre) {
        if ($nombre === '.' || $nombre === '..' || $nombre === '.gitkeep' || $nombre[0] === '.') {
            continue;
        }
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (is_file($ruta)) {
            $items[] = $nombre;
        }
    }
    natcasesort($items);
    return array_values($items);
}

function buscarArchivo($directorio, $candidatos)
{
    foreach ($candidatos as $nombre) {
        $nombre = basename($nombre);
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            continue;
        }
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (is_file($ruta)) {
            return $nombre;
        }
    }
    return '';
}

function candidatosPdfNumero($numero)
{
    $numero = trim((string) $numero);
    $candidatos = array();
    if ($numero === '') {
        return $candidatos;
    }
    $candidatos[] = $numero . '.pdf';
    $sinCeros = ltrim($numero, '0');
    if ($sinCeros !== '' && $sinCeros !== $numero) {
        $candidatos[] = $sinCeros . '.pdf';
    }
    return $candidatos;
}

function emailEstaEnLista($listaMails, $emailIngresado)
{
    $emailIngresado = strtolower(trim($emailIngresado));
    if ($emailIngresado === '') {
        return false;
    }

    $porComa = explode(',', (string) $listaMails);
    $limpios = array();
    foreach ($porComa as $trozo) {
        $porPuntoComa = explode(';', $trozo);
        foreach ($porPuntoComa as $correo) {
            $correo = strtolower(trim($correo));
            if ($correo !== '') {
                $limpios[] = $correo;
            }
        }
    }

    return in_array($emailIngresado, $limpios, true);
}
