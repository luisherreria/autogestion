<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<p class="text-red-500 text-sm">Acceso denegado.</p>';
    exit;
}

$busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';
if (strlen($busqueda) < 3) {
    exit;
}

$termino = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $busqueda) . '%';

function correosDeCampo($lista)
{
    $correos = array();
    $trozos = preg_split('/[;,\s]+/', (string) $lista);
    if (!is_array($trozos)) {
        return $correos;
    }
    foreach ($trozos as $correo) {
        $correo = trim($correo);
        if ($correo === '' || strpos($correo, '@') === false) {
            continue;
        }
        $clave = strtolower($correo);
        if (!isset($correos[$clave])) {
            $correos[$clave] = $correo;
        }
    }
    return array_values($correos);
}

function enlacesCorreoInline($lista, $codigo)
{
    $links = array();
    foreach (correosDeCampo($lista) as $correo) {
        $url = 'procesar_acceso_rapido.php?codigo=' . rawurlencode($codigo) . '&email=' . rawurlencode($correo);
        $links[] = '<a href="' . h($url) . '" data-correo="' . h($correo) . '" class="text-blue-600 hover:text-blue-800 hover:underline inline-flex items-center gap-1 font-medium" onclick="return confirmarAccesoPrestador(this);">'
            . h($correo)
            . '</a>';
    }
    if (count($links) === 0) {
        return '';
    }
    return implode(' <span class="text-gray-400 mx-1">/</span> ', $links);
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare(
        'SELECT CODIGO, NOMBRE, NOMFANTAS, MAIL, MAIL_AUTO, MAIL_PAGO, MAIL_DEB, MAILCONTRA
         FROM ebamp
         WHERE (NOMBRE LIKE :nombre OR NOMFANTAS LIKE :fantas OR CODIGO LIKE :codigo)
           AND (FECHABAJA IS NULL
            OR FECHABAJA = \'0000-00-00\'
            OR FECHABAJA = \'\'
            OR DATE(FECHABAJA) >= CURDATE())
           AND (CATEG IS NULL OR CATEG != \'ADEF\')
         ORDER BY NOMBRE ASC
         LIMIT 20'
    );
    $stmt->execute(array(
        ':nombre' => $termino,
        ':fantas' => $termino,
        ':codigo' => $termino,
    ));
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<p class="text-red-500 text-sm">No se pudo buscar.</p>';
    exit;
}

if (count($resultados) === 0) {
    echo '<p class="text-red-500 text-sm">No se encontraron prestadores vigentes.</p>';
    exit;
}

foreach ($resultados as $row) {
    $codigo = trim((string) $row['CODIGO']);
    $fantasia = trim((string) $row['NOMFANTAS']);
    $nombre = trim((string) $row['NOMBRE']);
    if ($fantasia !== '' && strcasecmp($fantasia, $nombre) !== 0) {
        $nombre .= ' ' . $fantasia;
    }
    $tiposCorreos = array(
        'Correos Principales:' => $row['MAIL'],
        'Correos Autorizaciones:' => $row['MAIL_AUTO'],
        'Correos Pagos:' => $row['MAIL_PAGO'],
        'Correos Débitos:' => $row['MAIL_DEB'],
        'Correos Contratos:' => $row['MAILCONTRA'],
    );
    echo '<div class="p-4 border-b border-gray-200 hover:bg-gray-50 transition-colors">';
    echo '<h3 class="font-bold text-blue-800 text-base mb-3">' . h($codigo . ' - ' . $nombre) . '</h3>';
    echo '<div class="space-y-2 text-sm">';
    foreach ($tiposCorreos as $etiqueta => $valor) {
        $enlacesHtml = enlacesCorreoInline($valor, $codigo);
        if ($enlacesHtml === '') {
            continue;
        }
        echo '<div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-2">';
        echo '<span class="font-bold text-gray-700 min-w-[170px]">' . h($etiqueta) . '</span>';
        echo '<div class="flex-1 flex flex-wrap items-center">' . $enlacesHtml . '</div>';
        echo '</div>';
    }
    echo '</div></div>';
}
