<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=pagos');
    exit;
}

$config = appConfig();
$directorio = $config['dirs']['comprobantes'];
$esAdmin = !empty($_SESSION['es_admin']);
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$filas = array();
$error = '';

$sql = 'SELECT FECHA, DETALLE, IMPORTE, TIPOPAGO, FACTURA
        FROM pagos';
$parametros = array();
if (!$esAdmin) {
    $sql .= ' WHERE TRIM(PRESTADOR) = :codigo';
    $parametros[':codigo'] = $codigo;
}
$sql .= ' ORDER BY FECHA DESC, id DESC';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tabla-datos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Comprobante</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $tipoPago = trim((string) $fila['TIPOPAGO']);
                $estado = $tipoPago === '' ? 'PAGADO' : 'PAGADO - ' . $tipoPago;
                $factura = trim((string) $fila['FACTURA']);
                $pdf = $factura === '' ? '' : buscarArchivo($directorio, array($factura . '.pdf'));
                ?>
                <tr>
                    <td><?php echo h(formatearFecha($fila['FECHA'])); ?></td>
                    <td><?php echo h($fila['DETALLE']); ?></td>
                    <td><?php echo h(formatearImporte($fila['IMPORTE'])); ?></td>
                    <td>
                        <span class="inline-block rounded-full px-2 py-1 text-xs font-medium bg-green-100 text-green-800">
                            <?php echo h($estado); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pdf !== '') { ?>
                            <a href="<?php echo h(urlDescarga('comprobantes', $pdf, false)); ?>"
                               class="inline-flex items-center gap-1 text-blue-800 hover:text-blue-950">
                                <i class="fa-solid fa-download"></i> Descargar
                            </a>
                        <?php } else { ?>
                            <span class="text-slate-400">No disponible</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
