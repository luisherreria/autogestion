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
                <th class="border-b p-2">Fecha emisión</th>
                <th class="border-b p-2">Razón social</th>
                <th class="border-b p-2">Comprobante</th>
                <th class="border-b p-2">Obra Social</th>
                <th class="border-b p-2">Asunto</th>
                <th class="border-b p-2">Tipo notificación</th>
                <th class="border-b p-2 text-center">Acciones</th>
                <th class="hidden">Remitente</th>
                <th class="hidden">Destinatarios</th>
                <th class="hidden">Correos del prestador</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
