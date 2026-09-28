<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

define('PORTAL_AUTOGESTION', true);

$secciones = array(
    'autorizaciones' => array('titulo' => 'Autorizaciones', 'icono' => 'fa-file-medical'),
    'obras_sociales' => array('titulo' => 'Obras Sociales Vigentes', 'icono' => 'fa-briefcase-medical'),
    'coseguros' => array('titulo' => 'Coseguros', 'icono' => 'fa-file-invoice-dollar'),
    'normativas' => array('titulo' => 'Normativas', 'icono' => 'fa-book'),
    'contratos' => array('titulo' => 'Contratos', 'icono' => 'fa-file-contract'),
    'pagos' => array('titulo' => 'Pagos Realizados', 'icono' => 'fa-money-bill-wave'),
    'admin_archivos' => array('titulo' => 'Gestión de Archivos', 'icono' => 'fa-cloud-arrow-up', 'solo_admin' => true),
    'auditoria_vista' => array('titulo' => 'Control de Auditoría', 'icono' => 'fa-clipboard-list', 'solo_admin' => true),
);

$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'autorizaciones';
if (!isset($secciones[$seccion])) {
    $seccion = 'autorizaciones';
}

$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
$codigo = isset($_SESSION['codigo']) ? $_SESSION['codigo'] : '';
$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$esLuis = $esAdmin;

