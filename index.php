<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

define('PORTAL_AUTOGESTION', true);

$secciones = array(
    'autorizaciones' => array('titulo' => 'Autorizaciones', 'icono' => 'fa-file-medical'),
    'obras_sociales' => array('titulo' => 'Obras Sociales Vigentes', 'icono' => 'fa-briefcase-medical'),
    'coseguros' => array('titulo' => 'Coseguros y APB', 'icono' => 'fa-file-invoice-dollar'),
    'normativas' => array('titulo' => 'Normativas', 'icono' => 'fa-book'),
    'contratos' => array('titulo' => 'Contratos', 'icono' => 'fa-file-contract'),
    'pagos' => array('titulo' => 'Liquidaciones', 'icono' => 'fa-money-bill-wave'),
    'empadronamiento' => array('titulo' => 'Empadronamiento Afiliados', 'icono' => 'fa-id-card', 'permiso' => 'ver_empadronamiento'),
    'perfil' => array('titulo' => 'Mi perfil', 'icono' => 'fa-gear', 'oculto' => true, 'libre' => true),
    'notificaciones' => array('titulo' => 'Notificaciones', 'icono' => 'fa-bell', 'oculto' => true),
    'admin_archivos' => array('titulo' => 'Gestión de Archivos', 'icono' => 'fa-cloud-arrow-up', 'solo_admin' => true),
    'auditoria_vista' => array('titulo' => 'Control de Auditoría', 'icono' => 'fa-clipboard-list', 'solo_admin' => true),
    'admin_permisos' => array('titulo' => 'Gestión de Permisos', 'icono' => 'fa-user-lock', 'solo_admin' => true),
    'conciliacion' => array('titulo' => 'Conciliador de Saldos', 'icono' => 'fa-scale-balanced', 'solo_admin' => true),
);

if (!empty($GLOBALS['forzarSeccion'])) {
    $seccion = $GLOBALS['forzarSeccion'];
} else {
    $seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'autorizaciones';
}
if (!isset($secciones[$seccion])) {
    $seccion = 'autorizaciones';
}
if ($seccion === 'perfil' && empty($GLOBALS['renderPerfil'])) {
    header('Location: perfil.php');
    exit;
}

$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
$codigo = isset($_SESSION['codigo']) ? $_SESSION['codigo'] : '';
$inicialesUsuario = '';
$nombrePerfil = isset($_SESSION['usuario_nombre']) ? trim($_SESSION['usuario_nombre']) : '';
$apellidoPerfil = isset($_SESSION['usuario_apellido']) ? trim($_SESSION['usuario_apellido']) : '';
$emailOperador = isset($_SESSION['email']) ? trim($_SESSION['email']) : '';
if ($nombrePerfil !== '' && $apellidoPerfil !== '') {
    $inicialesUsuario = strtoupper(substr($nombrePerfil, 0, 1) . substr($apellidoPerfil, 0, 1));
}
$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$esLuis = $esAdmin;
$accesoDenegado = false;

try {
    refrescarPermisosSesion(Database::getConnection());
} catch (Exception $e) {
    if (!$esAdmin) {
        $_SESSION['permisos'] = permisosVacios();
    }
}

$soloAdmin = !empty($secciones[$seccion]['solo_admin']);
$clavePermisoActual = isset($secciones[$seccion]['permiso']) ? $secciones[$seccion]['permiso'] : $seccion;
$puedeVer = $esAdmin || !empty($secciones[$seccion]['libre']) || (!$soloAdmin && tienePermiso($clavePermisoActual));
if ($seccion === 'notificaciones') {
    $puedeVer = $esAdmin || tienePermiso('pagos') || tienePermiso('autorizaciones');
}
if (!$puedeVer) {
    if (!isset($_GET['seccion']) || $_GET['seccion'] === '') {
        foreach ($secciones as $claveAlternativa => $itemAlternativo) {
            if (!empty($itemAlternativo['solo_admin'])) {
                continue;
            }
            $permisoAlternativo = isset($itemAlternativo['permiso']) ? $itemAlternativo['permiso'] : $claveAlternativa;
            if (tienePermiso($permisoAlternativo)) {
                header('Location: index.php?seccion=' . rawurlencode($claveAlternativa));
                exit;
            }
        }
    }
    $accesoDenegado = true;
}

