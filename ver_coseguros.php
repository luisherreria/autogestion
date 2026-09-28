<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/includes/bootstrap.php';
    header('Location: index.php?seccion=coseguros');
    exit;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigoPrestador = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';

$generales = array();
$porObra = array();
$porPrestador = array();
$visibles = array();
$error = '';

function iconoCoseguro($archivo)
{
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    if ($extension === 'pdf') {
        return 'fa-file-pdf text-red-600';
    }
    if (in_array($extension, array('xls', 'xlsx', 'csv'), true)) {
        return 'fa-file-excel text-green-600';
    }
    return 'fa-file text-slate-500';
}

function agruparCosegurosPorCodigo($filas)
{
    $grupos = array();
    foreach ($filas as $fila) {
        $codigo = trim((string) $fila['codigo']);
        if ($codigo === '') {
            $codigo = 'Sin código';
        }
        if (!isset($grupos[$codigo])) {
            $grupos[$codigo] = array();
        }
        $grupos[$codigo][] = $fila;
    }
    return $grupos;
}

function pintarTarjetasCoseguro($filas)
{
    if (count($filas) === 0) {
        echo '<p class="text-sm text-slate-500">No hay archivos en esta sección.</p>';
        return;
    }
    echo '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">';
    foreach ($filas as $fila) {
        $nombre = $fila['nombre_archivo'];
        $codigo = trim((string) $fila['codigo']);
        echo '<article class="bg-white rounded-xl shadow border border-slate-200 p-6 flex flex-col items-center text-center">';
        echo '<i class="fa-solid ' . h(iconoCoseguro($nombre)) . ' text-6xl mb-4"></i>';
        echo '<h3 class="font-medium text-slate-800 break-all mb-1">' . h($nombre) . '</h3>';
        if ($codigo !== '') {
            echo '<p class="text-xs text-slate-500 mb-4">' . h($codigo) . '</p>';
        } else {
            echo '<div class="mb-4"></div>';
        }
        echo '<a href="' . h(urlDescarga('coseguros', rutaRelativaCoseguro($fila['ruta']), false)) . '"';
        echo ' class="mt-auto inline-flex items-center gap-2 bg-blue-800 hover:bg-blue-900 text-white text-sm rounded-lg px-4 py-2">';
        echo '<i class="fa-solid fa-download"></i> Descargar</a>';
        echo '</article>';
    }
    echo '</div>';
}

try {
    $pdo = Database::getConnection();
    asegurarTablaArchivos($pdo);
    asegurarTablaArchivosCoseguros($pdo);

    if ($esAdmin) {
        $generales = $pdo->query(
            "SELECT nombre_archivo, ruta, alcance, codigo, fecha
             FROM archivos_coseguros
             WHERE alcance = 'general'
             ORDER BY fecha DESC, nombre_archivo"
        )->fetchAll();
        $porObra = $pdo->query(
            "SELECT nombre_archivo, ruta, alcance, codigo, fecha
             FROM archivos_coseguros
             WHERE alcance = 'obrasocial' OR alcance = 'obra_social'
             ORDER BY codigo, nombre_archivo"
        )->fetchAll();
        $porPrestador = $pdo->query(
            "SELECT nombre_archivo, ruta, alcance, codigo, fecha
             FROM archivos_coseguros
             WHERE alcance = 'prestador'
             ORDER BY codigo, nombre_archivo"
        )->fetchAll();
    } else {
        $obras = codigosObraSocialSesion($pdo, $codigoPrestador);
        $sql = "SELECT nombre_archivo, ruta, alcance, codigo, fecha
                FROM archivos_coseguros
                WHERE alcance = 'general'
                   OR (alcance = 'prestador' AND TRIM(codigo) = :codigo_prestador)";
        $params = array(':codigo_prestador' => $codigoPrestador);
        if (count($obras) > 0) {
            $marcas = array();
            foreach ($obras as $indice => $obra) {
                $clave = ':obra' . $indice;
                $marcas[] = $clave;
                $params[$clave] = $obra;
            }
            $sql .= " OR ((alcance = 'obrasocial' OR alcance = 'obra_social') AND TRIM(codigo) IN (" . implode(', ', $marcas) . '))';
        }
        $sql .= ' ORDER BY alcance, codigo, nombre_archivo';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $visibles = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } elseif ($esAdmin) { ?>
    <section class="mb-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Archivos Generales / Globales</h2>
        <?php pintarTarjetasCoseguro($generales); ?>
    </section>
    <section class="mb-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Por Obra Social Específica</h2>
        <?php if (count($porObra) === 0) { ?>
            <p class="text-sm text-slate-500">No hay archivos en esta sección.</p>
        <?php } else { ?>
            <?php foreach (agruparCosegurosPorCodigo($porObra) as $codigoObra => $filasObra) { ?>
                <h3 class="text-sm font-medium text-slate-600 mt-4 mb-3"><?php echo h($codigoObra); ?></h3>
                <?php pintarTarjetasCoseguro($filasObra); ?>
            <?php } ?>
        <?php } ?>
    </section>
    <section class="mb-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Por Prestador Específico</h2>
        <?php if (count($porPrestador) === 0) { ?>
            <p class="text-sm text-slate-500">No hay archivos en esta sección.</p>
        <?php } else { ?>
            <?php foreach (agruparCosegurosPorCodigo($porPrestador) as $codigoGrupo => $filasGrupo) { ?>
                <h3 class="text-sm font-medium text-slate-600 mt-4 mb-3"><?php echo h($codigoGrupo); ?></h3>
                <?php pintarTarjetasCoseguro($filasGrupo); ?>
            <?php } ?>
        <?php } ?>
    </section>
<?php } elseif (count($visibles) === 0) { ?>
    <div class="bg-white rounded-xl shadow p-8 text-center text-slate-500">No hay archivos de coseguros.</div>
<?php } else { ?>
    <?php pintarTarjetasCoseguro($visibles); ?>
<?php } ?>
