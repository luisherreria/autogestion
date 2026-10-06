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
            <div class="mt-4 text-xs text-gray-500 bg-gray-50 p-3 rounded border border-gray-200 inline-block text-left">
                <p class="font-bold mb-1 text-gray-600"><i class="fa-solid fa-circle-info mr-1"></i> Columnas requeridas en el Excel:</p>
                <ul class="list-disc list-inside grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-1">
                    <li><b>Código</b> (o Prestador)</li>
                    <li><b>Fecha</b> (o Emisión)</li>
                    <li><b>Sucursal</b> (o Cod Comprobante)</li>
                    <li><b>Número</b> (o Factura)</li>
                    <li><b>Importe</b></li>
                    <li><b>Saldo</b></li>
                </ul>
            </div>
            <input id="archivo-excel" name="archivo" type="file" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="hidden">
        </label>
        <div class="mt-4 flex items-center gap-3">
            <button id="btn-procesar-excel" type="submit" class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-medium text-white hover:bg-blue-900 disabled:opacity-60">Procesar Excel</button>
            <p id="aviso-conciliacion" class="text-sm text-slate-600"></p>
        </div>
    </form>
</div>

<div class="flex flex-col md:flex-row gap-4 mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
    <div class="flex-1">
        <label for="filtroPrestador" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Prestador:</label>
        <input type="text" id="filtroPrestador" class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2" placeholder="Ej: CLI011 o Santa Isabel">
    </div>
    <div class="flex-1">
        <label for="filtroEstado" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Estado:</label>
        <select id="filtroEstado" class="w-full border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm px-3 py-2">
            <option value="">Todos los estados</option>
            <option value="Faltante">Faltantes</option>
            <option value="En Auditoría">En Auditoría</option>
            <option value="En Sistema">En Sistema</option>
        </select>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table id="tablaConciliacion" class="display w-full text-sm">
        <thead>
            <tr>
                <th class="text-center"><input type="checkbox" id="checkAll" checked></th>
                <th>Prestador</th>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th class="text-right">Importe Excel</th>
                <th class="text-right">Saldo Excel</th>
                <th>Estado Sistema</th>
                <th>Orden de Pago</th>
                <th>Fecha Pago</th>
                <th class="text-right">Débito</th>
                <th class="text-right">CSN</th>
                <th class="text-center"><input type="checkbox" id="marcar-todos-enviar" class="w-4 h-4 text-blue-600 rounded cursor-pointer mr-1"> Enviar</th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">Totales Seleccionados:</th>
                <th id="totalImporteSelect" class="text-right">$ 0,00</th>
                <th id="totalSaldoSelect" class="text-right">$ 0,00</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
    <button type="button" id="btn-enviar-mail" class="hidden btn btn-primary bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded shadow-sm text-sm">
        <i class="fa-solid fa-envelope mr-1"></i> Enviar Reclamo por Mail
    </button>
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
        dom: '<"flex flex-col md:flex-row justify-between items-center mb-4" <"flex items-center gap-4"l B> f> rt <"flex justify-between items-center mt-4" i p>',
        buttons: botonesExportacionGrilla({
            title: 'Conciliador de Saldos',
            exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                format: {
                    body: function (dato) {
                        return $('<div>').html(dato).text().trim();
                    }
                }
            }
        }),
        columnDefs: [
            { orderable: false, targets: [0, 11] }
        ],
        order: [[1, 'asc']],
        columns: [
            { data: 'seleccion', orderable: false, searchable: false, className: 'text-center' },
            { data: 'codigo' },
            {
                data: 'fecha',
                className: 'nowrap',
                render: function (dato, tipo, fila) {
                    if (tipo === 'sort' || tipo === 'type') {
                        return fila.fecha_orden || '';
                    }
                    return dato || '';
                }
            },
            { data: 'comprobante' },
            { data: 'importe', className: 'text-right' },
            { data: 'saldo', className: 'text-right' },
            { data: 'estado' },
            { data: 'orden_pago', className: 'nowrap' },
            { data: 'fecha_pago', className: 'nowrap' },
            { data: 'debito', className: 'text-right nowrap' },
            { data: 'csn', className: 'text-right nowrap' },
            { data: 'enviar', orderable: false, searchable: false, className: 'text-center' }
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
    tabla.buttons().container().append($('#btn-enviar-mail').removeClass('hidden'));

    var rutasAdjuntosReclamo = [];

    $('#btn-enviar-mail').on('click', function () {
        var grilla = $('#tablaConciliacion').DataTable();
        var marcadas = grilla.rows({ search: 'applied' }).nodes().to$().find('.cb-enviar-mail:checked');
        if (!marcadas.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin comprobantes',
                text: 'Seleccione al menos un comprobante.',
                confirmButtonColor: '#1e40af'
            });
            return;
        }
        var lineas = [];
        var rutas = [];
        var mail = '';
        marcadas.each(function () {
            var cb = $(this);
            if (mail === '') {
                var fila = grilla.row(cb.closest('tr')).data();
                mail = fila && fila.mail_deb ? fila.mail_deb : '';
            }
            var comprobante = $.trim(cb.attr('data-comprobante') || '');
            var importe = $.trim(cb.attr('data-importe') || '');
            var estado = $.trim(cb.attr('data-estado') || '');
            if (importe.indexOf('$') === -1 && importe !== '') {
                importe = '$' + importe;
            }
            lineas.push('Reclamamos el estado de: Factura ' + comprobante + ' - Importe ' + importe + ' - Estado: ' + estado);
            $.each(['data-path-op', 'data-path-debito', 'data-path-csn'], function (_, atributo) {
                var ruta = $.trim(cb.attr(atributo) || '');
                if (ruta !== '' && $.inArray(ruta, rutas) === -1) {
                    rutas.push(ruta);
                }
            });
        });
        rutasAdjuntosReclamo = rutas;
        $('#mail-destinatario').val(mail);
        $('#mail-asunto').val('Reclamo de saldos');
        $('#mail-mensaje').val(lineas.join('\n'));
        $('#modal-reclamo').removeClass('hidden').addClass('flex');
    });

    $('#mail-enviar').on('click', function () {
        var botonMail = $(this);
        var htmlOriginal = botonMail.html();
        botonMail.prop('disabled', true).text('Enviando...');
        $.ajax({
            url: 'ajax_enviar_reclamo.php',
            type: 'POST',
            dataType: 'json',
            data: {
                destinatario: $('#mail-destinatario').val(),
                asunto: $('#mail-asunto').val(),
                mensaje: $('#mail-mensaje').val(),
                adjuntos: rutasAdjuntosReclamo
            }
        }).done(function (respuesta) {
            if (respuesta && respuesta.status === 'ok') {
                $('#modal-reclamo').addClass('hidden').removeClass('flex');
                Swal.fire({
                    icon: 'success',
                    title: 'Enviado',
                    text: respuesta.mensaje || 'Enviado',
                    confirmButtonColor: '#1e40af'
                });
                return;
            }
            Swal.fire({
                icon: 'error',
                title: 'No se pudo enviar',
                text: respuesta && respuesta.mensaje ? respuesta.mensaje : 'No se pudo enviar el correo.',
                confirmButtonColor: '#1e40af'
            });
        }).fail(function () {
            Swal.fire({
                icon: 'error',
                title: 'No se pudo enviar',
                text: 'No se pudo enviar el correo.',
                confirmButtonColor: '#1e40af'
            });
        }).always(function () {
            botonMail.prop('disabled', false).html(htmlOriginal);
        });
    });

    $('#mail-cancelar, #cerrar-modal-reclamo').on('click', function () {
        $('#modal-reclamo').addClass('hidden').removeClass('flex');
    });

    var timeoutSuma;

    function calcularTotalesSeleccionados() {
        var totalImporte = 0;
        var totalSaldo = 0;
        var grilla = $('#tablaConciliacion').DataTable();

        grilla.rows({ search: 'applied' }).nodes().to$().find('.fila-seleccionada:checked').each(function () {
            var fila = this.closest('tr');
            var textoImporte = fila.cells[4].innerText || '0';
            var textoSaldo = fila.cells[5].innerText || '0';
            totalImporte += parseFloat(textoImporte.replace(/[^0-9.-]+/g, '')) || 0;
            totalSaldo += parseFloat(textoSaldo.replace(/[^0-9.-]+/g, '')) || 0;
        });

        var formato = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' });
        $('#totalImporteSelect').html(formato.format(totalImporte));
        $('#totalSaldoSelect').html(formato.format(totalSaldo));
    }

    $('#filtroPrestador').on('keyup change clear', function () {
        tabla.column(1).search(this.value).draw();
    });

    $('#filtroEstado').on('change', function () {
        tabla.column(6).search(this.value).draw();
    });

    $('#tablaConciliacion').off('search.dt draw.dt');
    $('#tablaConciliacion').on('draw.dt', function () {
        clearTimeout(timeoutSuma);
        timeoutSuma = setTimeout(calcularTotalesSeleccionados, 150);
    });

    $('#tablaConciliacion tbody').off('change', '.fila-seleccionada').on('change', '.fila-seleccionada', function () {
        calcularTotalesSeleccionados();
    });

    $('#checkAll').off('change').on('change', function () {
        var grilla = $('#tablaConciliacion').DataTable();
        var isChecked = $(this).is(':checked');
        grilla.rows({ search: 'applied' }).nodes().to$().find('.fila-seleccionada').prop('checked', isChecked);
        calcularTotalesSeleccionados();
    });

    $('#marcar-todos-enviar').off('change').on('change', function () {
        var table = $('#tablaConciliacion').DataTable();
        $('input.cb-enviar-mail', table.cells().nodes()).prop('checked', this.checked);
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

<div id="modal-reclamo" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Enviar Reclamo por Mail</h2>
            <button type="button" id="cerrar-modal-reclamo" class="text-slate-500 hover:text-slate-800" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div>
                <label for="mail-destinatario" class="block text-sm font-medium text-gray-700 mb-1">Para:</label>
                <input type="text" id="mail-destinatario" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="mail-asunto" class="block text-sm font-medium text-gray-700 mb-1">Asunto:</label>
                <input type="text" id="mail-asunto" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="mail-mensaje" class="block text-sm font-medium text-gray-700 mb-1">Mensaje:</label>
                <textarea id="mail-mensaje" rows="10" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-200">
            <button type="button" id="mail-cancelar" class="px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="mail-enviar" class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700">
                <i class="fa-solid fa-envelope mr-1"></i> Enviar Mail
            </button>
        </div>
    </div>
</div>