if ($seccion === 'admin_archivos' && $_SERVER['REQUEST_METHOD'] === 'POST' && $puedeVer) {
    require dirname(__FILE__) . '/modulos/admin_archivos_post.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($accesoDenegado ? 'Acceso denegado' : $secciones[$seccion]['titulo']); ?> - Autogestión</title>
    <link rel="icon" type="image/x-icon" href="upload/img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            #contenido-notificacion, #contenido-notificacion * { visibility: visible; }
            #contenido-notificacion { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <?php if ($seccion === 'auditoria_vista' || $seccion === 'pagos' || $seccion === 'obras_sociales' || $seccion === 'autorizaciones' || $seccion === 'conciliacion') { ?>
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
        #tablaPagos_wrapper .dt-buttons,
        #tablaObrasSociales_wrapper .dt-buttons { margin: 0 0 0.75rem; }
        #tablaPagos_wrapper .dt-buttons .dt-button,
        #tablaObrasSociales_wrapper .dt-buttons .dt-button,
        #tablaAutorizaciones_wrapper .dt-buttons .dt-button {
            background: #1e40af;
            color: #fff;
            border: 0;
            border-radius: 0.375rem;
            padding: 0.25rem 0.75rem;
            margin-right: 0.35rem;
        }
        #tablaAutorizaciones_wrapper .controles-autorizaciones {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        #tablaAutorizaciones_wrapper .controles-autorizaciones .dataTables_length,
        #tablaAutorizaciones_wrapper .controles-autorizaciones .dt-buttons,
        #tablaAutorizaciones_wrapper .controles-autorizaciones .dataTables_filter {
            float: none;
            margin: 0;
        }
        #tablaAutorizaciones_wrapper .controles-autorizaciones .dt-buttons {
            display: inline-flex;
            align-items: center;
        }
        #tablaAutorizaciones_wrapper .controles-autorizaciones .dataTables_length {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        #tablaAutorizaciones_wrapper .controles-medio {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        #tablaAutorizaciones_wrapper .dataTables_filter {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        #grupo-estado-autorizaciones {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #475569;
            font-size: 0.875rem;
        }
        #filtroEstadoAutorizaciones,
        #tablaAutorizaciones_wrapper .dataTables_filter input,
        #tablaAutorizaciones_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            padding: 0.35rem 0.6rem;
            background: #fff;
        }
        #tablaAutorizaciones th:nth-child(3),
        #tablaAutorizaciones td:nth-child(3) {
            width: 220px;
            max-width: 240px;
            white-space: normal;
        }
        #tablaAutorizaciones {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100% !important;
        }
        #tablaAutorizaciones thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 0.85rem;
        }
        #tablaAutorizaciones tbody td {
            padding: 0.7rem 0.85rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #1e293b;
        }
        #tablaAutorizaciones tbody tr:hover td {
            background: #f8fafc;
        }
        #tablaAutorizaciones_wrapper .dataTables_info,
        #tablaAutorizaciones_wrapper .dataTables_paginate {
            margin-top: 0.75rem;
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
            <span class="flex items-center gap-2">
                <?php if ($inicialesUsuario !== '') { ?>
                    <span class="w-8 h-8 rounded-full bg-blue-500 inline-flex items-center justify-center text-white font-bold text-xs shadow-md"><?php echo h($inicialesUsuario); ?></span>
                <?php } else { ?>
                    <i class="fa-solid fa-user text-blue-100"></i>
                <?php } ?>
                <span class="flex flex-col leading-tight text-right">
                    <span class="text-sm text-blue-100"><?php echo h($nombre); ?></span>
                    <?php if (!$esAdmin) { ?>
                        <?php if ($nombrePerfil !== '' && $apellidoPerfil !== '') { ?>
                            <span class="text-xs text-gray-200"><?php echo h($nombrePerfil . ' ' . $apellidoPerfil); ?></span>
                        <?php } elseif ($emailOperador !== '') { ?>
                            <span class="text-xs text-gray-200 break-all"><?php echo h($emailOperador); ?></span>
                        <?php } ?>
                    <?php } ?>
                </span>
            </span>
            <a href="notificaciones.php" class="relative inline-flex items-center text-white hover:text-blue-100" title="Notificaciones">
                <i class="fa-solid fa-bell text-lg"></i>
                <span id="badge-notificaciones" class="hidden absolute -top-2 -right-2 min-w-[1.1rem] h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold items-center justify-center">0</span>
            </a>
            <a href="logout.php" class="bg-blue-950 hover:bg-black text-white text-sm rounded-lg px-3 py-2">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Cerrar sesión
            </a>
        </div>
    </header>

    <aside class="fixed top-16 left-0 bottom-0 w-64 bg-slate-900 text-slate-200 z-40 overflow-y-auto">
        <div class="relative px-4 py-6 border-b border-slate-700 text-center">
            <a href="perfil.php" title="Mi perfil" class="absolute top-3 right-3 text-gray-400 hover:text-white">
                <i class="fa-solid fa-gear"></i>
            </a>
            <?php if ($inicialesUsuario !== '') { ?>
                <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xl mx-auto shadow-md mb-2"><?php echo h($inicialesUsuario); ?></div>
            <?php } else { ?>
                <div class="w-12 h-12 rounded-full bg-blue-400 flex items-center justify-center text-white mx-auto shadow-md mb-2">
                    <i class="fa-solid fa-user text-2xl"></i>
                </div>
            <?php } ?>
            <div class="font-medium text-white"><?php echo h($nombre); ?></div>
            <div class="text-xs text-slate-400 mt-1">
                <?php echo $esAdmin ? 'Administrador' : h($codigo); ?>
            </div>
            <?php if (!$esAdmin) { ?>
                <?php if ($nombrePerfil !== '' && $apellidoPerfil !== '') { ?>
                    <p class="text-xs text-gray-400 mt-1 border-t border-gray-600 pt-1 mx-2">
                        Operador: <?php echo h($nombrePerfil . ' ' . $apellidoPerfil); ?>
                    </p>
                <?php } elseif ($emailOperador !== '') { ?>
                    <p class="text-xs text-gray-400 mt-1 border-t border-gray-600 pt-1 mx-2 break-all">
                        Operador: <?php echo h($emailOperador); ?>
                    </p>
                <?php } ?>
            <?php } ?>
        </div>
        <nav class="py-3">
            <?php foreach ($secciones as $clave => $item) {
                if (!empty($item['solo_admin'])) {
                    continue;
                }
                if (!empty($item['oculto'])) {
                    continue;
                }
                $clavePermiso = isset($item['permiso']) ? $item['permiso'] : $clave;
                $permisoActivo = isset($_SESSION['permisos'][$clavePermiso]) && (int) $_SESSION['permisos'][$clavePermiso] === 1;
                if (!(isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') && !$permisoActivo) {
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
                <details class="border-t border-slate-700" <?php echo ($seccion === 'admin_archivos' || $seccion === 'auditoria_vista' || $seccion === 'admin_permisos' || $seccion === 'conciliacion') ? 'open' : ''; ?>>
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
                    <a href="index.php?seccion=admin_permisos"
                       class="nav-portal flex items-center gap-3 pl-10 pr-4 py-2 text-sm hover:bg-slate-800 <?php echo $seccion === 'admin_permisos' ? 'bg-slate-800 text-white' : ''; ?>">
                        <i class="fa-solid fa-user-lock w-5 text-center"></i>
                        <span>Gestión de Permisos</span>
                    </a>
                    <a href="index.php?seccion=conciliacion"
                       class="nav-portal flex items-center gap-3 pl-10 pr-4 py-2 text-sm hover:bg-slate-800 <?php echo $seccion === 'conciliacion' ? 'bg-slate-800 text-white' : ''; ?>">
                        <i class="fa-solid fa-scale-balanced w-5 text-center"></i>
                        <span>Conciliador de Saldos</span>
                    </a>
                </details>
            <?php } ?>
        </nav>
    </aside>

    <div id="modal-notificacion" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
                <h2 id="titulo-notificacion" class="text-lg font-semibold text-slate-900">Notificación</h2>
                <button type="button" id="cerrar-notificacion" class="text-slate-500 hover:text-slate-800" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="contenido-notificacion" class="px-5 py-4 overflow-y-auto text-sm text-slate-800"></div>
            <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-200">
                <a id="descargar-notificacion" href="#" target="_blank" class="hidden rounded-lg px-4 py-2 text-sm bg-blue-800 hover:bg-blue-900 text-white">Descargar PDF</a>
                <button type="button" id="imprimir-notificacion" class="rounded-lg px-4 py-2 text-sm border border-slate-300 text-slate-700">Imprimir</button>
                <button type="button" id="cerrar-notificacion-pie" class="rounded-lg px-4 py-2 text-sm border border-slate-300 text-slate-700">Cerrar</button>
            </div>
        </div>
    </div>

    <main class="ml-64 mt-16 p-6 min-h-screen">
        <h1 class="text-2xl font-semibold text-slate-900 mb-4"><?php echo h($accesoDenegado ? 'Acceso denegado' : $secciones[$seccion]['titulo']); ?></h1>
        <?php if ($accesoDenegado) { ?>
            <div class="bg-white rounded-xl shadow p-8 text-center text-slate-600">No tiene permiso para ver esta sección.</div>
        <?php } else { ?>
            <?php
            if ($seccion === 'perfil') {
                mostrarFormularioPerfil();
            } else {
                require dirname(__FILE__) . '/modulos/' . $seccion . '.php';
            }
            ?>
        <?php } ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <?php if ($seccion === 'auditoria_vista' || $seccion === 'pagos' || $seccion === 'obras_sociales' || $seccion === 'autorizaciones' || $seccion === 'conciliacion') { ?>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <?php } ?>
    <script>
        function pintarBadgeNotificaciones(cantidad) {
            var badge = $('#badge-notificaciones');
            if (cantidad > 0) {
                badge.text(cantidad > 99 ? '99+' : String(cantidad));
                badge.removeClass('hidden').addClass('inline-flex');
            } else {
                badge.text('0');
                badge.addClass('hidden').removeClass('inline-flex');
            }
        }

        function guardarAvisoNotificaciones(detalle) {
            var modulo = 'notificaciones';
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
                data: { modulo: modulo, detalle: detalle }
            });
        }

        function consultarCampanita() {
            $.getJSON('ajax_campanita.php').done(function (respuesta) {
                var cantidad = respuesta && respuesta.cantidad ? parseInt(respuesta.cantidad, 10) : 0;
                if (isNaN(cantidad)) {
                    cantidad = 0;
                }
                pintarBadgeNotificaciones(cantidad);
                if (cantidad > 0 && !sessionStorage.getItem('campanita_bienvenida') && !window.campanitaAvisada && window.Swal) {
                    window.campanitaAvisada = true;
                    sessionStorage.setItem('campanita_bienvenida', '1');
                    Swal.fire({
                        icon: 'info',
                        title: 'Tienes ' + cantidad + ' notificaciones nuevas',
                        showCancelButton: true,
                        confirmButtonText: 'Ir a Notificaciones',
                        cancelButtonText: 'Cerrar',
                        confirmButtonColor: '#1e40af'
                    }).then(function (resultado) {
                        if (resultado && resultado.isConfirmed) {
                            guardarAvisoNotificaciones('Vio las notificaciones nuevas');
                            window.location.href = 'notificaciones.php';
                            return;
                        }
                        guardarAvisoNotificaciones('Cerró el aviso de notificaciones nuevas');
                    });
                }
            });
        }

        function abrirNotificacion(id) {
            $.ajax({
                url: 'ajax_leer_notificacion.php',
                type: 'POST',
                dataType: 'json',
                data: { id_notificacion: id }
            }).done(function (respuesta) {
                if (!respuesta || !respuesta.ok) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No se pudo abrir',
                            text: (respuesta && respuesta.mensaje) ? respuesta.mensaje : ''
                        });
                    }
                    return;
                }
                $('#titulo-notificacion').text(respuesta.asunto || 'Notificación');
                $('#contenido-notificacion').html(respuesta.cuerpo_html || '');
                if (respuesta.archivo_adjunto) {
                    $('#descargar-notificacion')
                        .attr('href', 'descargar_notificacion.php?id=' + encodeURIComponent(id))
                        .removeClass('hidden');
                } else {
                    $('#descargar-notificacion').addClass('hidden').attr('href', '#');
                }
                $('#modal-notificacion').removeClass('hidden').addClass('flex');
                consultarCampanita();
                if ($.fn.dataTable && $.fn.dataTable.isDataTable('#tablaNotificaciones')) {
                    $('#tablaNotificaciones').DataTable().ajax.reload(null, false);
                }
            });
        }

        function columnaImporte(campo) {
            return {
                data: campo,
                className: 'num text-right whitespace-nowrap',
                render: function (dato, tipo) {
                    if (tipo === 'sort' || tipo === 'type') {
                        var limpio = String(dato == null ? '' : dato).replace(/\$/g, '').replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
                        var numero = parseFloat(limpio);
                        return isNaN(numero) ? 0 : numero;
                    }
                    return dato;
                }
            };
        }
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
            if ($('#tablaObrasSociales').length && $.fn.dataTable) {
                var idiomaObras = $.extend({}, idiomaTabla, { emptyTable: 'No hay obras sociales para mostrar' });
                $('#tablaObrasSociales').DataTable({
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
                    order: [[0, 'asc']],
                    dom: 'lBfrtip',
                    buttons: [
                        { extend: 'excel', text: 'Excel' },
                        { extend: 'print', text: 'Imprimir' }
                    ],
                    language: idiomaObras
                });
            }
            if ($('#tabla-datos').length) {
                $('#tabla-datos').DataTable({
                    pageLength: 25,
                    order: [],
                    language: idiomaTabla
                });
            }
            if ($('#tablaPagos').length && $.fn.dataTable) {
                function enlacePdfPago(archivo, etiqueta) {
                    if (!archivo) {
                        return '';
                    }
                    var url = 'descargar_pago.php?archivo=' + encodeURIComponent(archivo);
                    return '<a href="' + url + '" target="_blank" class="text-xs text-red-600 hover:underline"><i class="fa-solid fa-file-pdf"></i> ' + etiqueta + '</a>';
                }
                var tabla = $('#tablaPagos').DataTable({
                    ajax: 'ajax_pagos.php',
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todos']],
                    order: [[0, 'desc']],
                    deferRender: true,
                    scrollX: true,
                    processing: true,
                    dom: 'lfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<i class="fa-solid fa-file-excel"></i>',
                            titleAttr: 'Exportar a Excel',
                            exportOptions: { orthogonal: 'export' }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fa-solid fa-file-pdf"></i>',
                            titleAttr: 'Exportar a PDF',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            exportOptions: { orthogonal: 'export' }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fa-solid fa-print"></i>',
                            titleAttr: 'Imprimir',
                            exportOptions: { orthogonal: 'export' }
                        }
                    ],
                    columns: [
                        { data: 'periodo' },
                        {
                            data: 'prestador',
                            render: function (dato, tipo, fila) {
                                var codigo = dato == null ? '' : String(dato);
                                var nombre = fila.prestador_nombre ? String(fila.prestador_nombre) : '';
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return nombre !== '' ? codigo + ' ' + nombre : codigo;
                                }
                                if (nombre === '') {
                                    return codigo;
                                }
                                var codigoHtml = codigo.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                var nombreHtml = nombre.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                return '<div class="flex flex-col justify-center items-start">'
                                    + '<span class="font-medium text-gray-900">' + codigoHtml + '</span>'
                                    + '<span class="text-xs text-gray-500 whitespace-normal leading-tight" style="max-width: 150px;">' + nombreHtml + '</span>'
                                    + '</div>';
                            }
                        },
                        { data: 'obrasocial' },
                        {
                            data: null,
                            className: 'nowrap',
                            render: function (dato, tipo, fila) {
                                return (fila.suc || '') + '-' + (fila.factura || '');
                            }
                        },
                        columnaImporte('facturado'),
                        columnaImporte('importe'),
                        columnaImporte('coseguro'),
                        columnaImporte('debitado'),
                        columnaImporte('liquidado'),
                        columnaImporte('pagado'),
                        columnaImporte('saldo'),
                        { data: 'recibo', className: 'text-center nowrap' },
                        {
                            data: 'retencion',
                            className: 'text-center nowrap',
                            render: function (dato, tipo, fila) {
                                var texto = dato == null || dato === '' ? '-' : String(dato);
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return texto;
                                }
                                if (!fila.retencion_pdf || texto === '-') {
                                    return texto;
                                }
                                var url = '../uploads/pdftango/' + encodeURIComponent(fila.retencion_pdf);
                                return '<div class="text-center"><a href="' + url + '" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold" title="Descargar Certificado de Retención"><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>' + texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</a></div>';
                            }
                        },
                        {
                            data: 'orden',
                            className: 'nowrap text-center',
                            render: function (dato, tipo, fila) {
                                var numero = dato == null ? '' : String(dato);
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return numero;
                                }
                                if (!fila.tango) {
                                    return numero;
                                }
                                var url = '../uploads/pdftango/' + encodeURIComponent(fila.tango);
                                return '<div class="whitespace-nowrap text-center"><a href="' + url + '" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold" title="Descargar Comprobante de Pago"><i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>' + numero.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</a></div>';
                            }
                        },
                        { data: 'fecha', className: 'nowrap' },
                        {
                            data: null,
                            className: 'text-center',
                            orderable: false,
                            render: function (dato, tipo, fila) {
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    var nombres = [];
                                    if (fila.deb) {
                                        nombres.push(fila.deb);
                                    }
                                    if (fila.csn) {
                                        nombres.push(fila.csn);
                                    }
                                    return nombres.join(' ');
                                }
                                var debito = enlacePdfPago(fila.deb, 'Débito');
                                var csn = enlacePdfPago(fila.csn, 'CSN');
                                if (!debito && !csn) {
                                    return '-';
                                }
                                return '<div class="flex flex-col gap-1 items-center">' + debito + csn + '</div>';
                            }
                        },
                        {
                            data: 'notificaciones',
                            className: 'text-center nowrap',
                            orderable: false,
                            render: function (dato, tipo, fila) {
                                if (tipo === 'export' || tipo === 'filter' || tipo === 'sort') {
                                    return fila.notificaciones_txt || '';
                                }
                                return dato || '';
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
                new $.fn.dataTable.Buttons(tabla, { buttons: tabla.init().buttons });
                tabla.buttons().container().appendTo('#contenedorExportacionLiq');
                var columnaFiltroLiq = parseInt($('#campoFiltroLiq').val(), 10);
                function aplicarFiltroLiq() {
                    var grilla = $('#tablaPagos').DataTable();
                    var columna = parseInt($('#campoFiltroLiq').val(), 10);
                    var valor = $('#valorFiltroLiq').val();
                    if (columnaFiltroLiq !== columna) {
                        grilla.column(columnaFiltroLiq).search('');
                        columnaFiltroLiq = columna;
                    }
                    grilla.column(columna).search(valor).draw();
                }
                $('#valorFiltroLiq').on('keyup change', aplicarFiltroLiq);
                $('#campoFiltroLiq').on('change', aplicarFiltroLiq);
                $('#btnLimpiarFiltroLiq').on('click', function () {
                    $('#valorFiltroLiq').val('');
                    aplicarFiltroLiq();
                });
                $('#controlTamanoLetra').on('change', function () {
                    $('#tablaPagos').removeClass('texto-grilla-xs text-sm text-base').addClass(this.value);
                });
            }
            if ($('#tablaNotificaciones').length && $.fn.dataTable) {
                $('#tablaNotificaciones').DataTable({
                    ajax: 'ajax_listar_notificaciones.php',
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todas']],
                    order: [[0, 'desc']],
                    processing: true,
                    columns: [
                        {
                            data: 'fecha',
                            render: function (dato, tipo, fila) {
                                if (tipo === 'sort' || tipo === 'type') {
                                    return fila.fecha_orden || '';
                                }
                                return dato;
                            }
                        },
                        { data: 'razon_social' },
                        { data: 'comprobante' },
                        { data: 'obra_social' },
                        {
                            data: 'asunto',
                            render: function (dato, tipo, fila) {
                                var texto = dato || '';
                                if (tipo === 'display' && !fila.leida) {
                                    return '<span class="font-semibold">' + $('<div>').text(texto).html() + '</span>';
                                }
                                return texto;
                            }
                        },
                        { data: 'tipo' },
                        {
                            data: 'id',
                            orderable: false,
                            searchable: false,
                            className: 'text-center',
                            render: function (dato) {
                                var id = parseInt(dato, 10) || 0;
                                return '<button type="button" class="text-blue-800 hover:text-blue-950" title="Abrir correo" onclick="abrirNotificacion(' + id + ')"><i class="fa-solid fa-envelope"></i></button>';
                            }
                        },
                        { data: 'remitente', visible: false, searchable: true },
                        { data: 'destinatarios', visible: false, searchable: true },
                        { data: 'correos_prestador', visible: false, searchable: true }
                    ],
                    language: idiomaTabla
                });
            }
            if ($('#tablaPermisos').length && $.fn.dataTable) {
                var idiomaPermisos = $.extend({}, idiomaTabla, { emptyTable: 'No hay permisos para mostrar' });
                var tablaPermisos = $('#tablaPermisos').DataTable({
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
                    order: [[0, 'asc']],
                    columnDefs: [{ orderable: false, targets: -1 }],
                    language: idiomaPermisos
                });
                var camposPermiso = [
                    'ver_autorizaciones',
                    'ver_obras_sociales',
                    'ver_coseguros',
                    'ver_normativas',
                    'ver_contratos',
                    'ver_pagos',
                    'ver_empadronamiento'
                ];

                function htmlPermiso(valor) {
                    if (parseInt(valor, 10) === 1) {
                        return '<span class="text-green-600 text-lg" title="Sí"><i class="fa-solid fa-check"></i></span>';
                    }
                    return '<span class="text-red-600 text-lg" title="No"><i class="fa-solid fa-xmark"></i></span>';
                }

                function cerrarModalPermisos() {
                    $('#modal-permisos').addClass('hidden').removeClass('flex');
                    $('#permisos-error').addClass('hidden').text('');
                }

                $('#tablaPermisos').on('click', '.btn-editar-permiso', function () {
                    var boton = $(this);
                    $('#permiso-id').val(boton.attr('data-id'));
                    $('#permiso-tipo').text(boton.attr('data-etiqueta'));
                    var i;
                    for (i = 0; i < camposPermiso.length; i++) {
                        var campo = camposPermiso[i];
                        $('#form-permisos input[name="' + campo + '"]').prop('checked', boton.attr('data-' + campo) === '1');
                    }
                    $('#permisos-error').addClass('hidden').text('');
                    $('#modal-permisos').removeClass('hidden').addClass('flex');
                });

                $('#cerrar-modal-permisos, #cancelar-modal-permisos').on('click', cerrarModalPermisos);

                $('#form-permisos').on('submit', function (evento) {
                    evento.preventDefault();
                    var datos = { id: $('#permiso-id').val() };
                    var i;
                    for (i = 0; i < camposPermiso.length; i++) {
                        var campo = camposPermiso[i];
                        datos[campo] = $('#form-permisos input[name="' + campo + '"]').is(':checked') ? 1 : 0;
                    }
                    $('#guardar-permisos').prop('disabled', true);
                    $.ajax({
                        url: 'ajax_permisos.php',
                        type: 'POST',
                        dataType: 'json',
                        data: datos
                    }).done(function (respuesta) {
                        if (!respuesta || !respuesta.ok) {
                            $('#permisos-error').removeClass('hidden').text((respuesta && respuesta.error) ? respuesta.error : 'No se pudo guardar.');
                            return;
                        }
                        var boton = $('#tablaPermisos .btn-editar-permiso[data-id="' + respuesta.id + '"]');
                        var fila = boton.closest('tr');
                        var j;
                        for (j = 0; j < camposPermiso.length; j++) {
                            var campo = camposPermiso[j];
                            var valor = respuesta.permisos[campo] ? 1 : 0;
                            boton.attr('data-' + campo, valor);
                            var celda = fila.find('td[data-campo="' + campo + '"]');
                            celda.html(htmlPermiso(valor));
                            if (celda.length) {
                                tablaPermisos.cell(celda.get(0)).invalidate();
                            }
                        }
                        cerrarModalPermisos();
                    }).fail(function () {
                        $('#permisos-error').removeClass('hidden').text('No se pudo guardar.');
                    }).always(function () {
                        $('#guardar-permisos').prop('disabled', false);
                    });
                });
            }
            if ($('#tablaAutorizaciones').length && $.fn.dataTable) {
                var idiomaAutorizaciones = $.extend({}, idiomaTabla, {
                    emptyTable: 'No hay autorizaciones en los últimos 90 días',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ órdenes de los últimos 90 días',
                    infoEmpty: 'Sin órdenes en los últimos 90 días',
                    infoFiltered: '(filtrado de _MAX_ órdenes de los últimos 90 días)'
                });
                var tablaAutorizaciones = $('#tablaAutorizaciones').DataTable({
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, 'Todas']],
                    order: [[1, 'desc']],
                    autoWidth: false,
                    columnDefs: [
                        { targets: 0, width: '120px' },
                        { targets: 1, width: '120px' },
                        { targets: 2, width: '220px' },
                        { targets: 3, width: '140px' },
                        { targets: 4, width: '150px', orderable: false }
                    ],
                    dom: "<'controles-autorizaciones'B<'controles-medio'lf>r>tip",
                    buttons: [
                        { extend: 'excel', text: 'Excel' },
                        { extend: 'pdf', text: 'PDF', orientation: 'landscape', pageSize: 'A4' },
                        { extend: 'print', text: 'Imprimir' }
                    ],
                    language: idiomaAutorizaciones
                });
                var medioAutorizaciones = $('#tablaAutorizaciones_wrapper .controles-medio');
                medioAutorizaciones.prepend($('#tablaAutorizaciones_length'));
                $('#tablaAutorizaciones_filter').before($('#grupo-estado-autorizaciones'));
                $('#filtroEstadoAutorizaciones').on('change', function () {
                    tablaAutorizaciones.column(3).search($(this).val(), false, false).draw();
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

            var menuActual = <?php echo json_encode($accesoDenegado ? 'Acceso denegado' : $secciones[$seccion]['titulo']); ?>;

            $('aside').on('click', 'a.nav-portal', function () {
                registrarAuditoria('menu', $.trim($(this).text()));
            });

            $(document).on('click', 'a[target="_blank"], a[href*="descargar.php"], a[href*="descargar_pago.php"]', function () {
                var href = $(this).attr('href') || '';
                var detalle = href;
                var consulta = href.split('?')[1] || '';
                var partes = consulta.split('&');
                var i;
                for (i = 0; i < partes.length; i++) {
                    if (partes[i].indexOf('archivo=') === 0) {
                        detalle = decodeURIComponent(partes[i].substring(8).replace(/\+/g, ' '));
                        break;
                    }
                }
                registrarAuditoria(menuActual, detalle);
            });

            function cerrarModalNotificacion() {
                $('#modal-notificacion').addClass('hidden').removeClass('flex');
            }
            $('#cerrar-notificacion, #cerrar-notificacion-pie').on('click', cerrarModalNotificacion);
            $('#imprimir-notificacion').on('click', function () {
                window.print();
            });
            consultarCampanita();
            setInterval(consultarCampanita, 300000);
        });
    </script>
<!-- Reloj Flotante Inferior Derecho -->
    <div id="reloj-flotante" class="fixed bottom-0 right-0 bg-blue-800 text-white px-4 py-1.5 rounded-tl-lg text-sm font-semibold shadow-lg z-50">
        <i class="fa-regular fa-clock mr-1"></i> <span id="reloj-texto">Cargando hora...</span>
    </div>

    <script>
        function actualizarReloj() {
            var ahora = new Date();
            function dosDigitos(numero) {
                return (numero < 10 ? '0' : '') + numero;
            }
            var fecha = dosDigitos(ahora.getDate()) + '/' + dosDigitos(ahora.getMonth() + 1) + '/' + ahora.getFullYear();
            var hora = dosDigitos(ahora.getHours()) + ':' + dosDigitos(ahora.getMinutes()) + ':' + dosDigitos(ahora.getSeconds());
            document.getElementById('reloj-texto').innerHTML = hora + ' | ' + fecha;
        }
        
        // Actualizar cada segundo
        setInterval(actualizarReloj, 1000);
        actualizarReloj(); // Ejecutar al instante
    </script>
</body>
</html>
