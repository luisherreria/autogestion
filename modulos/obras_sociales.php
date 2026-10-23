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

$sqlAdmin = "SELECT TRIM(TACODIGO) AS codigo,
                    TRIM(TADESCRIP) AS nombre,
                    TAFECHAINI,
                    CASE
                      WHEN TAFECHAFIN IS NOT NULL
                       AND TAFECHAFIN <> '0000-00-00 00:00:00'
                       AND TAFECHAFIN <> '0000-00-00'
                       AND CAST(TAFECHAFIN AS CHAR) <> ''
                       AND TAFECHAFIN <= CURDATE()
                      THEN TAFECHAFIN
                      ELSE NULL
                    END AS fechabaja,
                    CASE
                      WHEN TAFECHAFIN IS NOT NULL
                       AND TAFECHAFIN <> '0000-00-00 00:00:00'
                       AND TAFECHAFIN <> '0000-00-00'
                       AND CAST(TAFECHAFIN AS CHAR) <> ''
                       AND TAFECHAFIN <= CURDATE()
                      THEN 1
                      ELSE 0
                    END AS es_baja
             FROM obrasoc
             WHERE UPPER(TRIM(TACODIGO)) <> 'OSSEG U'
               AND (
                     TAFECHAFIN IS NULL
                     OR TAFECHAFIN = '0000-00-00 00:00:00'
                     OR TAFECHAFIN = '0000-00-00'
                     OR CAST(TAFECHAFIN AS CHAR) = ''
                     OR TAFECHAFIN > CURDATE()
                     OR (
                         TAFECHAFIN <= CURDATE()
                         AND TAFECHAFIN >= (CURDATE() - INTERVAL 180 DAY)
                     )
                   )
             ORDER BY codigo ASC";

$sqlPrestador = "SELECT TRIM(s.TACODIGO) AS codigo,
                        MAX(TRIM(s.TADESCRIP)) AS nombre,
                        MIN(s.TAFECHAINI) AS TAFECHAINI,
                        MAX(CASE
                              WHEN o.fechabaja IS NOT NULL
                               AND o.fechabaja <> '0000-00-00'
                               AND CAST(o.fechabaja AS CHAR) <> ''
                               AND o.fechabaja <= CURDATE()
                              THEN o.fechabaja
                              ELSE NULL
                            END) AS fechabaja,
                        MIN(CASE
                              WHEN o.fechabaja IS NULL
                                OR o.fechabaja = '0000-00-00'
                                OR CAST(o.fechabaja AS CHAR) = ''
                                OR o.fechabaja > CURDATE()
                              THEN 0
                              ELSE 1
                            END) AS es_baja
                 FROM cartilla.obramed o
                 INNER JOIN obrasoc s ON TRIM(o.obrasoc) = TRIM(s.TACODIGO)
                 WHERE TRIM(o.medico) = :codigo_prestador
                   AND UPPER(TRIM(s.TACODIGO)) <> 'OSSEG U'
                   AND (
                         o.fechabaja IS NULL
                         OR o.fechabaja = '0000-00-00'
                         OR CAST(o.fechabaja AS CHAR) = ''
                         OR o.fechabaja > CURDATE()
                         OR (
                             o.fechabaja <= CURDATE()
                             AND o.fechabaja >= (CURDATE() - INTERVAL 180 DAY)
                         )
                       )
                 GROUP BY TRIM(s.TACODIGO)
                 ORDER BY codigo ASC";

try {
    $pdo = Database::getConnection();
    if ($esAdmin) {
        $stmt = $pdo->prepare($sqlAdmin);
        $stmt->execute();
        $filas = $stmt->fetchAll();
    } elseif ($codigoPrestador !== '') {
        $stmt = $pdo->prepare($sqlPrestador);
        $stmt->execute(array(':codigo_prestador' => $codigoPrestador));
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
                $iniIso = trim((string) $fila['TAFECHAINI']);
                $iniDia = substr($iniIso, 0, 10);
                $esBaja = ((int) $fila['es_baja'] === 1);
                $fechaFin = fechaObra($fila['fechabaja']);
                $fechaIni = fechaObra($fila['TAFECHAINI']);
                $avisoBaja = $esBaja ? 'DADA DE BAJA DESDE LA FECHA ' . ($fechaFin === '' ? 'S/D' : $fechaFin) : '';
                ?>
                <tr class="<?php echo $esBaja ? 'fila-baja bg-red-100 text-red-900' : ''; ?>" <?php echo $avisoBaja !== '' ? 'title="' . h($avisoBaja) . '"' : ''; ?>>
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
