<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=notificaciones');
    exit;
}
?>
<div class="bg-white rounded-xl shadow p-4">
    <table id="tablaNotificaciones" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Fecha emisión</th>
                <th>Asunto</th>
                <th>Tipo notificación</th>
                <th class="text-center">Acciones</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
