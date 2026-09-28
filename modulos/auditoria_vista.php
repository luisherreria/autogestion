<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=auditoria_vista');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<div class="max-w-4xl mx-auto rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}

$filas = array();
$error = '';

try {
    $pdo = Database::getConnection();
    asegurarTablaAuditoria($pdo);
    $stmt = $pdo->query(
        'SELECT fecha_hora, codigo_prestador, usuario_email, modulo, accion_detalle
         FROM auditoria_log
         ORDER BY fecha_hora DESC, id DESC'
    );
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tabla-auditoria" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Código prestador</th>
                <th>Email/Usuario</th>
                <th>Módulo</th>
                <th>Acción/Archivo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) {
                $fecha = trim((string) $fila['fecha_hora']);
                $marca = strtotime($fecha);
                $fechaTexto = ($marca === false) ? $fecha : date('d/m/Y H:i:s', $marca);
                ?>
                <tr>
                    <td><?php echo h($fechaTexto); ?></td>
                    <td><?php echo h($fila['codigo_prestador']); ?></td>
                    <td><?php echo h($fila['usuario_email']); ?></td>
                    <td><?php echo h($fila['modulo']); ?></td>
                    <td><?php echo h($fila['accion_detalle']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
