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
    if (in_array($extension, array('doc', 'docx'), true)) {
        return 'fa-file-word text-blue-700';
    }
    if (in_array($extension, array('png', 'jpg', 'jpeg'), true)) {
        return 'fa-file-image text-sky-600';
    }
    return 'fa-file text-slate-500';
}

function esArchivoApb($nombre)
{
    return stripos((string) $nombre, 'APB') !== false;
}

function filtrarCosegurosPrestador($filas)
{
    $apb = array();
    $porPrestador = array();
    $porObra = array();
    $generales = array();

    $vistos = array();
    foreach ($filas as $fila) {
        $ruta = isset($fila['ruta']) ? $fila['ruta'] : $fila['nombre_archivo'];
        if (isset($vistos[$ruta])) {
            continue;
        }
        $vistos[$ruta] = true;
        if (esArchivoApb($fila['nombre_archivo'])) {
            $apb[] = $fila;
            continue;
        }
        $alcance = isset($fila['alcance']) ? $fila['alcance'] : '';
        if ($alcance === 'prestador') {
            $porPrestador[] = $fila;
        } elseif ($alcance === 'obrasocial' || $alcance === 'obra_social') {
            $porObra[] = $fila;
        } else {
            $generales[] = $fila;
        }
    }

    if (count($porPrestador) > 0) {
        $coseguros = $porPrestador;
        $nivel = 'prestador';
    } elseif (count($porObra) > 0) {
        $coseguros = $porObra;
        $nivel = 'obrasocial';
    } else {
        $coseguros = $generales;
        $nivel = 'general';
    }

    return array(
        'apb' => $apb,
        'coseguros' => $coseguros,
        'nivel' => $nivel,
    );
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
        $todos = $pdo->query(
            "SELECT nombre_archivo, ruta, alcance, codigo, fecha
             FROM archivos_coseguros
             ORDER BY fecha DESC, nombre_archivo"
        )->fetchAll();
        $vistos = array();
        foreach ($todos as $fila) {
            $ruta = isset($fila['ruta']) ? $fila['ruta'] : $fila['nombre_archivo'];
            if (isset($vistos[$ruta])) {
                continue;
            }
            $vistos[$ruta] = true;
            if (esArchivoApb($fila['nombre_archivo']) || $fila['alcance'] === 'general') {
                $generales[] = $fila;
            } elseif ($fila['alcance'] === 'obrasocial' || $fila['alcance'] === 'obra_social') {
                $porObra[] = $fila;
            } elseif ($fila['alcance'] === 'prestador') {
                $porPrestador[] = $fila;
            }
        }
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
        $visibles = filtrarCosegurosPrestador($stmt->fetchAll());
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
<?php } else {
    $apb = isset($visibles['apb']) ? $visibles['apb'] : array();
    $coseguros = isset($visibles['coseguros']) ? $visibles['coseguros'] : array();
    $nivel = isset($visibles['nivel']) ? $visibles['nivel'] : 'general';
    $globales = $apb;
    $especificos = array();
    if ($nivel === 'general') {
        foreach ($coseguros as $fila) {
            $globales[] = $fila;
        }
    } else {
        $especificos = $coseguros;
    }
    if (count($globales) === 0 && count($especificos) === 0) { ?>
    <div class="bg-white rounded-xl shadow p-8 text-center text-slate-500">No hay archivos de coseguros.</div>
    <?php } else { ?>
        <?php if (count($globales) > 0) { ?>
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Archivos Generales / Globales</h2>
                <?php pintarTarjetasCoseguro($globales); ?>
            </section>
        <?php } ?>
        <?php if (count($especificos) > 0) { ?>
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">
                    <?php echo $nivel === 'prestador' ? 'Coseguro del prestador' : 'Coseguro de la obra social'; ?>
                </h2>
                <?php pintarTarjetasCoseguro($especificos); ?>
            </section>
        <?php } ?>
    <?php }
} ?>
