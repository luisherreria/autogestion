<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/includes/bootstrap.php';
    header('Location: index.php?seccion=pagos');
    exit;
}
?>
<style>
    #tablaPagos tr.fila-pagada td { background-color: #dcfce7 !important; }
    #tablaPagos td.num, #tablaPagos th.num { text-align: right; }
    #tablaPagos_wrapper .dataTables_length,
    #tablaPagos_wrapper .dataTables_filter,
    #tablaPagos_wrapper .dt-buttons { margin-bottom: 0.75rem; }
</style>
<div class="bg-white rounded-xl shadow p-4">
    <table id="tablaPagos" class="display w-full text-sm">
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Prestador</th>
                <th>O.Social</th>
                <th>Suc</th>
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
                <th>Pago</th>
                <th>O.Pago</th>
                <th>F. Pago</th>
                <th>pdfdebito</th>
                <th>pdfcsn</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
