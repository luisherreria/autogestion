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

$sqlAmbulatorio = "SELECT auto_id, CONUMERO AS conumero, COFECHA AS cofecha, CONOMPAC AS conompac, COESTADO AS coestado, CONROAUTO AS conroauto, COMEDICO AS codigo_prestador, 'AMBULATORIO' AS origen
        FROM ordenes
        WHERE COFECHA >= :desde_ambu
          AND COFECHA < :hasta_ambu
          AND COESTADO IN ('AUTORIZADA', 'RECHAZADA')";
$sqlSanatorial = "SELECT auto_id, conumero, cofecha, conompac, coestado, conroauto, comedico AS codigo_prestador, 'SANATORIAL' AS origen
        FROM sanorden
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
    $sqlAmbulatorio .= ' AND COMEDICO = :codigo';
    $sqlSanatorial .= ' AND comedico = :codigo_sano';
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
