<?php
// 1. Forzamos el apagado de errores y abrimos un buffer para atrapar la basura
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';
require_once dirname(__FILE__) . '/lib/PHPExcel/PHPExcel.php';

header('Content-Type: application/json; charset=UTF-8');

function textoCeldaExcel($valor) {
    if ($valor === null) return '';
    if (is_bool($valor)) return $valor ? '1' : '0';
    return trim((string) $valor);
}

function tituloColumnaExcel($valor) {
    $texto = textoCeldaExcel($valor);
    $texto = str_replace(
        array('á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'),
        array('a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'),
        $texto
    );
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function buscarColumnaExcel($titulos, $agujas, $usados) {
    foreach ($agujas as $aguja) {
        foreach ($titulos as $indice => $titulo) {
            if (isset($usados[$indice]) || $titulo === '') continue;
            if (strpos($titulo, $aguja) !== false) return $indice;
        }
    }
    return null;
}

function digitosExcel($valor) {
    return preg_replace('/[^0-9]/', '', textoCeldaExcel($valor));
}

function fechaConciliacion($hoja, $columna, $filaExcel, $valorFormateado) {
    $resultado = array('texto' => '', 'orden' => '');
    $marca = null;
    if ($columna !== null) {
        $coordenada = PHPExcel_Cell::stringFromColumnIndex($columna) . $filaExcel;
        $celda = $hoja->getCell($coordenada);
        $valor = $celda->getValue();
        if (is_numeric($valor) && PHPExcel_Shared_Date::isDateTime($celda)) {
            $marca = PHPExcel_Shared_Date::ExcelToPHPObject($valor);
        }
    }
    if ($marca === null && $valorFormateado !== '' && $valorFormateado !== null && is_numeric($valorFormateado)) {
        $marca = PHPExcel_Shared_Date::ExcelToPHPObject($valorFormateado);
    }
    if ($marca === null) {
        $texto = trim((string) $valorFormateado);
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $texto, $partes)) {
            $primero = (int) $partes[1];
            $segundo = (int) $partes[2];
            $anio = (int) $partes[3];
            if ($segundo > 12) {
                $mes = $primero; $dia = $segundo;
            } elseif ($primero > 12) {
                $dia = $primero; $mes = $segundo;
            } else {
                $mes = $primero; $dia = $segundo;
            }
            if (checkdate($mes, $dia, $anio)) {
                $marca = DateTime::createFromFormat('Y-n-j', $anio . '-' . $mes . '-' . $dia);
            }
        }
    }
    if ($marca instanceof DateTime) {
        $resultado['texto'] = $marca->format('d/m/Y');
        $resultado['orden'] = $marca->format('Y-m-d');
    }
    return $resultado;
}

function enlacePdfConciliacion($href, $texto) {
    $texto = trim((string) $texto);
    if ($texto === '') {
        $texto = 'PDF';
    }
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-red-600 hover:text-red-800" title="Descargar PDF"><i class="fa-solid fa-file-pdf"></i> ' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</a>';
}

