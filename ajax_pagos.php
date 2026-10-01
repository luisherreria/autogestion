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

function mapaPdfsTango($directorio)
{
    $pagos = array();
    $retenciones = array();
    if (!is_dir($directorio)) {
        return array('pagos' => $pagos, 'retenciones' => $retenciones);
    }
    $rutas = glob(rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . 'O_P_*.pdf');
    if ($rutas === false) {
        return array('pagos' => $pagos, 'retenciones' => $retenciones);
    }
    foreach ($rutas as $ruta) {
        $nombre = basename($ruta);
        if (!preg_match('/^O_P_(\d{13})/i', $nombre, $coincidencia)) {
            continue;
        }
        $clave = $coincidencia[1];
        $esRetencion = stripos($nombre, '_G') !== false;
        if ($esRetencion) {
            if (!isset($retenciones[$clave]) || (strpos($retenciones[$clave], ' (2)') !== false && strpos($nombre, ' (2)') === false)) {
                $retenciones[$clave] = $nombre;
            }
        } elseif (!isset($pagos[$clave]) || (strpos($pagos[$clave], ' (2)') !== false && strpos($nombre, ' (2)') === false)) {
            $pagos[$clave] = $nombre;
        }
    }
    return array('pagos' => $pagos, 'retenciones' => $retenciones);
}

function montoPago($valor)
{
    return formatearImporte($valor);
}

function textoOGuion($valor)
{
    $texto = trim((string) $valor);
    if ($texto === '' || $texto === '0') {
        return '-';
    }
    return $texto;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
function textoEstadoMail($estado)
{
    if ((string) $estado === '0') {
        return 'Enviado (no leído)';
    }
    return 'Leído';
}

function iconoMailLiquidacion($estado, $letra)
{
    if ($estado === null || $estado === '') {
        return '';
    }
    $titulo = textoEstadoMail($estado);
    $clase = 'text-blue-500';
    $icono = 'fa-envelope';
    if ((string) $estado !== '0') {
        $clase = 'text-green-500';
        $icono = 'fa-envelope-open';
    }
    return '<span title="' . $titulo . '" class="fa-stack ' . $clase . '" style="font-size: 0.7em;">'
        . '<i class="fa-solid ' . $icono . ' fa-stack-2x"></i>'
        . '<span class="fa-stack-1x font-bold text-white" style="font-size: 0.6em; margin-top: 3px;">' . $letra . '</span>'
        . '</span>';
}

function columnaMailsLiquidacion($mailPago, $mailResumen)
{
    $iconos = iconoMailLiquidacion($mailPago, 'P') . iconoMailLiquidacion($mailResumen, 'R');
    if ($iconos === '') {
        return '-';
    }
    return '<div class="flex gap-2 justify-center">' . $iconos . '</div>';
}

function textoMailsLiquidacion($mailPago, $mailResumen)
{
    $partes = array();
    if ($mailPago !== null && $mailPago !== '') {
        $partes[] = 'P: ' . textoEstadoMail($mailPago);
    }
    if ($mailResumen !== null && $mailResumen !== '') {
        $partes[] = 'R: ' . textoEstadoMail($mailResumen);
    }
    if (count($partes) === 0) {
        return '-';
    }
    return implode(' | ', $partes);
}

function mapaMailsLiquidacion($pdo)
{
    $mapa = array();
    $stmt = $pdo->query(
        "SELECT TRIM(cod_prestador) AS cod, TRIM(nro_comprobante) AS comp, TRIM(obra_social) AS os,
                tipo_notificacion, estado_lectura
         FROM notificaciones_historial
         WHERE tipo_notificacion IN ('PAGO', 'RESUMEN')
         ORDER BY fecha_emision ASC, id_notificacion ASC"
    );
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clave = $fila['cod'] . '|' . $fila['comp'] . '|' . $fila['os'] . '|' . $fila['tipo_notificacion'];
        $mapa[$clave] = $fila['estado_lectura'];
    }
    return $mapa;
}

$codigoPrestador = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
if (!$esAdmin && !tienePermiso('pagos')) {
    echo json_encode(array('data' => array()));
    exit;
}