if ($seccion === 'admin_archivos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require dirname(__FILE__) . '/modulos/admin_archivos_post.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($secciones[$seccion]['titulo']); ?> - Autogestión</title>
    <link rel="icon" type="image/x-icon" href="upload/img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <?php if ($seccion === 'auditoria_vista' || $seccion === 'pagos') { ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <?php } ?>
    <style>
        #tabla-datos_wrapper .dataTables_filter input,
        #tabla-datos_wrapper .dataTables_length select,
        #tablaPagos_wrapper .dataTables_filter input,
        #tablaPagos_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
        }
        #tablaPagos_wrapper .dt-buttons { margin: 0 0 0.75rem; }
        #tablaPagos_wrapper .dt-buttons .dt-button {
            background: #1e40af;
            color: #fff;
            border: 0;
            border-radius: 0.375rem;
            padding: 0.25rem 0.75rem;
            margin-right: 0.35rem;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
    <header class="fixed top-0 left-0 right-0 h-16 bg-blue-800 text-white flex items-center justify-between px-4 z-50 shadow">
        <div class="flex items-center gap-3">
            <img src="upload/img/comedica_bl.png" alt="Comedica" class="h-10 w-auto object-contain">
            <span class="font-semibold text-lg">Autogestión Prestadores</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm text-blue-100">
                <i class="fa-solid fa-user mr-1"></i>
                <?php echo h($nombre); ?>
            </span>
            <a href="logout.php" class="bg-blue-950 hover:bg-black text-white text-sm rounded-lg px-3 py-2">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar sesión
            </a>
        </div>
    </header>

    <aside class="fixed top-16 left-0 bottom-0 w-64 bg-slate-900 text-slate-200 z-40 overflow-y-auto">
        <div class="px-4 py-6 border-b border-slate-700 text-center">
            <div class="text-3xl text-blue-300 mb-2"><i class="fa-solid fa-user-circle"></i></div>
            <div class="font-medium text-white"><?php echo h($nombre); ?></div>
            <div class="text-xs text-slate-400 mt-1">
                <?php echo $esAdmin ? 'Administrador' : h($codigo); ?>
            </div>
        </div>
        <nav class="py-3">
            <?php foreach ($secciones as $clave => $item) {
                if (!empty($item['solo_admin'])) {
                    continue;
                }
                ?>
                <a href="index.php?seccion=<?php echo h($clave); ?>"
                   class="nav-portal flex items-center gap-3 px-4 py-3 text-sm hover:bg-slate-800 <?php echo $seccion === $clave ? 'bg-slate-800 text-white border-l-4 border-blue-500' : 'border-l-4 border-transparent'; ?>">
                    <i class="fa-solid <?php echo h($item['icono']); ?> w-5 text-center"></i>
                    <span><?php echo h($item['titulo']); ?></span>
                </a>
            <?php } ?>
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') { ?>
                <details class="border-t border-slate-700" <?php echo ($seccion === 'admin_archivos' || $seccion === 'auditoria_vista') ? 'open' : ''; ?>>
                    <summary class="flex items-center gap-3 px-4 py-3 text-sm cursor-pointer hover:bg-slate-800 list-none">
                        <i class="fa-solid fa-user-shield w-5 text-center"></i>
                        <span class="flex-1">Administrador</span>
                        <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
                    </summary>
                    <a href="index.php?seccion=admin_archivos"
                       class="nav-portal flex items-center gap-3 pl-10 pr-4 py-2 text-sm hover:bg-slate-800 <?php echo $seccion === 'admin_archivos' ? 'bg-slate-800 text-white' : ''; ?>">
                        <i class="fa-solid fa-cloud-arrow-up w-5 text-center"></i>
                        <span>Gestión de Archivos</span>
                    </a>
                    <a href="index.php?seccion=auditoria_vista"
                       class="nav-portal flex items-center gap-3 pl-10 pr-4 py-2 text-sm hover:bg-slate-800 <?php echo $seccion === 'auditoria_vista' ? 'bg-slate-800 text-white' : ''; ?>">
                        <i class="fa-solid fa-clipboard-list w-5 text-center"></i>
                        <span>Control de Auditoría</span>
                    </a>
                </details>
            <?php } ?>
        </nav>
    </aside>

    <main class="ml-64 mt-16 p-6 min-h-screen">
        <h1 class="text-2xl font-semibold text-slate-900 mb-4"><?php echo h($secciones[$seccion]['titulo']); ?></h1>
        <?php require dirname(__FILE__) . '/modulos/' . $seccion . '.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <?php if ($seccion === 'auditoria_vista' || $seccion === 'pagos') { ?>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <?php } ?>
    <script>
        $(function () {
            var idiomaTabla = {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Sin registros',
                infoFiltered: '(filtrado de _MAX_)',
                zeroRecords: 'No se encontraron registros',
                loadingRecords: 'Cargando...',
                processing: 'Cargando...',
                emptyTable: 'No hay pagos para mostrar',
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior'
                }
            };
            if ($('#tabla-datos').length) {
                $('#tabla-datos').DataTable({
                    pageLength: 25,
                    order: [],
                    language: idiomaTabla
                });
            }
            if ($('#tablaPagos').length && $.fn.dataTable) {
                function iconoPdfPago(archivo) {
                    if (!archivo) {
                        return '-';
                    }
                    var url = 'descargar_pago.php?archivo=' + encodeURIComponent(archivo);
                    return '<a href="' + url + '" target="_blank" title="Ver PDF" class="text-red-600 text-lg"><i class="fa-solid fa-file-pdf"></i></a>';
                }
                $('#tablaPagos').DataTable({
                    ajax: 'ajax_pagos.php',
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todos']],
                    order: [[0, 'desc']],
                    deferRender: true,
                    scrollX: true,
                    processing: true,
                    dom: 'lBfrtip',
                    buttons: [
                        { extend: 'excel', text: 'Excel', exportOptions: { orthogonal: 'export' } },
                        {
                            extend: 'pdf',
                            text: 'PDF',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            exportOptions: { orthogonal: 'export' }
                        },
                        { extend: 'print', text: 'Imprimir', exportOptions: { orthogonal: 'export' } }
                    ],
                    columns: [
                        { data: 'periodo' },
                        { data: 'prestador' },
                        { data: 'obrasocial' },
                        { data: 'suc' },
                        { data: 'factura' },
                        { data: 'facturado', className: 'num' },
                        { data: 'importe', className: 'num' },
                        { data: 'coseguro', className: 'num' },
                        { data: 'debitado', className: 'num' },
                        { data: 'liquidado', className: 'num' },
                        { data: 'pagado', className: 'num' },
                        { data: 'saldo', className: 'num' },
                        { data: null, defaultContent: '-', render: function () { return '-'; } },
                        { data: null, defaultContent: '-', className: 'num', render: function () { return '-'; } },
                        {
                            data: 'pagada',
                            className: 'text-center',
                            orderable: false,
                            render: function (dato, tipo) {
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return dato ? 'Si' : 'No';
                                }
                                return dato ? '<input type="checkbox" checked="checked" disabled="disabled">' : '<input type="checkbox" disabled="disabled">';
                            }
                        },
                        { data: 'orden' },
                        { data: 'fecha' },
                        {
                            data: 'deb',
                            className: 'text-center',
                            render: function (dato, tipo) {
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return dato || '';
                                }
                                return iconoPdfPago(dato);
                            }
                        },
                        {
                            data: 'csn',
                            className: 'text-center',
                            render: function (dato, tipo) {
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return dato || '';
                                }
                                return iconoPdfPago(dato);
                            }
                        }
                    ],
                    createdRow: function (fila, dato) {
                        if (dato.pagada) {
                            $(fila).addClass('fila-pagada');
                        }
                    },
                    language: idiomaTabla
                });
            }
            if ($('#tabla-auditoria').length) {
                $('#tabla-auditoria').DataTable({
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todas']],
                    order: [],
                    dom: 'lBfrtip',
                    buttons: ['excel', 'pdf', 'print'],
                    language: idiomaTabla
                });
            }

            function registrarAuditoria(modulo, detalle) {
                var datos = {
                    modulo: modulo,
                    detalle: detalle
                };
                if (window.navigator && navigator.sendBeacon) {
                    var cuerpo = new Blob(
                        ['modulo=' + encodeURIComponent(modulo) + '&detalle=' + encodeURIComponent(detalle)],
                        {type: 'application/x-www-form-urlencoded'}
                    );
                    navigator.sendBeacon('ajax_auditoria.php', cuerpo);
                    return;
                }
                $.ajax({
                    url: 'ajax_auditoria.php',
                    type: 'POST',
                    data: datos
                });
            }

            $('aside').on('click', 'a.nav-portal', function () {
                registrarAuditoria('menu', $.trim($(this).text()));
            });

            $(document).on('click', 'a[target="_blank"], a[href*="descargar.php"]', function () {
                var href = $(this).attr('href') || '';
                registrarAuditoria('descarga', href);
            });
        });
    </script>
<!-- Reloj Flotante Inferior Derecho -->
    <div id="reloj-flotante" class="fixed bottom-0 right-0 bg-blue-800 text-white px-4 py-1.5 rounded-tl-lg text-sm font-semibold shadow-lg z-50">
        <i class="fa-regular fa-clock mr-1"></i> <span id="reloj-texto">Cargando hora...</span>
    </div>

    <script>
        function actualizarReloj() {
            const ahora = new Date();
            
            // Opciones para la fecha (ej: Viernes, 25 de septiembre de 2026)
            const opcionesFecha = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            let fecha = ahora.toLocaleDateString('es-ES', opcionesFecha);
            // Capitalizar la primera letra
            fecha = fecha.charAt(0).toUpperCase() + fecha.slice(1);
            
            // Hora en formato 24h
            const hora = ahora.toLocaleTimeString('es-ES', { hour12: false });
            
            document.getElementById('reloj-texto').innerHTML = `${hora} | ${fecha}`;
        }
        
        // Actualizar cada segundo
        setInterval(actualizarReloj, 1000);
        actualizarReloj(); // Ejecutar al instante
    </script>
</body>
</html>
