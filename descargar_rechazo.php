<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';
require_once dirname(__FILE__) . '/classes/PDF.php';

$orden = isset($_GET['orden']) ? trim((string) $_GET['orden']) : '';
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
$origen = isset($_GET['origen']) ? trim((string) $_GET['origen']) : '';

if ($orden === '' || $estado !== 'RECHAZADA' || ($origen !== 'AMBULATORIO' && $origen !== 'SANATORIAL')) {
    header('HTTP/1.1 404 Not Found');
    echo 'Faltan parámetros o la orden no es válida.';
    exit;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';

if ($origen === 'SANATORIAL') {
    $tabla = 'cartilla.sanorden';
    $autoriza = 'cartilla.sanauto';
    $detalle = 'cartilla.sanautod';
} else {
    $tabla = 'cartilla.ordenes';
    $autoriza = 'cartilla.Autoriza';
    $detalle = 'cartilla.detauto';
}

$sql = 'SELECT
            o.cofeccons AS fechaRech,
            o.conumero AS orden,
            o.conroauto AS autorizacion,
            o.coobrasoc AS obra,
            o.conomobra AS obraNombre,
            s.tadescrip AS nombreObra,
            o.coafiliado AS numeroAfiliado,
            o.conompac AS nombreAfiliado,
            o.codirafi AS direccionAfiliado,
            o.colocafi AS localidadAfiliado,
            o.coplan AS plan,
            o.cotelafi AS telefonoAfiliado,
            a.conomprest AS nombrePrestador,
            a.codirpre AS direccionPrestador,
            a.colocpre AS localidadPrestador,
            a.cotelpre AS telefonoPrestador,
            a.conomderiv AS nombreDerivante,
            o.cocatderi AS categoria,
            o.coespederi AS especialidad,
            o.conompra1 AS grupo1,
            o.conompra2 AS grupo2,
            o.conompra3 AS grupo3,
            o.conompra4 AS grupo4,
            o.conompra5 AS grupo5,
            o.coaclara1 AS detalle1,
            o.coaclara2 AS detalle2,
            o.coaclara3 AS detalle3,
            o.coaclara4 AS detalle4,
            o.coaclara5 AS detalle5,
            o.ntxtrech AS titulo,
            o.cotexto AS texto,
            o.comedico AS comedico,
            o.coprestado AS coprestado
        FROM ' . $tabla . ' o
        LEFT JOIN ' . $detalle . ' d ON o.conroauto = RIGHT(d.conumero, 8)
        LEFT JOIN ' . $autoriza . ' a ON o.conroauto = RIGHT(a.conumero, 8)
        LEFT JOIN obrasoc s ON o.coobrasoc = s.tacodigo
        WHERE TRIM(o.conumero) = :orden
          AND TRIM(o.coestado) = \'RECHAZADA\'
          AND o.cofecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';

$parametros = array(':orden' => $orden);
if (!$esAdmin) {
    $sql .= ' AND (TRIM(o.comedico) = :codigo OR (TRIM(IFNULL(o.comedico, \'\')) = \'\' AND TRIM(o.coprestado) = :codigo_prestador))';
    $parametros[':codigo'] = $codigo;
    $parametros[':codigo_prestador'] = $codigo;
}
$sql .= ' LIMIT 1';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
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

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$codigo_obra = strtoupper(trim((string) $fila['obra']));
if (!preg_match('/^[A-Z0-9 ._-]+$/', $codigo_obra)) {
    $codigo_obra = '';
}
$ruta_logo_upper = __DIR__ . '/public/img/' . $codigo_obra . '.PNG';
$ruta_logo_lower = __DIR__ . '/public/img/' . $codigo_obra . '.png';
$ruta_logo_jpg = __DIR__ . '/public/img/' . $codigo_obra . '.JPG';

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(10, 8);
$pdf->Cell(72, 5, utf8_decode('Sarmiento 767 2º B'), 0, 1, 'L');
$pdf->SetX(10);
$pdf->Cell(72, 5, 'Capital Federal', 0, 1, 'L');
$pdf->SetX(10);
$pdf->Cell(72, 5, 'Telefono: 0800-345-0428 (Linea Gratuita)', 0, 1, 'L');

$pdf->SetXY(128, 8);
$pdf->Cell(72, 5, 'Gestionado por Comedica SA', 0, 1, 'R');
$pdf->SetX(128);
$pdf->Cell(72, 5, 'autorizaciones@comedica.com.ar', 0, 1, 'R');
$pdf->SetX(128);
$pdf->Cell(72, 5, 'www.comedica.com.ar', 0, 1, 'R');

$altoLogo = 0;
if (file_exists($ruta_logo_upper)) {
    $pdf->Image($ruta_logo_upper, 85, 10, 40);
    $altoLogo = altoLogoPdf($ruta_logo_upper);
} elseif (file_exists($ruta_logo_lower)) {
    $pdf->Image($ruta_logo_lower, 85, 10, 40);
    $altoLogo = altoLogoPdf($ruta_logo_lower);
} elseif (file_exists($ruta_logo_jpg)) {
    $pdf->Image($ruta_logo_jpg, 85, 10, 40);
    $altoLogo = altoLogoPdf($ruta_logo_jpg);
} else {
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->SetXY(70, 15);
    $pdf->MultiCell(70, 4, 'Logo no encontrado en: ' . $ruta_logo_upper, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$finEncabezado = 23;
if ((10 + $altoLogo) > $finEncabezado) {
    $finEncabezado = 10 + $altoLogo;
}
if ($pdf->GetY() > $finEncabezado) {
    $finEncabezado = $pdf->GetY();
}
$pdf->SetY($finEncabezado);
$pdf->Ln(4);

$fechaTexto = '';
$fechaCruda = trim((string) $fila['fechaRech']);
if ($fechaCruda !== '' && strpos($fechaCruda, '0000-00-00') !== 0) {
    $marca = strtotime($fechaCruda);
    if ($marca !== false) {
        $fechaTexto = date('d/m/Y', $marca);
    }
}

$nombreObra = trim((string) $fila['nombreObra']);
if ($nombreObra === '') {
    $nombreObra = trim((string) $fila['obraNombre']);
}

$pdf->SetTextColor(255, 0, 0);
$pdf->SetFont('Arial', 'B', 22);
$pdf->Cell(67, 8, '', 0, 0, 'L');
$pdf->Cell(66, 8, 'RECHAZADA', 0, 0, 'C');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 8, 'Fecha:', 0, 0, 'R');
$pdf->Cell(0, 8, $fechaTexto, 0, 1, 'L');
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(52, 7, utf8_decode('Número de Orden:'), 0, 0, 'L');
$pdf->Cell(0, 7, pdfTxt($fila['orden']), 0, 1, 'L');

$pdf->Cell(35, 7, 'Obra Social:', 0, 0, 'L');
$pdf->Cell(165, 7, pdfTxt($nombreObra), 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 12);
$pdf->SetMargins(6, 10);

$startX = 5;
$startY = $pdf->GetY();
$pdf->SetX(6);
$pdf->SetFillColor(235, 235, 235);
$pdf->Cell(18, 6, 'Nombre:', 0, 0, 'L', true);
$pdf->Cell(100, 6, pdfTxt($fila['nombreAfiliado']), 0, 0, 'L', true);
$pdf->Cell(29, 6, utf8_decode('Nº de Afiliado:'), 0, 0, 'L', true);
$pdf->Cell(0, 6, pdfTxt($fila['numeroAfiliado']), 0, 1, 'L', true);

$pdf->Cell(20, 6, 'Domicilio:', 0, 0, 'L');
$pdf->Cell(98, 6, pdfTxt($fila['direccionAfiliado']), 0, 0, 'L');
$pdf->Cell(11, 6, 'Plan:', 0, 0, 'L');
$pdf->Cell(0, 6, pdfTxt($fila['plan']), 0, 1, 'L');

$pdf->Cell(21, 6, 'Localidad:', 0, 0, 'L', true);
$pdf->Cell(97, 6, pdfTxt($fila['localidadAfiliado']), 0, 0, 'L', true);
$pdf->Cell(19, 6, utf8_decode('Teléfono:'), 0, 0, 'L', true);
$pdf->Cell(0, 6, pdfTxt($fila['telefonoAfiliado']), 0, 1, 'L', true);

$endY = $pdf->GetY();
$pdf->Rect($startX, $startY, 200, $endY - $startY);
$pdf->Ln(2);

$startY = $pdf->GetY();
$pdf->SetX(6);
$pdf->Cell(21, 6, 'Prestador:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, pdfTxt($fila['nombrePrestador']), 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(20, 6, 'Domicilio:', 0, 0, 'L', true);
$pdf->Cell(98, 6, pdfTxt($fila['direccionPrestador']), 0, 0, 'L', true);
$pdf->Cell(19, 6, utf8_decode('Teléfono:'), 0, 0, 'L', true);
$pdf->Cell(0, 6, pdfTxt($fila['telefonoPrestador']), 0, 1, 'L', true);

$pdf->Cell(21, 6, 'Localidad:', 0, 0, 'L');
$pdf->Cell(0, 6, pdfTxt($fila['localidadPrestador']), 0, 1, 'L');

$pdf->Cell(21, 6, 'Derivante:', 0, 0, 'L', true);
$pdf->Cell(97, 6, pdfTxt($fila['nombreDerivante']), 0, 0, 'L', true);
$pdf->Cell(21, 6, 'Categoria:', 0, 0, 'L', true);
$pdf->Cell(0, 6, pdfTxt($fila['categoria']), 0, 1, 'L', true);

$pdf->Cell(28, 6, 'Especialidad:', 0, 0, 'L');
$pdf->Cell(0, 6, pdfTxt($fila['especialidad']), 0, 1, 'L');

$endY = $pdf->GetY();
$pdf->Rect($startX, $startY, 200, $endY - $startY);
$pdf->Ln(2);

$pdf->SetMargins(5, 10);
$pdf->SetX(5);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 7, 'ESTUDIOS SOLICITADOS', 0, 1, 'L');
$pdf->Ln(1);

$pdf->SetFont('Arial', '', 12);
$pdf->SetLineWidth(0.7);
$pdf->Cell(100, 8, 'Grupo', 'L T B', 0, 'L');
$pdf->Cell(100, 8, 'Detalle', 'T B R', 1, 'L');

$grupos = array(
    array('grupo1', 'detalle1', 'L', 'R'),
    array('grupo2', 'detalle2', 'L', 'R'),
    array('grupo3', 'detalle3', 'L', 'R'),
    array('grupo4', 'detalle4', 'L', 'R'),
    array('grupo5', 'detalle5', 'L B', 'B R'),
);
foreach ($grupos as $par) {
    $pdf->Cell(100, 8, pdfTxt($fila[$par[0]]), $par[2], 0, 'L');
    $pdf->Cell(100, 8, pdfTxt($fila[$par[1]]), $par[3], 1, 'L');
}
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, pdfTxt($fila['titulo']), 0, 1, 'C');
$pdf->Ln(1);
$pdf->SetFont('Arial', '', 12);

$pdf->SetFillColor(255, 255, 255);
$pdf->MultiCell(0, 6, pdfTxt($fila['texto']), 0, 'L');
$pdf->Ln(8);

$hoy = date('d/m/Y');
$pdf->Cell(27, 8, 'Buenos Aires,', 0, 0, 'L');
$pdf->Cell(0, 8, $hoy, 0, 1, 'L');

// TODO: Ajustar ruta de imagen/logo para evitar que FPDF arroje un error fatal si no encuentra el archivo físico.
// $pdf->Image('tpl/default/images/FirmaAut.png', 125, 210, 60);

$pdf->Output('I', 'recha' . $orden . '.pdf');

function pdfTxt($valor)
{
    return utf8_decode(trim((string) $valor));
}

function altoLogoPdf($ruta)
{
    $medidas = @getimagesize($ruta);
    if (!$medidas || $medidas[0] <= 0) {
        return 0;
    }
    return 40 * ($medidas[1] / $medidas[0]);
}
