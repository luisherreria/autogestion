<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

function mapaPdfsPagos($directorio)
{
    $debitos = array();
    $csn = array();
    if (!is_dir($directorio)) {
        return array('debitos' => $debitos, 'csn' => $csn);
    }
    $nombres = scandir($directorio);
    if ($nombres === false) {
        return array('debitos' => $debitos, 'csn' => $csn);
    }
    foreach ($nombres as $nombre) {
        if ($nombre === '.' || $nombre === '..') {
            continue;
        }
        if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }
        $partes = explode('_', $nombre);
        if (count($partes) < 6) {
            continue;
        }
        $periodo = str_replace('-', '/', $partes[0]);
        $suc = '';
        $factura = '';
        $total = count($partes);
        for ($idx = 0; $idx < $total; $idx++) {
            $pieza = $partes[$idx];
            $siguiente = isset($partes[$idx + 1]) ? $partes[$idx + 1] : '';
            if (strlen($pieza) === 4 && ctype_digit($pieza) && strlen($siguiente) === 8 && ctype_digit($siguiente)) {
                $suc = $pieza;
                $factura = $siguiente;
                break;
            }
        }
        if ($suc === '' || $factura === '') {
            continue;
        }
        $clave = $periodo . '_' . $suc . '_' . $factura;
        if (strpos(strtoupper($nombre), '_CSN.PDF') !== false) {
            $csn[$clave] = $nombre;
        } else {
            $debitos[$clave] = $nombre;
        }
    }
    return array('debitos' => $debitos, 'csn' => $csn);
}

function montoPago($valor)
{
    return number_format((float) $valor, 2, '.', '');
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigoPrestador = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
if (!$esAdmin && !tienePermiso('pagos')) {
    echo json_encode(array('data' => array()));
    exit;
}

try {
    $config = appConfig();
    $directorioPdf = isset($config['dirs']['comprobantes']) ? $config['dirs']['comprobantes'] : dirname(__FILE__) . '/archivos/comprobantes';
    $mapas = mapaPdfsPagos($directorioPdf);

    $pdo = Database::getConnection();
    $sql = 'SELECT LIPERIODO, LIPRESTADO, LIOBRASOC, LISUC, LIFACTURA,
                   LIFACTURAD, LIIMPORTE, LICOSEGURO, LIDEBITADO, LILIQUIDAD,
                   LIPAGADO, LISALDO, LIORDENPAG, LIFECHAPAG
            FROM liquida';
    $parametros = array();
    if (!$esAdmin) {
        $sql .= ' WHERE TRIM(LIPRESTADO) = :codigo';
        $parametros[':codigo'] = $codigoPrestador;
    }
    $sql .= ' ORDER BY LIPERIODO DESC, LIFACTURA ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    $data = array();
    while ($fila = $stmt->fetch()) {
        $periodo = trim((string) $fila['LIPERIODO']);
        $suc = str_pad(trim((string) $fila['LISUC']), 4, '0', STR_PAD_LEFT);
        $factura = str_pad(trim((string) $fila['LIFACTURA']), 8, '0', STR_PAD_LEFT);
        $clave = $periodo . '_' . $suc . '_' . $factura;
        $pagado = (float) $fila['LIPAGADO'];
        $saldo = (float) $fila['LISALDO'];
        $fechaPago = formatearFecha($fila['LIFECHAPAG']);
        if ($fechaPago === '') {
            $fechaPago = '/ /';
        }
        $data[] = array(
            'periodo' => $periodo,
            'prestador' => trim((string) $fila['LIPRESTADO']),
            'obrasocial' => trim((string) $fila['LIOBRASOC']),
            'suc' => $suc,
            'factura' => $factura,
            'facturado' => montoPago($fila['LIFACTURAD']),
            'importe' => montoPago($fila['LIIMPORTE']),
            'coseguro' => montoPago($fila['LICOSEGURO']),
            'csn' => isset($mapas['csn'][$clave]) ? $mapas['csn'][$clave] : '',
            'debitado' => montoPago($fila['LIDEBITADO']),
            'deb' => isset($mapas['debitos'][$clave]) ? $mapas['debitos'][$clave] : '',
            'liquidado' => montoPago($fila['LILIQUIDAD']),
            'pagado' => montoPago($fila['LIPAGADO']),
            'saldo' => montoPago($saldo),
            'orden' => trim((string) $fila['LIORDENPAG']),
            'fecha' => $fechaPago,
            'pagada' => ($pagado > 0 && $saldo == 0) ? 1 : 0,
        );
    }

    echo json_encode(array('data' => $data));
} catch (Exception $e) {
    echo json_encode(array('data' => array(), 'error' => 'No se pudieron leer los pagos.'));
}
exit;