try {
    $pdo = Database::getConnection();
    $config = appConfig();
    $directorioPdf = isset($config['dirs']['comprobantes']) ? $config['dirs']['comprobantes'] : dirname(__FILE__) . '/archivos/comprobantes';
    $mapas = mapaPdfsPagos($directorioPdf);
    $directorioTango = dirname(__FILE__) . '/../uploads/pdftango';
    $mapasTango = mapaPdfsTango($directorioTango);
    $mailsLiquidacion = mapaMailsLiquidacion($pdo);
    $columnas = array();
    foreach ($pdo->query('SHOW COLUMNS FROM liquida') as $columna) {
        $columnas[strtoupper($columna['Field'])] = $columna;
    }
    $campoRecibo = isset($columnas['LINRORECIB']) ? $columnas['LINRORECIB']['Field'] : '';
    $campoRetencion = isset($columnas['LIRETEN']) ? $columnas['LIRETEN']['Field'] : '';
    $retencionEsImporte = $campoRetencion !== '' && (
        strpos(strtolower($columnas['LIRETEN']['Type']), 'decimal') !== false
        || strpos(strtolower($columnas['LIRETEN']['Type']), 'float') !== false
        || strpos(strtolower($columnas['LIRETEN']['Type']), 'double') !== false
    );

    $sql = 'SELECT LIPERIODO, LIPRESTADO, LINOMPREST, LIOBRASOC, LISUC, LIFACTURA,
                   LIFACTURAD, LIIMPORTE, LICOSEGURO, LIDEBITADO, LILIQUIDAD,
                   LIPAGADO, LISALDO, LIORDENPAG, LIFECHAPAG';
    if ($campoRecibo !== '') {
        $sql .= ', ' . $campoRecibo;
    }
    if ($campoRetencion !== '') {
        $sql .= ', ' . $campoRetencion;
    }
    $sql .= ' FROM liquida';
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
        $recibo = '-';
        if ($campoRecibo !== '') {
            $recibo = textoOGuion($fila[$campoRecibo]);
        }
        $retencion = '-';
        if ($campoRetencion !== '') {
            if ($retencionEsImporte) {
                $importeRetencion = trim((string) $fila[$campoRetencion]);
                $retencion = ($importeRetencion === '' || (float) $importeRetencion == 0) ? '-' : montoPago($fila[$campoRetencion]);
            } else {
                $retencion = textoOGuion($fila[$campoRetencion]);
            }
        }
        $ordenPago = trim((string) $fila['LIORDENPAG']);
        if (strpos($ordenPago, '.') !== false) {
            $ordenPago = substr($ordenPago, 0, strpos($ordenPago, '.'));
        }
        $tango = '';
        if ($ordenPago !== '' && $ordenPago !== '0' && ctype_digit($ordenPago)) {
            $fechaCruda = trim((string) $fila['LIFECHAPAG']);
            $marcaPago = false;
            if ($fechaCruda !== '' && strpos($fechaCruda, '0000-00-00') !== 0) {
                $marcaPago = strtotime(substr($fechaCruda, 0, 10));
            }
            if ($marcaPago !== false && $marcaPago >= strtotime('2026-01-10')) {
                $opRellenada = str_pad($ordenPago, 13, '0', STR_PAD_LEFT);
                if (isset($mapasTango['pagos'][$opRellenada])) {
                    $tango = $mapasTango['pagos'][$opRellenada];
                }
            }
        }
        $retencionPdf = '';
        if ($retencion !== '-' && $ordenPago !== '' && $ordenPago !== '0' && ctype_digit($ordenPago)) {
            $opRetencion = str_pad($ordenPago, 13, '0', STR_PAD_LEFT);
            if (isset($mapasTango['retenciones'][$opRetencion])) {
                $retencionPdf = $mapasTango['retenciones'][$opRetencion];
            }
        }
        $comprobanteMail = trim((string) $fila['LISUC']) . '-' . trim((string) $fila['LIFACTURA']);
        $comprobanteRelleno = $suc . '-' . $factura;
        $baseMail = trim((string) $fila['LIPRESTADO']) . '|' . $comprobanteMail . '|' . trim((string) $fila['LIOBRASOC']);
        $baseMailRelleno = trim((string) $fila['LIPRESTADO']) . '|' . $comprobanteRelleno . '|' . trim((string) $fila['LIOBRASOC']);
        $mailPago = null;
        $mailResumen = null;
        if (isset($mailsLiquidacion[$baseMail . '|PAGO'])) {
            $mailPago = $mailsLiquidacion[$baseMail . '|PAGO'];
        } elseif (isset($mailsLiquidacion[$baseMailRelleno . '|PAGO'])) {
            $mailPago = $mailsLiquidacion[$baseMailRelleno . '|PAGO'];
        }
        if (isset($mailsLiquidacion[$baseMail . '|RESUMEN'])) {
            $mailResumen = $mailsLiquidacion[$baseMail . '|RESUMEN'];
        } elseif (isset($mailsLiquidacion[$baseMailRelleno . '|RESUMEN'])) {
            $mailResumen = $mailsLiquidacion[$baseMailRelleno . '|RESUMEN'];
        }
        $data[] = array(
            'periodo' => $periodo,
            'prestador' => trim((string) $fila['LIPRESTADO']),
            'prestador_nombre' => trim((string) $fila['LINOMPREST']),
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
            'orden' => $ordenPago,
            'tango' => $tango,
            'fecha' => $fechaPago,
            'recibo' => $recibo,
            'retencion' => $retencion,
            'retencion_pdf' => $retencionPdf,
            'pagada' => ($pagado > 0 && $saldo == 0) ? 1 : 0,
            'notificaciones' => columnaMailsLiquidacion($mailPago, $mailResumen),
            'notificaciones_txt' => textoMailsLiquidacion($mailPago, $mailResumen),
        );
    }

    echo json_encode(array('data' => $data));
} catch (Exception $e) {
    echo json_encode(array('data' => array(), 'error' => 'No se pudieron leer los pagos.'));
}
exit;
