<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=conciliacion');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<div class="max-w-4xl mx-auto rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}
?>
<div class="bg-white rounded-xl shadow p-6 mb-6">
    <form id="form-conciliacion" action="ajax_procesar_conciliacion.php" method="post" enctype="multipart/form-data">
        <label id="zona-excel" for="archivo-excel" class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50">
            <i class="fa-solid fa-file-excel text-3xl text-blue-800"></i>
            <span class="text-sm font-medium text-slate-800">Arrastrá el Excel acá o hacé clic para seleccionarlo</span>
            <span id="nombre-excel" class="text-xs text-slate-500">Solo archivos .xls o .xlsx</span>
            <input id="archivo-excel" name="archivo" type="file" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="hidden">
        </label>
        <div class="mt-4 flex items-center gap-3">
            <button id="btn-procesar-excel" type="submit" class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-medium text-white hover:bg-blue-900 disabled:opacity-60">Procesar Excel</button>
            <p id="aviso-conciliacion" class="text-sm text-slate-600"></p>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tablaConciliacion" class="display w-full text-sm">
        <thead>
            <tr>
                <th class="text-center"><input type="checkbox" id="checkAll" checked></th>
                <th>Prestador</th>
                <th>Comprobante</th>
                <th class="text-right">Importe Excel</th>
                <th class="text-right">Saldo Excel</th>
                <th>Estado Sistema</th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-right">Totales Seleccionados:</th>
                <th id="totalImporteSelect" class="text-right">$ 0,00</th>
                <th id="totalSaldoSelect" class="text-right">$ 0,00</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>

<script>
function iniciarConciliacion() {
    if (!window.jQuery || !$.fn.dataTable || !$('#tablaConciliacion').length) {
        return;
    }
    var tabla = $('#tablaConciliacion').DataTable({
        data: [],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'Todas']],
        deferRender: false,
        order: [],
        columns: [
            { data: 'seleccion', orderable: false, searchable: false, className: 'text-center' },
            { data: 'codigo' },
            { data: 'comprobante' },
            { data: 'importe', className: 'text-right' },
            { data: 'saldo', className: 'text-right' },
            { data: 'estado' }
        ],
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty: 'Sin registros',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: 'No se encontraron registros',
            emptyTable: 'Todavía no se procesó un Excel',
            paginate: { previous: 'Anterior', next: 'Siguiente' }
        }
    });

    function calcularTotalesSeleccionados() {
        var totalImporte = 0;
        var totalSaldo = 0;
        var grilla = $('#tablaConciliacion').DataTable();

        grilla.rows({ search: 'applied' }).nodes().to$().find('.fila-seleccionada:checked').each(function () {
            var fila = $(this).closest('tr');
            var textoImporte = fila.find('td').eq(3).text() || '0';
            var textoSaldo = fila.find('td').eq(4).text() || '0';
            var valorImporte = parseFloat(textoImporte.replace(/[^0-9.-]+/g, '')) || 0;
            var valorSaldo = parseFloat(textoSaldo.replace(/[^0-9.-]+/g, '')) || 0;
            totalImporte += valorImporte;
            totalSaldo += valorSaldo;
        });

        var formato = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });
        $('#totalImporteSelect').html(formato.format(totalImporte));
        $('#totalSaldoSelect').html(formato.format(totalSaldo));
    }

    $('#tablaConciliacion tbody').off('change', '.fila-seleccionada').on('change', '.fila-seleccionada', function () {
        calcularTotalesSeleccionados();
    });

    $('#checkAll').off('change').on('change', function () {
        var grilla = $('#tablaConciliacion').DataTable();
        var isChecked = $(this).is(':checked');
        grilla.rows().nodes().to$().find('.fila-seleccionada').prop('checked', isChecked);
        calcularTotalesSeleccionados();
    });

    $('#tablaConciliacion').on('draw.dt', function () {
        calcularTotalesSeleccionados();
    });

    $('#tablaConciliacion').on('search.dt', function () {
        calcularTotalesSeleccionados();
    });

    var input = document.getElementById('archivo-excel');
    var zona = document.getElementById('zona-excel');
    var nombre = document.getElementById('nombre-excel');
    var aviso = document.getElementById('aviso-conciliacion');
    var boton = document.getElementById('btn-procesar-excel');

    function mostrarArchivo(archivo) {
        nombre.textContent = archivo ? archivo.name : 'Solo archivos .xls o .xlsx';
    }

    input.addEventListener('change', function () {
        mostrarArchivo(input.files && input.files[0] ? input.files[0] : null);
    });

    ['dragenter', 'dragover'].forEach(function (evento) {
        zona.addEventListener(evento, function (e) {
            e.preventDefault();
            zona.classList.add('border-blue-500', 'bg-blue-50');
        });
    });
    ['dragleave', 'drop'].forEach(function (evento) {
        zona.addEventListener(evento, function (e) {
            e.preventDefault();
            zona.classList.remove('border-blue-500', 'bg-blue-50');
        });
    });
    zona.addEventListener('drop', function (e) {
        var archivos = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
        if (!archivos || !archivos.length || typeof DataTransfer === 'undefined') {
            return;
        }
        var transferencia = new DataTransfer();
        transferencia.items.add(archivos[0]);
        input.files = transferencia.files;
        mostrarArchivo(archivos[0]);
    });

    $('#form-conciliacion').on('submit', function (e) {
        e.preventDefault();
        if (!input.files || !input.files.length) {
            aviso.textContent = 'Seleccioná un archivo Excel.';
            aviso.className = 'text-sm text-red-600';
            return;
        }
        var datos = new FormData();
        datos.append('archivo', input.files[0]);
        boton.disabled = true;
        aviso.textContent = 'Procesando...';
        aviso.className = 'text-sm text-slate-600';
        $.ajax({
            url: 'ajax_procesar_conciliacion.php',
            type: 'POST',
            data: datos,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (respuesta) {
            var filas = respuesta && respuesta.data ? respuesta.data : [];
            tabla.clear();
            tabla.rows.add(filas);
            $('#checkAll').prop('checked', true);
            tabla.draw();
            if (!respuesta || respuesta.status !== 'ok') {
                aviso.textContent = respuesta && respuesta.mensaje ? respuesta.mensaje : 'No se pudo procesar el Excel.';
                aviso.className = 'text-sm text-red-600';
                return;
            }
            aviso.textContent = filas.length + ' filas leídas.';
            aviso.className = 'text-sm text-green-700';
        }).fail(function () {
            aviso.textContent = 'No se pudo procesar el Excel.';
            aviso.className = 'text-sm text-red-600';
        }).always(function () {
            boton.disabled = false;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarConciliacion);
} else {
    iniciarConciliacion();
}
</script>
