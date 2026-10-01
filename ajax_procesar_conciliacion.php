<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

function textoCeldaExcel($valor)
{
    if ($valor === null) {
        return '';
    }
    if (is_bool($valor)) {
        return $valor ? '1' : '0';
    }
    return trim((string) $valor);
}

function tituloColumnaExcel($valor)
{
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

function buscarColumnaExcel($titulos, $agujas, $usados)
{
    foreach ($agujas as $aguja) {
        foreach ($titulos as $indice => $titulo) {
            if (isset($usados[$indice]) || $titulo === '') {
                continue;
            }
            if (strpos($titulo, $aguja) !== false) {
                return $indice;
            }
        }
    }
    return null;
}

function digitosExcel($valor)
{
    return preg_replace('/[^0-9]/', '', textoCeldaExcel($valor));
}

function comprobanteConciliacion($sucursal, $numero)
{
    $sucursal = digitosExcel($sucursal);
    $numero = digitosExcel($numero);
    if ($sucursal === '' && $numero === '') {
        return '';
    }
    return str_pad($sucursal, 4, '0', STR_PAD_LEFT) . '-' . str_pad($numero, 8, '0', STR_PAD_LEFT);
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
if (!$esAdmin) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No autorizado.', 'data' => array()));
    exit;
}

$archivo = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;
if (!$archivo || !isset($archivo['error']) || (int) $archivo['error'] !== UPLOAD_ERR_OK || empty($archivo['tmp_name'])) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se recibió el archivo.', 'data' => array()));
    exit;
}

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if ($extension !== 'xls' && $extension !== 'xlsx') {
    echo json_encode(array('status' => 'error', 'mensaje' => 'El archivo debe ser .xls o .xlsx.', 'data' => array()));
    exit;
}

try {
    $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo['tmp_name']);
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
    if ($colSucursal !== null) {
        $usados[$colSucursal] = true;
    }
    $colFecha = buscarColumnaExcel($titulos, array('fecha fact', 'emision', 'fecha'), $usados);
    if ($colFecha !== null) {
        $usados[$colFecha] = true;
    }
    $colNumero = buscarColumnaExcel($titulos, array('factura', 'numero', 'nro'), $usados);
    if ($colNumero !== null) {
        $usados[$colNumero] = true;
    }
    $colPrestador = buscarColumnaExcel($titulos, array('codprest', 'prestador', 'codigo'), $usados);
    if ($colPrestador !== null) {
        $usados[$colPrestador] = true;
    }
    $colImporte = buscarColumnaExcel($titulos, array('importe'), $usados);
    if ($colImporte !== null) {
        $usados[$colImporte] = true;
    }
    $colSaldo = buscarColumnaExcel($titulos, array('saldo'), $usados);
    $faltantes = array();
    if ($colPrestador === null) {
        $faltantes[] = 'Código / Prestador';
    }
    if ($colSucursal === null) {
        $faltantes[] = 'Cod comprobante / Sucursal';
    }
    if ($colNumero === null) {
        $faltantes[] = 'Número / Factura';
    }
    if ($colImporte === null) {
        $faltantes[] = 'Importe';
    }
    if ($colSaldo === null) {
        $faltantes[] = 'Saldo';
    }
    if (count($faltantes) > 0) {
        echo json_encode(array(
            'status' => 'error',
            'mensaje' => 'No se encontraron las columnas: ' . implode(', ', $faltantes) . '.',
            'data' => array(),
        ));
        exit;
    }
    $pdo = Database::getConnection();
    $diccionarioPrestadores = array();
    $stmtPrestadores = $pdo->query('SELECT CODIGO, NOMBRE FROM ebamp');
    while ($row = $stmtPrestadores->fetch(PDO::FETCH_ASSOC)) {
        $codigoPrestador = trim((string) $row['CODIGO']);
        $nombrePrestadorBase = trim((string) $row['NOMBRE']);
        if ($codigoPrestador === '' || $nombrePrestadorBase === '') {
            continue;
        }
        $diccionarioPrestadores[$codigoPrestador] = $nombrePrestadorBase;
    }
    $facturasEnSistema = array();
    $stmtLiquida = $pdo->query('SELECT TRIM(LISUC) AS sucursal, TRIM(LIFACTURA) AS factura, TRIM(LIPRESTADO) AS codigo FROM liquida');
    while ($row = $stmtLiquida->fetch(PDO::FETCH_ASSOC)) {
        $facturasEnSistema[$row['codigo'] . '|' . $row['sucursal'] . '|' . $row['factura']] = true;
    }
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
        if (trim($codigo) === '') {
            continue;
        }
        $comprobante = comprobanteConciliacion(
            isset($fila[$colSucursal]) ? $fila[$colSucursal] : '',
            isset($fila[$colNumero]) ? $fila[$colNumero] : ''
        );
        if (trim($comprobante) === '') {
            continue;
        }
        $importe = textoCeldaExcel(isset($fila[$colImporte]) ? $fila[$colImporte] : '');
        $saldo = textoCeldaExcel(isset($fila[$colSaldo]) ? $fila[$colSaldo] : '');
        $nombrePrestador = isset($diccionarioPrestadores[$codigo]) ? $diccionarioPrestadores[$codigo] : 'Nombre no encontrado';
        $prestadorFinal = $codigo . ' - ' . $nombrePrestador;
        $fechaFormateada = '';
        $fechaRaw = ($colFecha !== null && isset($fila[$colFecha])) ? $fila[$colFecha] : '';
        if ($fechaRaw !== '' && $fechaRaw !== null) {
            if (is_numeric($fechaRaw)) {
                $fechaObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($fechaRaw);
                $fechaFormateada = $fechaObj->format('d/m/Y');
            } else {
                $fechaFormateada = trim((string) $fechaRaw);
            }
        }
        $partesComp = explode('-', $comprobante);
        $sucursal = isset($partesComp[0]) ? $partesComp[0] : '';
        $factura = isset($partesComp[1]) ? $partesComp[1] : '';
        $claveFactura = $codigo . '|' . $sucursal . '|' . $factura;
        $existeEnSistema = isset($facturasEnSistema[$claveFactura]);
        $existeEnAuditoria = isset($facturasEnAuditoria[$claveFactura]);
        if ($existeEnSistema) {
            $estadoHtml = "<span class='bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-circle-check mr-1'></i> En Sistema</span>";
        } elseif ($existeEnAuditoria) {
            $estadoHtml = "<span class='bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-clock mr-1'></i> En Auditoría</span>";
        } else {
            $estadoHtml = "<span class='bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded shadow-sm'><i class='fa-solid fa-circle-xmark mr-1'></i> Faltante</span>";
        }
        $data[] = array(
            'seleccion' => '<input type="checkbox" class="fila-seleccionada w-4 h-4 text-blue-600 rounded" checked>',
            'codigo' => $prestadorFinal,
            'fecha' => $fechaFormateada,
            'comprobante' => $comprobante,
            'importe' => $importe,
            'saldo' => $saldo,
            'estado' => $estadoHtml,
        );
    }
    echo json_encode(array('status' => 'ok', 'data' => $data));
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo leer el Excel.', 'data' => array()));
}