function mapaPdfsComprobantesConciliacion($directorio) {
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
        $totalPartes = count($partes);
        for ($idx = 0; $idx < $totalPartes; $idx++) {
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

function mapaPdfsTangoConciliacion($directorio) {
    $pagos = array();
    if (!is_dir($directorio)) {
        return $pagos;
    }
    $rutas = glob(rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . 'O_P_*.pdf');
    if ($rutas === false) {
        return $pagos;
    }
    foreach ($rutas as $ruta) {
        $nombre = basename($ruta);
        if (!preg_match('/^O_P_(\d{13})/i', $nombre, $coincidencia)) {
            continue;
        }
        if (stripos($nombre, '_G') !== false) {
            continue;
        }
        $clave = $coincidencia[1];
        if (!isset($pagos[$clave]) || (strpos($pagos[$clave], ' (2)') !== false && strpos($nombre, ' (2)') === false)) {
            $pagos[$clave] = $nombre;
        }
    }
    return $pagos;
}

function comprobanteConciliacion($sucursal, $numero) {
    $sucursal = digitosExcel($sucursal);
    $numero = digitosExcel($numero);
    if ($sucursal === '' && $numero === '') return '';
    return str_pad($sucursal, 4, '0', STR_PAD_LEFT) . '-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
if (!$esAdmin) {
    ob_clean();
    echo json_encode(array('status' => 'error', 'mensaje' => 'No autorizado.', 'data' => array()));
    exit;
}

$archivo = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;
if (!$archivo || !isset($archivo['error']) || (int) $archivo['error'] !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
    ob_clean();
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se recibió el archivo.', 'data' => array()));
    exit;
}

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if ($extension !== 'xls' && $extension !== 'xlsx') {
    ob_clean();
    echo json_encode(array('status' => 'error', 'mensaje' => 'El archivo debe ser .xls o .xlsx.', 'data' => array()));
    exit;
}

try {
    $libro = PHPExcel_IOFactory::load($archivo['tmp_name']);
    $hoja = $libro->getActiveSheet();
    $filas = $hoja->toArray();
    $titulos = array();
    if (isset($filas[0]) && is_array($filas[0])) {
        foreach ($filas[0] as $indice => $titulo) {
            $titulos[$indice] = tituloColumnaExcel($titulo);
        }
    }
    
    $usados = array();
    $colSucursal = buscarColumnaExcel($titulos, array('cod comprobant', 'sucursal'), $usados);
    if ($colSucursal !== null) $usados[$colSucursal] = true;
    
    $colFecha = buscarColumnaExcel($titulos, array('fecha fact', 'emision', 'fecha'), $usados);
    if ($colFecha !== null) $usados[$colFecha] = true;
    
    $colNumero = buscarColumnaExcel($titulos, array('factura', 'numero', 'nro'), $usados);
    if ($colNumero !== null) $usados[$colNumero] = true;
    
    $colPrestador = buscarColumnaExcel($titulos, array('codprest', 'prestador', 'codigo'), $usados);
    if ($colPrestador !== null) $usados[$colPrestador] = true;
    
    $colImporte = buscarColumnaExcel($titulos, array('importe'), $usados);
    if ($colImporte !== null) $usados[$colImporte] = true;
    
    $colSaldo = buscarColumnaExcel($titulos, array('saldo'), $usados);
    
    $faltantes = array();
    if ($colPrestador === null) $faltantes[] = 'Código / Prestador';
    if ($colSucursal === null) $faltantes[] = 'Cod comprobante / Sucursal';
    if ($colNumero === null) $faltantes[] = 'Número / Factura';
    if ($colImporte === null) $faltantes[] = 'Importe';
    if ($colSaldo === null) $faltantes[] = 'Saldo';
    
    if (count($faltantes) > 0) {
        ob_clean();
        echo json_encode(array(
            'status' => 'error',
            'mensaje' => 'No se encontraron las columnas: ' . implode(', ', $faltantes) . '.',
            'data' => array(),
        ));
        exit;
    }
    
    $pdo = Database::getConnection();
    $diccionarioPrestadores = array();
    $stmtPrestadores = $pdo->query('SELECT CODIGO, NOMBRE, MAIL_DEB FROM cartilla.ebamp');
    while ($row = $stmtPrestadores->fetch(PDO::FETCH_ASSOC)) {
        $codigoPrestador = trim((string) $row['CODIGO']);
        if ($codigoPrestador === '') continue;
        $diccionarioPrestadores[$codigoPrestador] = array(
            'codigo' => $codigoPrestador,
            'nombre' => trim((string) $row['NOMBRE']),
            'mail_deb' => trim((string) $row['MAIL_DEB']),
        );
    }
    
    $facturasEnSistema = array();
    $stmtLiquida = $pdo->query(
        'SELECT TRIM(LISUC) AS sucursal, TRIM(LIFACTURA) AS factura, TRIM(LIPRESTADO) AS codigo,
                TRIM(LIPERIODO) AS periodo, TRIM(liordenpag) AS orden_pago, LIFECHAPAG AS fecha_pago,
                LIDEBITADO AS debito, LICOSEGURO AS csn
         FROM liquida'
    );
    while ($row = $stmtLiquida->fetch(PDO::FETCH_ASSOC)) {
        $facturasEnSistema[$row['codigo'] . '|' . $row['sucursal'] . '|' . $row['factura']] = $row;
    }

    $avisosComprobante = array();
    $stmtAvisos = $pdo->query(
        "SELECT TRIM(cod_prestador) AS codigo, TRIM(nro_comprobante) AS comp, tipo_notificacion, archivo_adjunto
         FROM notificaciones_historial
         WHERE tipo_notificacion IN ('DEBITO', 'CSN')"
    );
    while ($row = $stmtAvisos->fetch(PDO::FETCH_ASSOC)) {
        $claveAviso = $row['codigo'] . '|' . $row['comp'];
        if (!isset($avisosComprobante[$claveAviso])) {
            $avisosComprobante[$claveAviso] = array();
        }
        $avisosComprobante[$claveAviso][strtoupper(trim((string) $row['tipo_notificacion']))] = trim((string) $row['archivo_adjunto']);
    }

    $config = appConfig();
    $directorioPdf = isset($config['dirs']['comprobantes']) ? $config['dirs']['comprobantes'] : dirname(__FILE__) . '/archivos/comprobantes';
    $mapasPdf = mapaPdfsComprobantesConciliacion($directorioPdf);
    $mapasTango = mapaPdfsTangoConciliacion(dirname(__FILE__) . '/../uploads/pdftango');
    
    $facturasEnAuditoria = array();
    $stmtAuditoria = $pdo->query('SELECT TRIM(COSUCFAC) AS sucursal, TRIM(CONROFAC) AS factura, TRIM(COPRESTADO) AS codigo FROM consulta');
    while ($row = $stmtAuditoria->fetch(PDO::FETCH_ASSOC)) {
        $facturasEnAuditoria[$row['codigo'] . '|' . $row['sucursal'] . '|' . $row['factura']] = true;
    }
    
    $data = array();
    $total = count($filas);
    for ($indice = 1; $indice < $total; $indice++) {
        $fila = $filas[$indice];
        $codigo = textoCeldaExcel(isset($fila[$colPrestador]) ? $fila[$colPrestador] : '');
        if (trim($codigo) === '') continue;
        
        $comprobante = comprobanteConciliacion(
            isset($fila[$colSucursal]) ? $fila[$colSucursal] : '',
            isset($fila[$colNumero]) ? $fila[$colNumero] : ''
        );
        if (trim($comprobante) === '') continue;
        
        $importe = textoCeldaExcel(isset($fila[$colImporte]) ? $fila[$colImporte] : '');
        $saldo = textoCeldaExcel(isset($fila[$colSaldo]) ? $fila[$colSaldo] : '');
        $prestador = isset($diccionarioPrestadores[$codigo]) ? $diccionarioPrestadores[$codigo] : null;
        $nombrePrestador = ($prestador && $prestador['nombre'] !== '') ? $prestador['nombre'] : 'Nombre no encontrado';
        $mailDeb = $prestador ? $prestador['mail_deb'] : '';
        $prestadorFinal = $codigo . ' - ' . $nombrePrestador;
        $fechaRaw = ($colFecha !== null && isset($fila[$colFecha])) ? $fila[$colFecha] : '';
        $fecha = fechaConciliacion($hoja, $colFecha, $indice + 1, $fechaRaw);
        $fechaFormateada = $fecha['texto'];
        $partesComp = explode('-', $comprobante);
        $sucursal = isset($partesComp[0]) ? $partesComp[0] : '';
        $factura = isset($partesComp[1]) ? $partesComp[1] : '';
        $claveFactura = $codigo . '|' . $sucursal . '|' . $factura;
        $liquidacion = isset($facturasEnSistema[$claveFactura]) ? $facturasEnSistema[$claveFactura] : null;
        $existeEnSistema = $liquidacion !== null;
        $existeEnAuditoria = isset($facturasEnAuditoria[$claveFactura]);
        $claveAviso = $codigo . '|' . $sucursal . '-' . $factura;
        $avisos = isset($avisosComprobante[$claveAviso]) ? $avisosComprobante[$claveAviso] : array();
        $ordenPago = '';
        $fechaPago = '';
        $debito = '';
        $csn = '';
        $montoDebito = '';
        $montoCsn = '';
        $archivoDebito = '';
        $archivoCsn = '';
        $pathOp = '';
        $pathDebito = '';
        $pathCsn = '';
        if ($liquidacion) {
            $ordenNumero = trim((string) $liquidacion['orden_pago']);
            if (strpos($ordenNumero, '.') !== false) {
                $ordenNumero = substr($ordenNumero, 0, strpos($ordenNumero, '.'));
            }
            $ordenNumero = trim($ordenNumero);
            $fechaPago = formatearFecha($liquidacion['fecha_pago']);
            if ($ordenNumero !== '' && $ordenNumero !== '0' && ctype_digit($ordenNumero)) {
                $fechaCruda = trim((string) $liquidacion['fecha_pago']);
                $fechaComparar = '';
                if ($fechaCruda !== '' && strpos($fechaCruda, '0000-00-00') !== 0) {
                    $marcaPago = strtotime(substr($fechaCruda, 0, 10));
                    if ($marcaPago !== false) {
                        $fechaComparar = date('Y-m-d', $marcaPago);
                    }
                }
                $ordenVisible = htmlspecialchars($ordenNumero, ENT_QUOTES, 'UTF-8');
                if ($fechaComparar !== '' && $fechaComparar >= '2026-01-07') {
                    $ordenFormateada = str_pad($ordenNumero, 13, '0', STR_PAD_LEFT);
                    $hrefOrden = '../uploads/pdftango/O_P_' . $ordenFormateada . '.pdf';
                    $rutaOp = dirname(__FILE__) . '/../uploads/pdftango/O_P_' . $ordenFormateada . '.pdf';
                    $realOp = realpath($rutaOp);
                    if ($realOp !== false && is_file($realOp)) {
                        $pathOp = str_replace('\\', '/', $realOp);
                    }
                    $ordenPago = '<a href="' . htmlspecialchars($hrefOrden, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold hover:underline" title="Descargar Orden de Pago"><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>' . $ordenVisible . '</a>';
                } else {
                    $ordenPago = '<span class="text-gray-600 font-semibold">' . $ordenVisible . '</span>';
                }
            }
            $montoDebito = formatearImporte($liquidacion['debito']);
            $montoCsn = formatearImporte($liquidacion['csn']);
            $periodoPdf = str_replace('-', '/', trim((string) $liquidacion['periodo']));
            $clavePdf = $periodoPdf . '_' . $sucursal . '_' . $factura;
            if (isset($mapasPdf['debitos'][$clavePdf])) {
                $archivoDebito = $mapasPdf['debitos'][$clavePdf];
            }
            if (isset($mapasPdf['csn'][$clavePdf])) {
                $archivoCsn = $mapasPdf['csn'][$clavePdf];
            }
        }
        if ($archivoDebito === '' && isset($avisos['DEBITO']) && $avisos['DEBITO'] !== '') {
            $archivoDebito = basename(str_replace('\\', '/', $avisos['DEBITO']));
        }
        if ($archivoCsn === '' && isset($avisos['CSN']) && $avisos['CSN'] !== '') {
            $archivoCsn = basename(str_replace('\\', '/', $avisos['CSN']));
        }
        if ($archivoDebito !== '') {
            $rutaDebito = rtrim($directorioPdf, '/\\') . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $archivoDebito));
            $realDebito = realpath($rutaDebito);
            if ($realDebito !== false && is_file($realDebito)) {
                $pathDebito = str_replace('\\', '/', $realDebito);
            }
            $debito = enlacePdfConciliacion('descargar_pago.php?archivo=' . rawurlencode($archivoDebito), $montoDebito !== '' ? $montoDebito : 'Débito');
        } else {
            $debito = $montoDebito;
        }
        if ($archivoCsn !== '') {
            $rutaCsn = rtrim($directorioPdf, '/\\') . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $archivoCsn));
            $realCsn = realpath($rutaCsn);
            if ($realCsn !== false && is_file($realCsn)) {
                $pathCsn = str_replace('\\', '/', $realCsn);
            }
            $csn = enlacePdfConciliacion('descargar_pago.php?archivo=' . rawurlencode($archivoCsn), $montoCsn !== '' ? $montoCsn : 'CSN');
        } else {
            $csn = $montoCsn;
        }
        $mailHtml = htmlspecialchars($mailDeb, ENT_QUOTES, 'UTF-8');
        
        if ($existeEnSistema) {
            $estadoTexto = 'En Sistema';
            $estadoHtml = "<span class='bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-circle-check mr-1'></i> En Sistema</span>";
        } elseif ($existeEnAuditoria) {
            $estadoTexto = 'En Auditoría';
            $estadoHtml = "<span class='bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-clock mr-1'></i> En Auditoría</span>";
        } else {
            $estadoTexto = 'Faltante';
            $estadoHtml = "<span class='bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-circle-xmark mr-1'></i> Faltante</span>";
        }
        $enviar = '<input type="checkbox" class="cb-enviar-mail w-4 h-4 text-blue-600 rounded"'
            . ' data-comprobante="' . htmlspecialchars($comprobante, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-importe="' . htmlspecialchars($importe, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-estado="' . htmlspecialchars($estadoTexto, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-path-op="' . htmlspecialchars($pathOp, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-path-debito="' . htmlspecialchars($pathDebito, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-path-csn="' . htmlspecialchars($pathCsn, ENT_QUOTES, 'UTF-8') . '">';
        
        $data[] = array(
            'seleccion' => '<input type="checkbox" class="fila-seleccionada w-4 h-4 text-blue-600 rounded" data-mail="' . $mailHtml . '" checked>',
            'codigo' => $prestadorFinal,
            'fecha' => $fechaFormateada,
            'fecha_orden' => $fecha['orden'],
            'comprobante' => $comprobante,
            'importe' => $importe,
            'saldo' => $saldo,
            'estado' => $estadoHtml,
            'orden_pago' => $ordenPago,
            'fecha_pago' => $fechaPago,
            'debito' => $debito,
            'csn' => $csn,
            'enviar' => $enviar,
            'mail_deb' => $mailDeb,
        );
    }
    
    // 2. Limpiamos cualquier error impreso y mandamos solo los datos limpios
    ob_clean();
    echo json_encode(array('status' => 'ok', 'data' => $data));
} catch (Exception $e) {
    ob_clean();
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo leer el Excel.', 'data' => array()));
}