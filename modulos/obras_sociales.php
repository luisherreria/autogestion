<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=obras_sociales');
    exit;
}

$filas = array();
$error = '';
$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$codigoPrestador = isset($_SESSION['codigo']) ? trim((string) $_SESSION['codigo']) : '';

if ($esAdmin) {
    $sql = "SELECT TRIM(TACODIGO) AS codigo,
                   TRIM(TADESCRIP) AS nombre,
                   TAFECHAINI AS fechaalta,
                   CASE
                     WHEN TAFECHAFIN IS NOT NULL
                      AND TAFECHAFIN <> '0000-00-00 00:00:00'
                      AND TAFECHAFIN <> '0000-00-00'
                      AND CAST(TAFECHAFIN AS CHAR) <> ''
                     THEN TAFECHAFIN
                     ELSE NULL
                   END AS fechabaja,
                   CASE
                     WHEN TAFECHAFIN IS NOT NULL
                      AND TAFECHAFIN <> '0000-00-00 00:00:00'
                      AND TAFECHAFIN <> '0000-00-00'
                      AND CAST(TAFECHAFIN AS CHAR) <> ''
                      AND TAFECHAFIN < CURDATE()
                     THEN 1
                     ELSE 0
                   END AS es_baja,
                   CASE
                     WHEN TAFECHAFIN IS NOT NULL
                      AND TAFECHAFIN <> '0000-00-00 00:00:00'
                      AND TAFECHAFIN <> '0000-00-00'
                      AND CAST(TAFECHAFIN AS CHAR) <> ''
                      AND TAFECHAFIN < (CURDATE() - INTERVAL 180 DAY)
                     THEN 1
                     ELSE 0
                   END AS baja_antigua
            FROM cartilla.obrasoc
            WHERE UPPER(TRIM(TACODIGO)) <> 'OSSEG U'
            ORDER BY codigo ASC";
} else {
    $vigente = "(
        o.fechabaja IS NULL
        OR o.fechabaja = '0000-00-00'
        OR CAST(o.fechabaja AS CHAR) = ''
        OR o.fechabaja >= CURDATE()
    )";
    $sql = "SELECT TRIM(o.obrasoc) AS codigo,
                   COALESCE(NULLIF(MAX(TRIM(s.TADESCRIP)), ''), MAX(TRIM(o.nomobra))) AS nombre,
                   CASE
                     WHEN SUM(CASE WHEN $vigente THEN 1 ELSE 0 END) > 0
                     THEN MIN(CASE WHEN $vigente THEN o.fechaalta END)
                     ELSE MIN(o.fechaalta)
                   END AS fechaalta,
                   CASE
                     WHEN SUM(CASE WHEN $vigente THEN 1 ELSE 0 END) > 0
                     THEN MAX(CASE WHEN $vigente THEN o.fechabaja END)
                     ELSE MAX(o.fechabaja)
                   END AS fechabaja,
                   CASE
                     WHEN SUM(CASE WHEN $vigente THEN 1 ELSE 0 END) > 0 THEN 0
                     ELSE 1
                   END AS es_baja,
                   0 AS baja_antigua
            FROM cartilla.obramed o
            LEFT JOIN cartilla.obrasoc s ON TRIM(o.obrasoc) = TRIM(s.TACODIGO)
            WHERE TRIM(o.medico) = :codigo_prestador
              AND UPPER(TRIM(o.obrasoc)) <> 'OSSEG U'
            GROUP BY TRIM(o.obrasoc)
            ORDER BY codigo ASC";
}

try {
    $pdo = Database::getConnection();
    if ($esAdmin || $codigoPrestador !== '') {
        $stmt = $pdo->prepare($sql);
        $params = array();
        if (!$esAdmin) {
            $params[':codigo_prestador'] = $codigoPrestador;
        }
        $stmt->execute($params);
        $filas = $stmt->fetchAll();
    }
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
    #tablaObrasSociales tr.fila-baja td { background-color: #fee2e2 !important; color: #7f1d1d; cursor: help; }
    .badge-estado { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 11px; font-weight: 700; color: #fff; }
    .badge-estado.bg-danger { background: #dc2626; }
    .badge-estado.bg-success { background: #16a34a; }
</style>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow p-4">
    <?php if ($esAdmin) { ?>
        <label for="mostrar-bajas-antiguas" class="inline-flex items-center gap-3 text-sm text-slate-700 mb-3 cursor-pointer select-none">
            <span class="relative inline-block w-11 h-6">
                <input type="checkbox" id="mostrar-bajas-antiguas" class="peer sr-only">
                <span class="absolute inset-0 rounded-full bg-slate-300 peer-checked:bg-blue-700"></span>
                <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"></span>
            </span>
            Mostrar dadas de baja de más de 180 días
        </label>
    <?php } ?>
    <table id="tablaObrasSociales" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Código</th>
                <th>Obra social</th>
                <th>Fecha inicio</th>
                <th>Fecha baja</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $iniIso = trim((string) $fila['fechaalta']);
                $iniDia = substr($iniIso, 0, 10);
                $finIso = trim((string) $fila['fechabaja']);
                $finDia = substr($finIso, 0, 10);
                $esBaja = ((int) $fila['es_baja'] === 1);
                $fechaFin = fechaObra($fila['fechabaja']);
                $fechaIni = fechaObra($fila['fechaalta']);
                $avisoBaja = $esBaja ? 'DADA DE BAJA DESDE LA FECHA ' . ($fechaFin === '' ? 'S/D' : $fechaFin) : '';
                $bajaAntigua = $esBaja && ((int) $fila['baja_antigua'] === 1);
                ?>
                <tr class="<?php echo $esBaja ? 'fila-baja bg-red-100 text-red-900' : ''; ?>" <?php echo $bajaAntigua ? 'data-baja-antigua="1"' : ''; ?> <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>>
                    <td <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo h($fila['codigo']); ?></td>
                    <td <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo h($fila['nombre']); ?></td>
                    <td data-order="<?php echo h($iniDia); ?>" <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo $fechaIni === '' ? 'S/D' : h($fechaIni); ?></td>
                    <td data-order="<?php echo h($finDia); ?>" <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>><?php echo $fechaFin === '' ? 'S/D' : h($fechaFin); ?></td>
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
    <?php if ($esAdmin) { ?>
        <p class="text-sm text-slate-500 mt-3">Las dadas de baja de los últimos 180 días se ven en rojo. Las más antiguas quedan ocultas hasta activar el interruptor.</p>
    <?php } else { ?>
        <p class="text-sm text-slate-500 mt-3">Las obras con fecha de baja anterior a hoy figuran en rojo.</p>
    <?php } ?>
</div>
