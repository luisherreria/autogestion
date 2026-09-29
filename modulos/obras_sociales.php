<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=obras_sociales');
    exit;
}

$filas = array();
$error = '';
$hoy = date('Y-m-d');

$sql = "SELECT TRIM(TACODIGO) AS codigo,
               TRIM(TADESCRIP) AS nombre,
               TAFECHAINI,
               TAFECHAFIN
        FROM OBRASOC
        WHERE TAFECHAFIN >= (CURDATE() - INTERVAL 180 DAY)
          AND UPPER(TRIM(TACODIGO)) <> 'OSSEG U'
        ORDER BY TRIM(TACODIGO) ASC";

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

function fechaObra($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
        return '';
    }
    $marca = strtotime($valor);
    if ($marca === false) {
        return '';
    }
    return date('d/m/Y', $marca);
}
?>
<style>
    #tablaObrasSociales tr.fila-baja td { background-color: #fee2e2 !important; color: #991b1b; cursor: help; }
    .badge-estado { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 11px; font-weight: 700; color: #fff; }
    .badge-estado.bg-danger { background: #dc2626; }
    .badge-estado.bg-success { background: #16a34a; }
</style>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow p-4">
    <table id="tablaObrasSociales" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Código</th>
                <th>Obra social</th>
                <th>Fecha inicio</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $finIso = trim((string) $fila['TAFECHAFIN']);
                $finDia = substr($finIso, 0, 10);
                $iniIso = trim((string) $fila['TAFECHAINI']);
                $iniDia = substr($iniIso, 0, 10);
                $esBaja = ($finDia !== '' && $finDia < $hoy);
                $fechaFin = fechaObra($fila['TAFECHAFIN']);
                $fechaIni = fechaObra($fila['TAFECHAINI']);
                $avisoBaja = $esBaja ? 'DADA DE BAJA DESDE LA FECHA ' . ($fechaFin === '' ? 'S/D' : $fechaFin) : '';
                ?>
                <tr class="<?php echo $esBaja ? 'fila-baja' : ''; ?>" <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>>
                    <td <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo h($fila['codigo']); ?></td>
                    <td <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo h($fila['nombre']); ?></td>
                    <td data-order="<?php echo h($iniDia); ?>" <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo $fechaIni === '' ? 'S/D' : h($fechaIni); ?></td>
                    <td <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>>
                        <?php if ($esBaja) { ?>
                            <span class="badge-estado bg-danger">BAJA</span>
                        <?php } else { ?>
                            <span class="badge-estado bg-success">ACTIVA</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <p class="text-sm text-slate-500 mt-3">Se mostrarán las OS dadas de baja por un plazo de 180 días, en color rojo.</p>
</div>
