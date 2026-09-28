<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=obras_sociales');
    exit;
}

$esAdmin = !empty($_SESSION['es_admin']) || (isset($_SESSION['nombre']) && strcasecmp($_SESSION['nombre'], 'Luis') === 0);
$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$filas = array();
$error = '';

$sql = "SELECT TRIM(om.obrasoc) AS codigo,
               TRIM(COALESCE(NULLIF(TRIM(os.TADESCRIP), ''), NULLIF(TRIM(om.nomobra), ''), om.obrasoc)) AS nombre
        FROM obramed om
        LEFT JOIN obrasoc os ON TRIM(os.TACODIGO) = TRIM(om.obrasoc)
        WHERE (om.fechabaja IS NULL
               OR om.fechabaja = '0000-00-00'
               OR om.fechabaja > CURDATE())";

$parametros = array();
if (!$esAdmin) {
    $sql .= ' AND TRIM(om.medico) = :codigo';
    $parametros[':codigo'] = $codigo;
}

$sql .= ' GROUP BY codigo, nombre ORDER BY nombre ASC';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php if ($esAdmin) { ?>
    <div class="max-w-4xl mx-auto rounded-lg bg-blue-50 text-blue-900 px-4 py-3 mb-4 text-sm">
        Modo Admin: se listan todas las obras sociales vigentes, sin filtrar por prestador.
    </div>
<?php } ?>
<?php if ($error !== '') { ?>
    <div class="max-w-4xl mx-auto rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tabla-datos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Código</th>
                <th>Obra social</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) { ?>
                <tr>
                    <td><?php echo h($fila['codigo']); ?></td>
                    <td><?php echo h($fila['nombre']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
