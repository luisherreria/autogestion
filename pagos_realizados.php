<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/includes/bootstrap.php';
    header('Location: index.php?seccion=pagos');
    exit;
}
?>
<style>
    #tablaPagos tr.fila-pagada td { background-color: #dcfce7 !important; }
    #tablaPagos tr.fila-debitada td { background-color: #fff9c4 !important; }
    #tablaPagos td.num, #tablaPagos th.num { text-align: right; white-space: nowrap; }
    #tablaPagos td.nowrap, #tablaPagos th.nowrap { white-space: nowrap; }
    #tablaPagos_wrapper .dataTables_length,
    #tablaPagos_wrapper .dataTables_filter,
    #tablaPagos_wrapper .dt-buttons { margin-bottom: 0.75rem; }
    #tablaPagos.texto-grilla-xs,
    #tablaPagos.texto-grilla-xs th,
    #tablaPagos.texto-grilla-xs td,
    #tablaPagos.texto-grilla-xs td span { font-size: 11px !important; }
    #contenedorExportacionLiq .dt-button { padding: 0.35rem 0.6rem; }
</style>
<div class="flex flex-wrap items-end gap-4 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200 shadow-sm">
    <div class="w-48">
        <label for="campoFiltroLiq" class="block text-xs font-medium text-gray-700 mb-1">Filtrar por Campo:</label>
        <select id="campoFiltroLiq" class="w-full border border-gray-300 rounded text-sm py-1.5 px-2 focus:ring-blue-500">
            <option value="0">Periodo</option>
            <option value="1">Prestador</option>
            <option value="3">Factura</option>
            <option value="11">Recibo</option>
        </select>
    </div>
    <div class="w-64">
        <label for="valorFiltroLiq" class="block text-xs font-medium text-gray-700 mb-1">Valor:</label>
        <div class="flex gap-2">
            <input type="text" id="valorFiltroLiq" class="w-full border border-gray-300 rounded text-sm py-1.5 px-2 focus:ring-blue-500" placeholder="Escriba para filtrar...">
            <button type="button" id="btnLimpiarFiltroLiq" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-sm whitespace-nowrap">Limpiar</button>
        </div>
    </div>
    <div class="w-40">
        <label for="controlTamanoLetra" class="block text-xs font-medium text-gray-700 mb-1"><i class="fa-solid fa-text-height mr-1"></i> Tamaño grilla:</label>
        <select id="controlTamanoLetra" class="w-full border border-gray-300 rounded text-sm py-1.5 px-2 focus:ring-blue-500">
            <option value="texto-grilla-xs">Pequeña</option>
            <option value="text-sm" selected>Normal</option>
            <option value="text-base">Grande</option>
        </select>
    </div>
    <div id="contenedorExportacionLiq" class="ml-auto flex gap-2"></div>
</div>
<div class="bg-white rounded-xl shadow p-4">
    <table id="tablaPagos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Prestador</th>
                <th>O.Social</th>
                <th>Factura</th>
                <th class="num">Facturado</th>
                <th class="num">Importe</th>
                <th class="num">Coseguro</th>
                <th class="num">Debitado</th>
                <th class="num">Liquidado</th>
                <th class="num">Pagado</th>
                <th class="num">Saldo</th>
                <th>Recibo</th>
                <th class="num">Retencion</th>
                <th class="nowrap">O.Pago</th>
                <th class="nowrap">F. Pago</th>
                <th>Archivos</th>
                <th class="text-center">Notif.</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
    <div class="flex flex-wrap items-center gap-4 mt-4 text-sm text-slate-600">
        <div class="flex items-center">
            <span class="inline-block w-4 h-4 mr-2 border border-gray-300 bg-white"></span> Pendiente / Sin Pagar
        </div>
        <div class="flex items-center">
            <span class="inline-block w-4 h-4 mr-2 border border-gray-300" style="background-color: #dcfce7;"></span> Pagada
        </div>
        <div class="flex items-center">
            <span class="inline-block w-4 h-4 mr-2 border border-gray-300" style="background-color: #fff9c4;"></span> Totalmente Debitada
        </div>
    </div>
</div>
