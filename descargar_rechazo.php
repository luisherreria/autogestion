<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$orden = isset($_GET['orden']) ? trim((string) $_GET['orden']) : '';
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
$origen = isset($_GET['origen']) ? trim((string) $_GET['origen']) : '';

if ($orden === '' || $estado !== 'RECHAZADA' || ($origen !== 'AMBULATORIO' && $origen !== 'SANATORIAL')) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';

try {
    $pdo = Database::getConnection();
    if ($origen === 'SANATORIAL') {
        $sql = 'SELECT conumero, cofecha, conompac, coestado, ntxtrech, textorech, coobs, comedico, coprestado
                FROM sanorden
                WHERE TRIM(conumero) = :orden
                  AND TRIM(coestado) = \'RECHAZADA\'
                  AND cofecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                LIMIT 1';
    } else {
        $sql = 'SELECT CONUMERO AS conumero, COFECHA AS cofecha, CONOMPAC AS conompac, COESTADO AS coestado,
                       NTXTRECH AS ntxtrech, TEXTORECH AS textorech, COOBS AS coobs, COMEDICO AS comedico, COPRESTADO AS coprestado
                FROM ordenes
                WHERE TRIM(CONUMERO) = :orden
                  AND TRIM(COESTADO) = \'RECHAZADA\'
                  AND COFECHA >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                LIMIT 1';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':orden' => $orden));
    $fila = $stmt->fetch();
} catch (Exception $e) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

if (!$fila) {
    header('HTTP/1.1 404 Not Found');
    echo 'Archivo no disponible';
    exit;
}

if (!$esAdmin) {
    $medico = trim((string) $fila['comedico']);
    $prestador = trim((string) $fila['coprestado']);
    $coincide = ($medico !== '' && strcasecmp($medico, $codigo) === 0)
        || ($medico === '' && strcasecmp($prestador, $codigo) === 0);
    if (!$coincide) {
        header('HTTP/1.1 404 Not Found');
        echo 'Archivo no disponible';
        exit;
    }
}

$motivo = trim((string) $fila['ntxtrech']);
if ($motivo === '') {
    $motivo = trim((string) $fila['coobs']);
}
if ($motivo === '') {
    $motivo = trim((string) $fila['textorech']);
}

$fechaTexto = formatearFecha($fila['cofecha']);

$pdf = pdfRechazo(array(
    'Orden rechazada',
    'Orden: ' . trim((string) $fila['conumero']),
    'Fecha: ' . $fechaTexto,
    'Paciente: ' . trim((string) $fila['conompac']),
    'Origen: ' . $origen,
    'Estado: RECHAZADA',
    'Motivo: ' . $motivo,
));

$nombre = 'recha' . $orden . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;

function pdfRechazo($lineas)
{
    $contenido = "BT /F1 16 Tf 50 780 Td (" . pdfTexto($lineas[0]) . ") Tj\n/F1 12 Tf\n";
    $total = count($lineas);
    for ($i = 1; $i < $total; $i++) {
        $contenido .= '0 -24 Td (' . pdfTexto($lineas[$i]) . ") Tj\n";
    }
    $contenido .= 'ET';

    $objetos = array(
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    );

    $pdf = "%PDF-1.4\n";
    $offsets = array(0);
    foreach ($objetos as $indice => $objeto) {
        $offsets[] = strlen($pdf);
        $pdf .= ($indice + 1) . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $cantidad = count($objetos) + 1;
    $pdf .= 'xref\n0 ' . $cantidad . "\n0000000000 65535 f \n";
    for ($i = 1; $i < $cantidad; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= 'trailer << /Size ' . $cantidad . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

function pdfTexto($texto)
{
    $texto = utf8_decode((string) $texto);
    $texto = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $texto);
    return $texto;
}
