<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=autorizaciones');
    exit;
}

$config = appConfig();
$directorio = $config['dirs']['autorizaciones'];
$esAdmin = !empty($_SESSION['es_admin']);
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$filas = array();
$error = '';

$sqlEstado = "CASE
        WHEN UPPER(TRIM(IFNULL(COBORRADO, ''))) IN ('T', '1', 'S') THEN 'BORRADA'
        WHEN UPPER(TRIM(IFNULL(CORECHAZA, ''))) NOT IN ('', '0', 'F') THEN 'RECHAZADA'
        ELSE 'AUTORIZADA'
    END";

$sql = "SELECT numero,
        MAX(fecha) AS fecha,
        MAX(paciente) AS paciente,
        CASE MAX(CASE estado
            WHEN 'BORRADA' THEN 3
            WHEN 'RECHAZADA' THEN 2
            ELSE 1
        END)
            WHEN 3 THEN 'BORRADA'
            WHEN 2 THEN 'RECHAZADA'
            ELSE 'AUTORIZADA'
        END AS estado
    FROM (
        SELECT TRIM(CONUMERO) AS numero,
               COFECHA AS fecha,
               TRIM(CONOMPAC) AS paciente,
               TRIM(COPRESTADO) AS prestador,
               {$sqlEstado} AS estado
        FROM autoriza
        UNION ALL
        SELECT TRIM(CONUMERO) AS numero,
               COFECHA AS fecha,
               TRIM(CONOMPAC) AS paciente,
               TRIM(COPRESTADO) AS prestador,
               {$sqlEstado} AS estado
        FROM sanauto
    ) ordenes_prestador";

$parametros = array();
if (!$esAdmin) {
    $sql .= ' WHERE TRIM(prestador) = :codigo';
    $parametros[':codigo'] = $codigo;
}

$sql .= ' GROUP BY numero ORDER BY fecha DESC, numero DESC';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

function claseEstado($estado)
{
    if ($estado === 'RECHAZADA') {
        return 'bg-red-100 text-red-800';
    }
    if ($estado === 'BORRADA') {
        return 'bg-slate-200 text-slate-700';
    }
    return 'bg-green-100 text-green-800';
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tabla-datos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Paciente</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $pdf = buscarArchivo($directorio, candidatosPdfNumero($fila['numero']));
                ?>
                <tr>
                    <td><?php echo h($fila['numero']); ?></td>
                    <td><?php echo h(formatearFecha($fila['fecha'])); ?></td>
                    <td><?php echo h($fila['paciente']); ?></td>
                    <td>
                        <span class="inline-block rounded-full px-2 py-1 text-xs font-medium <?php echo claseEstado($fila['estado']); ?>">
                            <?php echo h($fila['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pdf !== '') { ?>
                            <a href="<?php echo h(urlDescarga('autorizaciones', $pdf, true)); ?>" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-red-700 hover:text-red-900" title="Ver PDF">
                                <i class="fa-solid fa-file-pdf text-lg"></i>
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
