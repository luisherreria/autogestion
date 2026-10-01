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
    $stmtPrestador = $pdo->prepare('SELECT NOMBRE FROM ebamp WHERE TRIM(CODIGO) = ? LIMIT 1');
    $data = array();
    $total = count($filas);
    for ($indice = 1; $indice < $total; $indice++) {
        $fila = $filas[$indice];
        $codigo = textoCeldaExcel(isset($fila[$colPrestador]) ? $fila[$colPrestador] : '');
        $comprobante = comprobanteConciliacion(
            isset($fila[$colSucursal]) ? $fila[$colSucursal] : '',
            isset($fila[$colNumero]) ? $fila[$colNumero] : ''
        );
        $importe = textoCeldaExcel(isset($fila[$colImporte]) ? $fila[$colImporte] : '');
        $saldo = textoCeldaExcel(isset($fila[$colSaldo]) ? $fila[$colSaldo] : '');
        if (trim($codigo) === '' || trim($comprobante) === '') {
            continue;
        }
        $stmtPrestador->execute(array($codigo));
        $rowPrestador = $stmtPrestador->fetch();
        $nombrePrestador = ($rowPrestador && trim((string) $rowPrestador['NOMBRE']) !== '') ? trim((string) $rowPrestador['NOMBRE']) : 'Nombre no encontrado';
        $data[] = array(
            'seleccion' => '<input type="checkbox" class="fila-seleccionada w-4 h-4 text-blue-600 rounded" checked>',
            'codigo' => $codigo . ' - ' . $nombrePrestador,
            'comprobante' => $comprobante,
            'importe' => $importe,
            'saldo' => $saldo,
            'estado' => "<span class='text-gray-500'>Pendiente</span>",
        );
    }
    echo json_encode(array('status' => 'ok', 'data' => $data));
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo leer el Excel.', 'data' => array()));
}
