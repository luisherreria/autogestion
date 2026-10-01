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
        'SELECT a.fecha_hora, a.codigo_prestador, a.usuario_email, a.modulo, a.accion_detalle, e.NOMBRE AS nombre_prestador
         FROM auditoria_log a
         LEFT JOIN (
             SELECT TRIM(CODIGO) AS codigo, MAX(TRIM(NOMBRE)) AS NOMBRE
             FROM ebamp
             GROUP BY TRIM(CODIGO)
         ) e ON e.codigo = TRIM(a.codigo_prestador)
         ORDER BY a.fecha_hora DESC, a.id DESC
         LIMIT 500'
    );
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php
function urlAccionAuditoria($modulo, $accion)
{
    $accion = trim((string) $accion);
    if ($accion === '' || strpos($accion, '..') !== false && !preg_match('#^\.\./uploads/(pdf|pdftango)/[^\\\\/]+\.(pdf|png|jpe?g)$#i', $accion)) {
        return '';
    }
    if (!preg_match('/\.(pdf|png|jpe?g)$/i', $accion)) {
        return '';
    }
    if (preg_match('#^\.\./uploads/(pdf|pdftango)/[^\\\\/]+\.(pdf|png|jpe?g)$#i', $accion)) {
        return $accion;
    }
    if ($accion[0] === '/' || $accion[0] === '\\' || strpos($accion, '..') !== false) {
        return '';
    }
    $tipos = array(
        'Autorizaciones' => 'autorizaciones',
        'Normativas' => 'normativas',
        'Coseguros y APB' => 'coseguros',
        'Contratos' => 'contratos',
    );
    if (isset($tipos[$modulo])) {
        return 'descargar.php?tipo=' . rawurlencode($tipos[$modulo]) . '&archivo=' . rawurlencode($accion) . '&modo=ver';
    }
    if ($modulo === 'Liquidaciones' && preg_match('/\.pdf$/i', $accion) && strpos($accion, '/') === false) {
        return 'descargar_pago.php?archivo=' . rawurlencode($accion);
    }
    return '';
}

function htmlAccionAuditoria($modulo, $accion)
{
    $accion = trim((string) $accion);
    $url = urlAccionAuditoria($modulo, $accion);
    if ($url === '') {
        return h($accion);
    }
    return '<a href="' . h($url) . '" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline font-medium inline-flex items-center gap-1" title="Abrir archivo">'
        . '<i class="fa-solid fa-up-right-from-square"></i> ' . h($accion) . '</a>';
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="flex flex-wrap items-end justify-center gap-4 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200 shadow-sm">
    <div class="w-48">
        <label for="campoFiltroAuditoria" class="block text-xs font-medium text-gray-700 mb-1">Filtrar por Campo:</label>
        <select id="campoFiltroAuditoria" class="w-full border border-gray-300 rounded text-sm py-1.5 px-2 focus:ring-blue-500">
            <option value="1">Código Prestador</option>
            <option value="2">Nombre Prestador</option>
            <option value="3">Email/Usuario</option>
            <option value="4">Módulo</option>
        </select>
    </div>
    <div class="w-64">
        <label for="valorFiltroAuditoria" class="block text-xs font-medium text-gray-700 mb-1">Valor:</label>
        <div class="flex gap-2">
            <input type="text" id="valorFiltroAuditoria" class="w-full border border-gray-300 rounded text-sm py-1.5 px-2 focus:ring-blue-500" placeholder="Escriba para filtrar...">
            <button type="button" id="btnLimpiarFiltroAud" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-sm whitespace-nowrap">Limpiar</button>
        </div>
    </div>
    <div id="contenedorExportacionAud" class="flex gap-2"></div>
</div>
<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tabla-auditoria" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Código prestador</th>
                <th>Nombre Prestador</th>
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
                $codigo = trim((string) $fila['codigo_prestador']);
                $nombrePrestador = trim((string) $fila['nombre_prestador']);
                if ($nombrePrestador === '') {
                    $nombrePrestador = '-';
                }
                ?>
                <tr>
                    <td><?php echo h($fechaTexto); ?></td>
                    <td><?php echo h($codigo); ?></td>
                    <td><span class="text-sm text-gray-600 truncate max-w-[200px] block" title="<?php echo h($nombrePrestador); ?>"><?php echo h($nombrePrestador); ?></span></td>
                    <td><?php echo h($fila['usuario_email']); ?></td>
                    <td><?php echo h($fila['modulo']); ?></td>
                    <td><?php echo htmlAccionAuditoria($fila['modulo'], $fila['accion_detalle']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
