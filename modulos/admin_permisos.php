<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=admin_permisos');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<div class="rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}

$filas = array();
$error = '';
$modulos = mapaModulosPermiso();

try {
    $pdo = Database::getConnection();
    asegurarTablaPermisos($pdo);
    $filas = $pdo->query(
        'SELECT id, tipo_correo, ver_autorizaciones, ver_obras_sociales, ver_coseguros, ver_normativas, ver_contratos, ver_pagos, ver_empadronamiento
         FROM a_permisos
         ORDER BY tipo_correo ASC'
    )->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

function iconoEstadoPermiso($valor)
{
    if ((int) $valor === 1) {
        return '<span class="text-green-600 text-lg" title="Sí"><i class="fa-solid fa-check"></i></span>';
    }
    return '<span class="text-red-600 text-lg" title="No"><i class="fa-solid fa-xmark"></i></span>';
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tablaPermisos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Tipo de correo</th>
                <?php foreach ($modulos as $modulo) { ?>
                    <th class="text-center"><?php echo h($modulo['titulo']); ?></th>
                <?php } ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila) { ?>
                <tr>
                    <td><?php echo h(etiquetaTipoCorreo($fila['tipo_correo'])); ?></td>
                    <?php foreach ($modulos as $columna => $modulo) { ?>
                        <td class="text-center perm-celda" data-campo="<?php echo h($columna); ?>"><?php echo iconoEstadoPermiso($fila[$columna]); ?></td>
                    <?php } ?>
                    <td>
                        <button type="button"
                                class="btn-editar-permiso inline-flex items-center gap-2 bg-blue-800 hover:bg-blue-900 text-white text-sm rounded-lg px-3 py-1.5"
                                data-id="<?php echo (int) $fila['id']; ?>"
                                data-etiqueta="<?php echo h(etiquetaTipoCorreo($fila['tipo_correo'])); ?>"
                                <?php foreach ($modulos as $columna => $modulo) { ?>
                                    data-<?php echo h($columna); ?>="<?php echo ((int) $fila[$columna] === 1) ? '1' : '0'; ?>"
                                <?php } ?>>
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div id="modal-permisos" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Editar permisos</h2>
            <button type="button" id="cerrar-modal-permisos" class="text-slate-500 hover:text-slate-800" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="form-permisos" class="px-5 py-4 space-y-4">
            <input type="hidden" name="id" id="permiso-id" value="">
            <p class="text-sm text-slate-600">Tipo de correo: <span id="permiso-tipo" class="font-medium text-slate-900"></span></p>
            <div id="permisos-error" class="hidden rounded-lg bg-red-50 text-red-700 px-3 py-2 text-sm"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($modulos as $columna => $modulo) { ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="<?php echo h($columna); ?>" value="1" class="rounded border-slate-300">
                        <?php echo h($modulo['titulo']); ?>
                    </label>
                <?php } ?>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="cancelar-modal-permisos" class="rounded-lg px-4 py-2 text-sm border border-slate-300 text-slate-700">Cancelar</button>
                <button type="submit" id="guardar-permisos" class="rounded-lg px-4 py-2 text-sm bg-blue-800 hover:bg-blue-900 text-white">Guardar</button>
            </div>
        </form>
    </div>
</div>
