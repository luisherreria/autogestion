<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=autorizaciones');
    exit;
}

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$filas = array();
$error = '';
$desde = date('Y-m-d', strtotime('-90 days'));
$desdeTexto = date('d/m/Y', strtotime($desde));
$hastaTexto = date('d/m/Y');

$sqlAmbulatorio = "SELECT auto_id, CONUMERO AS conumero, COFECHA AS cofecha, CONOMPAC AS conompac, COESTADO AS coestado, CONROAUTO AS conroauto, COPRESTADO AS codigo_prestador, 'AMBULATORIO' AS origen
        FROM cartilla.ordenes
        WHERE COFECHA >= :desde_ambu
          AND COFECHA < :hasta_ambu
          AND COESTADO IN ('AUTORIZADA', 'RECHAZADA')";
$sqlSanatorial = "SELECT auto_id, conumero, cofecha, conompac, coestado, conroauto, COPRESTADO AS codigo_prestador, 'SANATORIAL' AS origen
        FROM cartilla.sanorden
        WHERE cofecha >= :desde_sano
          AND cofecha < :hasta_sano
          AND coestado IN ('AUTORIZADA', 'RECHAZADA')";
$manana = date('Y-m-d', strtotime('+1 day'));
$parametros = array(
    ':desde_ambu' => $desde,
    ':hasta_ambu' => $manana,
    ':desde_sano' => $desde,
    ':hasta_sano' => $manana,
);
if (!$esAdmin) {
    if ($codigo === '') {
        error_log('ERROR DE SESION: El codigo de prestador esta vacio en modulos/autorizaciones.php');
    }
    $sqlAmbulatorio .= ' AND TRIM(COPRESTADO) = :codigo';
    $sqlSanatorial .= ' AND TRIM(COPRESTADO) = :codigo_sano';
    $parametros[':codigo'] = $codigo;
    $parametros[':codigo_sano'] = $codigo;
}
$sql = $sqlAmbulatorio . ' UNION ALL ' . $sqlSanatorial . ' ORDER BY cofecha DESC, conumero DESC';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    foreach ($stmt->fetchAll() as $fila) {
        $fechaFila = substr(trim((string) $fila['cofecha']), 0, 10);
        if ($fechaFila >= $desde && $fechaFila < $manana) {
            $filas[] = $fila;
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

function claseEstadoAutorizacion($estado)
{
    if ($estado === 'AUTORIZADA') {
        return 'bg-green-100 text-green-800';
    }
    if ($estado === 'RECHAZADA') {
        return 'bg-red-100 text-red-800';
    }
    return 'bg-slate-100 text-slate-700';
}

function rutaPdfAutorizacion($fila)
{
    $estado = isset($fila['coestado']) ? trim($fila['coestado']) : '';
    $numero = isset($fila['conumero']) ? trim($fila['conumero']) : '';
    $auto = isset($fila['conroauto']) ? trim($fila['conroauto']) : '';

    if ($estado === 'AUTORIZADA' && $auto !== '' && $auto !== '0') {
        return '../uploads/pdf/' . $auto . '.pdf';
    }
    if ($estado === 'AUTORIZADA' && ($auto === '' || $auto === '0')) {
        return '../uploads/pdf/orden' . $numero . '.pdf';
    }
    if ($estado === 'RECHAZADA') {
        $origen = isset($fila['origen']) ? trim((string) $fila['origen']) : '';
        return 'descargar_rechazo.php?orden=' . rawurlencode($numero)
            . '&estado=RECHAZADA&origen=' . rawurlencode($origen);
    }
    return '';
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-slate-800">Últimos 90 días</p>
            <p class="text-xs text-slate-500 mt-0.5">Del <?php echo h($desdeTexto); ?> al <?php echo h($hastaTexto); ?> · <?php echo count($filas); ?> órdenes</p>
        </div>
    </div>
    <div class="p-4">
    <div id="grupo-estado-autorizaciones">
        <label for="filtroEstadoAutorizaciones">Estado</label>
        <select id="filtroEstadoAutorizaciones">
            <option value="">Todas</option>
            <option value="AUTORIZADA">Autorizadas</option>
            <option value="RECHAZADA">Rechazadas</option>
        </select>
    </div>
    <table id="tablaAutorizaciones" class="display w-full text-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Paciente</th>
                <th>Estado</th>
                <th>Descargar Autorización</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $estado = trim((string) $fila['coestado']);
                $numero = trim($fila['conumero']);
                $rutaPdf = rutaPdfAutorizacion($fila);
                ?>
                <tr>
                    <td><?php echo h($numero); ?></td>
                    <td data-order="<?php echo h(substr(trim((string) $fila['cofecha']), 0, 10)); ?>"><?php echo h(formatearFecha($fila['cofecha'])); ?></td>
                    <td><?php echo h(trim((string) $fila['conompac'])); ?></td>
                    <td>
                        <?php if ($estado !== '') { ?>
                            <span class="inline-block rounded-full px-2 py-1 text-xs font-medium <?php echo claseEstadoAutorizacion($estado); ?>">
                                <?php echo h($estado); ?>
                            </span>
                        <?php } ?>
                    </td>
                    <td class="text-center">
                        <?php if ($rutaPdf !== '') { ?>
                            <a href="<?php echo h($rutaPdf); ?>" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1.5" title="Descargar autorización">
                                <i class="fa-solid fa-file-pdf"></i>
                                PDF
                            </a>
                        <?php } else { ?>
                            <span class="text-slate-400">-</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</div>
<?php if ($esAdmin) { ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mt-6">
    <div class="px-5 py-4 border-b border-slate-100">
        <h2 class="text-lg font-semibold text-slate-900">Resumen Diario de Autorizaciones (Últimos 30 Días)</h2>
    </div>
    <div class="p-4">
        <table id="tablaResumenDiario" class="display w-full text-sm">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Fecha</th>
                    <th class="text-center">Cantidad Órdenes</th>
                    <th class="text-center">Cantidad Aut-Rech</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div id="modalDetalleOrdenes" class="fixed top-16 left-0 right-0 bottom-0 z-[200] hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-full flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h2 id="tituloDetalleOrdenes" class="text-lg font-semibold text-slate-900">Órdenes del día</h2>
            <button type="button" class="cerrar-detalle-ordenes text-slate-500 hover:text-slate-800" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 overflow-auto">
            <table id="tablaDetalleOrdenes" class="display w-full text-sm">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Prestador</th>
                        <th>Obra Social</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalDetalleAutRech" class="fixed top-16 left-0 right-0 bottom-0 z-[200] hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-full flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h2 id="tituloDetalleAutRech" class="text-lg font-semibold text-slate-900">Autorizadas y rechazadas</h2>
            <button type="button" class="cerrar-detalle-aut-rech text-slate-500 hover:text-slate-800" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 overflow-auto">
            <table id="tablaDetalleAutRech" class="display w-full text-sm">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Prestador</th>
                        <th>Obra Social</th>
                        <th>Estado</th>
                        <th>Descargar PDF</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>
