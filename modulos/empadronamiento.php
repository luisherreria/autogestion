<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=empadronamiento');
    exit;
}

$institucion = isset($_SESSION['nombre']) ? trim($_SESSION['nombre']) : '';
$telefonoSesion = isset($_SESSION['telefono']) ? trim($_SESSION['telefono']) : '';
?>
<div class="max-w-4xl mx-auto">
    <div id="aviso-empadronamiento" class="hidden mb-4 rounded-lg px-4 py-3 text-sm"></div>

    <form id="form-buscar-padron" class="max-w-2xl mx-auto bg-white shadow-lg rounded-lg p-6" autocomplete="off">
        <label for="busqueda-padron" class="block text-sm font-medium text-slate-700 mb-2">DNI, carnet o ITROM</label>
        <input type="text" id="busqueda-padron" name="busqueda" maxlength="30" required
               class="w-full text-lg rounded-lg border border-slate-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-600"
               placeholder="Ingrese DNI, carnet o ITROM">
        <button type="submit" id="btn-verificar-padron"
                class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg py-3">
            <i class="fa-solid fa-user-check mr-2"></i> Verificar Empadronamiento
        </button>
    </form>

    <div id="resultado-padron" class="mt-4"></div>
</div>
<script>
window.datosEmpadronamiento = {
    institucion: <?php echo json_encode($institucion); ?>,
    telefono: <?php echo json_encode($telefonoSesion); ?>
};
</script>
<script>
(function esperarJqueryEmpadronamiento() {
    if (!window.jQuery) {
        setTimeout(esperarJqueryEmpadronamiento, 30);
        return;
    }
    jQuery(function ($) {
        var institucionBase = window.datosEmpadronamiento.institucion || '';
        var telefonoBase = window.datosEmpadronamiento.telefono || '';

        function escapar(texto) {
            return $('<div>').text(texto == null ? '' : texto).html();
        }

        function iniciarProgreso(boton) {
            var barra = boton.find('.barra-progreso');
            var avance = 0;
            boton.prop('disabled', true);
            var timer = setInterval(function () {
                var paso = Math.random() * 7 + 3;
                avance = Math.min(90, avance + paso);
                barra.css('width', avance + '%');
            }, 250);
            return function (completo) {
                clearInterval(timer);
                if (completo) {
                    barra.css('width', '100%');
                    return;
                }
                barra.css('width', '0%');
                boton.prop('disabled', false);
            };
        }

        function aviso(tipo, texto) {
            var caja = $('#aviso-empadronamiento');
            caja.removeClass('hidden bg-emerald-50 text-emerald-800 bg-red-50 text-red-700 bg-amber-50 text-amber-800');
            if (tipo === 'ok') {
                caja.addClass('bg-emerald-50 text-emerald-800');
            } else if (tipo === 'alerta') {
                caja.addClass('bg-amber-50 text-amber-800');
            } else {
                caja.addClass('bg-red-50 text-red-700');
            }
            caja.text(texto);
        }

        function camposContacto(botonTexto, claseBoton, conInternacion) {
            var html = '';
            html += '<label class="block text-sm font-medium text-slate-700 mb-1">Institución</label>';
            html += '<input type="text" name="institucion" readonly value="' + escapar(institucionBase) + '" class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 mb-4">';
            html += '<label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>';
            html += '<input type="text" name="telefono" value="' + escapar(telefonoBase) + '" class="w-full rounded-lg border border-slate-300 px-3 py-2 mb-4">';
            if (conInternacion) {
                html += '<fieldset class="mb-4">';
                html += '<legend class="text-sm font-medium text-slate-700 mb-2">¿Se trata de una internación?</legend>';
                html += '<div class="flex items-center gap-6">';
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="internacion" value="si" required> Sí</label>';
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="internacion" value="no"> No</label>';
                html += '</div></fieldset>';
            }
            html += '<button type="submit" class="relative overflow-hidden w-full ' + claseBoton + ' text-white font-medium rounded-lg py-3">';
            html += '<span class="barra-progreso absolute inset-y-0 left-0 bg-blue-950" style="width:0%;transition:width .25s linear"></span>';
            html += '<span class="relative z-10">' + botonTexto + '</span></button>';
            return html;
        }

        function pintarActivo(datos) {
            var afiliado = datos.afiliado || {};
            if (datos.telefono) {
                telefonoBase = datos.telefono;
            }
            if (datos.institucion) {
                institucionBase = datos.institucion;
            }
            var html = '<div class="bg-white shadow-lg rounded-lg p-6">';
            html += '<div class="rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3 mb-4 text-sm">Afiliado activo en el padrón.</div>';
            html += '<dl class="grid grid-cols-1 gap-2 mb-6 text-sm">';
            html += '<div><dt class="text-slate-500">Nombre completo</dt><dd class="font-medium text-slate-900">' + escapar(afiliado.nombre) + '</dd></div>';
            html += '<div><dt class="text-slate-500">DNI</dt><dd class="font-medium text-slate-900">' + escapar(afiliado.dni) + '</dd></div>';
            html += '<div><dt class="text-slate-500">Nro carnet</dt><dd class="font-medium text-slate-900">' + escapar(afiliado.carnet) + '</dd></div>';
            html += '<div><dt class="text-slate-500">Obra social</dt><dd class="font-medium text-slate-900">' + escapar(afiliado.obra_social) + '</dd></div>';
            html += '<div><dt class="text-slate-500">Plan</dt><dd class="font-medium text-slate-900">' + escapar(afiliado.plan) + '</dd></div>';
            html += '</dl>';
            html += '<form id="form-enviar-padron" class="border-t border-slate-200 pt-4">';
            html += '<input type="hidden" name="estado_padron" value="activo">';
            html += '<input type="hidden" name="busqueda" value="' + escapar($('#busqueda-padron').val()) + '">';
            html += '<input type="hidden" name="nombre" value="' + escapar(afiliado.nombre) + '">';
            html += '<input type="hidden" name="dni" value="' + escapar(afiliado.dni) + '">';
            html += '<input type="hidden" name="carnet" value="' + escapar(afiliado.carnet) + '">';
            html += '<input type="hidden" name="obra_social" value="' + escapar(afiliado.obra_social) + '">';
            html += '<input type="hidden" name="plan" value="' + escapar(afiliado.plan) + '">';
            html += camposContacto('Enviar Verificación de Empadronamiento', 'bg-blue-800 hover:bg-blue-900', true);
            html += '</form></div>';
            $('#resultado-padron').html(html);
        }

        function pintarFuera(datos) {
            if (datos.telefono) {
                telefonoBase = datos.telefono;
            }
            if (datos.institucion) {
                institucionBase = datos.institucion;
            }
            var busqueda = datos.busqueda || $('#busqueda-padron').val();
            $('#form-buscar-padron').addClass('hidden');
            var html = '<section class="bg-white shadow-lg rounded-lg overflow-hidden">';
            html += '<div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-slate-200">';
            html += '<h2 class="text-xl font-semibold text-slate-900">Búsqueda de Afiliado</h2>';
            html += '<button type="button" id="volver-padron" class="bg-blue-800 hover:bg-blue-900 text-white text-sm rounded-lg px-4 py-2">Volver atrás</button>';
            html += '</div>';
            html += '<div class="px-6 py-10 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">';
            html += '<p class="text-blue-800 font-semibold">Estado de Afiliación:</p>';
            html += '<p class="text-slate-600 md:text-center">El afiliado no se encuentra en nuestro padrón.</p>';
            html += '</div></section>';
            html += '<section class="mt-4 bg-indigo-50 rounded-lg p-6 text-slate-800">';
            html += '<h3 class="font-semibold text-lg mb-3">Contáctenos</h3>';
            html += '<p class="mb-1">Email: <a class="text-blue-800 hover:underline" href="mailto:autorizaciones@comedica.com.ar">autorizaciones@comedica.com.ar</a></p>';
            html += '<p>Tel: 6009-1255</p>';
            html += '</section>';
            html += '<p id="aviso-fuera-auto" class="mt-4 text-sm text-slate-500">Enviando notificación de fuera de padrón...</p>';
            html += '<button type="button" id="abrir-consulta" class="mt-4 bg-blue-800 hover:bg-blue-900 text-white rounded-lg px-4 py-2">Escribir consulta</button>';
            html += '<form id="form-consulta-padron" class="hidden mt-4 bg-white shadow-lg rounded-lg p-6">';
            html += '<input type="hidden" name="estado_padron" value="consulta">';
            html += '<input type="hidden" name="busqueda" value="' + escapar(busqueda) + '">';
            html += '<input type="hidden" name="institucion" value="' + escapar(institucionBase) + '">';
            html += '<input type="hidden" name="telefono" value="' + escapar(telefonoBase) + '">';
            html += '<label for="consulta-padron" class="block text-sm font-medium text-slate-700 mb-2">Consulta</label>';
            html += '<textarea id="consulta-padron" name="consulta" rows="4" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Escriba su consulta"></textarea>';
            html += '<button type="submit" class="relative overflow-hidden mt-4 bg-blue-800 hover:bg-blue-900 text-white rounded-lg px-4 py-2">';
            html += '<span class="barra-progreso absolute inset-y-0 left-0 bg-blue-950" style="width:0%;transition:width .25s linear"></span>';
            html += '<span class="relative z-10">Enviar consulta</span></button>';
            html += '</form>';
            $('#resultado-padron').html(html);
            $.ajax({
                url: 'ajax_enviar_empadronamiento.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    estado_padron: 'fuera_padron',
                    busqueda: busqueda,
                    institucion: institucionBase,
                    telefono: telefonoBase
                }
            }).done(function (respuesta) {
                if (respuesta && respuesta.ok) {
                    $('#aviso-fuera-auto').attr('class', 'mt-4 text-sm text-emerald-700').text('Se envió la notificación de fuera de padrón.');
                    return;
                }
                $('#aviso-fuera-auto').attr('class', 'mt-4 text-sm text-red-700').text((respuesta && respuesta.mensaje) ? respuesta.mensaje : 'No se pudo enviar la notificación de fuera de padrón.');
            }).fail(function () {
                $('#aviso-fuera-auto').attr('class', 'mt-4 text-sm text-red-700').text('No se pudo enviar la notificación de fuera de padrón.');
            });
        }

        $('#form-buscar-padron').on('submit', function (evento) {
            evento.preventDefault();
            var termino = $.trim($('#busqueda-padron').val());
            if (termino === '') {
                aviso('error', 'Ingrese un DNI, carnet o ITROM.');
                return;
            }
            $('#aviso-empadronamiento').addClass('hidden');
            $('#btn-verificar-padron').prop('disabled', true);
            $('#resultado-padron').html('<p class="text-sm text-slate-500 text-center">Buscando...</p>');
            $.ajax({
                url: 'ajax_validar_padron.php',
                type: 'POST',
                dataType: 'json',
                data: { busqueda: termino }
            }).done(function (respuesta) {
                if (!respuesta || respuesta.status === 'error') {
                    $('#resultado-padron').empty();
                    aviso('error', (respuesta && respuesta.mensaje) ? respuesta.mensaje : 'No se pudo consultar el padrón.');
                    return;
                }
                if (respuesta.status === 'activo') {
                    pintarActivo(respuesta);
                    return;
                }
                pintarFuera(respuesta);
            }).fail(function () {
                $('#resultado-padron').empty();
                aviso('error', 'No se pudo consultar el padrón.');
            }).always(function () {
                $('#btn-verificar-padron').prop('disabled', false);
            });
        });

        $('#resultado-padron').on('click', '#abrir-consulta', function () {
            $('#form-consulta-padron').removeClass('hidden');
            $('#consulta-padron').focus();
        });

        $('#resultado-padron').on('click', '#volver-padron', function () {
            $('#resultado-padron').empty();
            $('#form-buscar-padron').removeClass('hidden');
            $('#aviso-empadronamiento').addClass('hidden');
        });

        $('#resultado-padron').on('submit', '#form-consulta-padron', function (evento) {
            evento.preventDefault();
            var formulario = $(this);
            if ($.trim(formulario.find('textarea[name="consulta"]').val()) === '') {
                aviso('alerta', 'Escriba la consulta.');
                return;
            }
            var boton = formulario.find('button[type="submit"]');
            var finProgreso = iniciarProgreso(boton);
            $.ajax({
                url: 'ajax_enviar_empadronamiento.php',
                type: 'POST',
                dataType: 'json',
                data: formulario.serialize()
            }).done(function (respuesta) {
                if (!respuesta || !respuesta.ok) {
                    finProgreso(false);
                    aviso('error', (respuesta && respuesta.mensaje) ? respuesta.mensaje : 'No se pudo enviar la consulta.');
                    return;
                }
                finProgreso(true);
                formulario.find('textarea[name="consulta"]').val('');
                aviso('ok', 'Correo enviado exitosamente.');
                setTimeout(function () {
                    boton.find('.barra-progreso').css('width', '0%');
                    boton.prop('disabled', false);
                }, 400);
            }).fail(function () {
                finProgreso(false);
                aviso('error', 'No se pudo enviar la consulta.');
            });
        });

        $('#resultado-padron').on('submit', '#form-enviar-padron', function (evento) {
            evento.preventDefault();
            var formulario = $(this);
            var estado = formulario.find('input[name="estado_padron"]').val();
            if (estado === 'activo' && formulario.find('input[name="internacion"]:checked').length === 0) {
                aviso('alerta', 'Indique si se trata de una internación.');
                return;
            }
            if ($.trim(formulario.find('input[name="telefono"]').val()) === '') {
                aviso('alerta', 'Ingrese un teléfono de contacto.');
                return;
            }
            var boton = formulario.find('button[type="submit"]');
            var finProgreso = iniciarProgreso(boton);
            $.ajax({
                url: 'ajax_enviar_empadronamiento.php',
                type: 'POST',
                dataType: 'json',
                data: formulario.serialize()
            }).done(function (respuesta) {
                if (!respuesta || !respuesta.ok) {
                    finProgreso(false);
                    aviso('error', (respuesta && respuesta.mensaje) ? respuesta.mensaje : 'No se pudo enviar el correo.');
                    return;
                }
                finProgreso(true);
                setTimeout(function () {
                    $('#form-buscar-padron')[0].reset();
                    $('#resultado-padron').empty();
                    aviso('ok', 'Correo enviado exitosamente.');
                }, 350);
            }).fail(function () {
                finProgreso(false);
                aviso('error', 'No se pudo enviar el correo.');
            });
        });
    });
})();
</script>
