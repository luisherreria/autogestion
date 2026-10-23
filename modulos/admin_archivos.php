<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=admin_archivos');
    exit;
}

if (!esUsuarioLuis()) {
    echo '<div class="max-w-4xl mx-auto rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}

$categorias = array(
    'coseguros' => 'Coseguros y APB',
    'normativas' => 'Normativas',
    'contratos' => 'Contratos',
);
$alcances = array(
    'general' => 'General (Todos)',
    'prestador' => 'Por prestador específico',
    'obra_social' => 'Por obra social específica',
);

$config = appConfig();
$mensaje = '';
$tipoMensaje = '';
$filas = array();

if (isset($_SESSION['flash_archivos']) && is_array($_SESSION['flash_archivos'])) {
    $mensaje = isset($_SESSION['flash_archivos']['texto']) ? $_SESSION['flash_archivos']['texto'] : '';
    $tipoMensaje = isset($_SESSION['flash_archivos']['tipo']) ? $_SESSION['flash_archivos']['tipo'] : '';
    unset($_SESSION['flash_archivos']);
}

try {
    $pdo = Database::getConnection();
    asegurarTablaArchivos($pdo);
    $stmt = $pdo->query(
        'SELECT id, nombre_archivo, ruta, categoria, alcance, codigo_asociado, fecha
         FROM archivos_subidos
         ORDER BY fecha DESC, id DESC'
    );
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    if ($mensaje === '') {
        $mensaje = 'No se pudo leer el registro de archivos. ' . $e->getMessage();
        $tipoMensaje = 'error';
    }
}
?>
<div class="max-w-4xl mx-auto">
    <?php if ($mensaje !== '') { ?>
        <div class="rounded-lg px-4 py-3 mb-4 text-sm <?php echo $tipoMensaje === 'ok' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'; ?>">
            <?php echo h($mensaje); ?>
        </div>
    <?php } ?>

    <form id="form-subida-archivos" method="post" action="index.php?seccion=admin_archivos" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 space-y-4">
        <div>
            <label for="categoria" class="block text-sm font-medium text-slate-700 mb-1">Carpeta / categoría</label>
            <select name="categoria" id="categoria" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <?php foreach ($categorias as $clave => $etiqueta) { ?>
                    <option value="<?php echo h($clave); ?>"><?php echo h($etiqueta); ?></option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label for="alcance" class="block text-sm font-medium text-slate-700 mb-1">Alcance</label>
            <select name="alcance" id="alcance" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <?php foreach ($alcances as $clave => $etiqueta) { ?>
                    <option value="<?php echo h($clave); ?>"><?php echo h($etiqueta); ?></option>
                <?php } ?>
            </select>
        </div>
        <div id="bloque-codigo">
            <label for="codigo-visible" id="etiqueta-codigo" class="block text-sm font-medium text-slate-700 mb-1">Código</label>
            <input type="text" id="codigo-visible" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Código o parte del nombre">
            <input type="hidden" name="codigo" id="codigo" value="">
            <ul id="lista-prestadores" class="hidden mt-1 border border-slate-200 rounded-lg bg-white max-h-56 overflow-y-auto text-sm"></ul>
            <p id="aviso-prestador" class="mt-1 text-xs text-slate-500"></p>
        </div>
        <div>
            <span class="block text-sm font-medium text-slate-700 mb-1">Archivo</span>
            <label id="zona-archivo" class="border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 p-6 text-center hover:border-blue-500 hover:bg-blue-50 cursor-pointer block">
                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400"></i>
                <p class="mt-2 text-sm text-slate-600">Arrastre un archivo aquí o haga clic para seleccionarlo</p>
                <p id="nombre-archivo" class="mt-2 text-sm font-medium text-blue-800"></p>
                <p class="mt-1 text-xs text-slate-500">PDF, Excel, Word, PNG o JPG.</p>
                <input type="file" name="archivo" id="archivo" accept=".pdf,.xls,.xlsx,.doc,.docx,.png,.jpg,.jpeg,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/png,image/jpeg" class="hidden">
            </label>
        </div>
        <button type="submit" class="bg-blue-800 hover:bg-blue-900 text-white rounded-lg px-4 py-2">
            <i class="fa-solid fa-upload mr-1"></i> Subir archivo
        </button>
    </form>

    <div class="bg-white rounded-xl shadow overflow-x-auto p-4 mt-6">
        <h2 class="text-lg font-medium mb-3">Últimos archivos registrados</h2>
        <table id="tablaArchivos" class="display w-full text-sm">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Archivo</th>
                    <th>Categoría</th>
                    <th>Alcance</th>
                    <th>Código</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila) {
                    $categoria = isset($fila['categoria']) ? trim((string) $fila['categoria']) : '';
                    $rutaGuardada = str_replace('\\', '/', (string) $fila['ruta']);
                    $prefijo = 'archivos/' . $categoria . '/';
                    $relativo = '';
                    if ($categoria !== '' && strpos($rutaGuardada, $prefijo) === 0) {
                        $relativo = substr($rutaGuardada, strlen($prefijo));
                    }
                    $enlace = ($relativo !== '' && isset($categorias[$categoria])) ? urlDescarga($categoria, $relativo, true) : '';
                    ?>
                    <tr>
                        <td data-order="<?php echo h($fila['fecha']); ?>"><?php echo h(formatearFecha($fila['fecha'])); ?></td>
                        <td><?php echo h($fila['nombre_archivo']); ?></td>
                        <td><?php echo h(isset($categorias[$categoria]) ? $categorias[$categoria] : $categoria); ?></td>
                        <td><?php echo h(isset($alcances[$fila['alcance']]) ? $alcances[$fila['alcance']] : $fila['alcance']); ?></td>
                        <td><?php echo h($fila['codigo_asociado']); ?></td>
                        <td class="text-right">
                            <?php if ($enlace !== '') { ?>
                                <a href="<?php echo h($enlace); ?>" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 mr-3" title="Abrir archivo">
                                    <i class="fa-solid fa-up-right-from-square"></i>
                                </a>
                            <?php } ?>
                            <form method="post" action="index.php?seccion=admin_archivos" class="inline" onsubmit="return confirmarBorrarArchivo(this);">
                                <input type="hidden" name="accion" value="borrar">
                                <input type="hidden" name="id" value="<?php echo (int) $fila['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800" title="Borrar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function confirmarBorrarArchivo(formulario) {
    if (formulario.getAttribute('data-confirmado') === '1') {
        return true;
    }
    Swal.fire({
        icon: 'warning',
        title: '¿Borrar este archivo?',
        showCancelButton: true,
        confirmButtonText: 'Borrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#b91c1c'
    }).then(function (resultado) {
        if (resultado && resultado.isConfirmed) {
            formulario.setAttribute('data-confirmado', '1');
            formulario.submit();
        }
    });
    return false;
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined') {
        return;
    }
    var esperaBusqueda = null;
    var codigoElegido = '';
    var extensionesArchivo = ['pdf', 'xls', 'xlsx', 'doc', 'docx', 'png', 'jpg', 'jpeg'];

    function etiquetaBusqueda() {
        return jQuery('#alcance').val() === 'obra_social' ? 'obra social' : 'prestador';
    }

    function limpiarLista() {
        jQuery('#lista-prestadores').empty().addClass('hidden');
    }

    function marcarElegido(codigo, nombre) {
        codigoElegido = codigo;
        jQuery('#codigo').val(codigo);
        jQuery('#codigo-visible').val(codigo + ' — ' + nombre);
        jQuery('#aviso-prestador').text('Seleccionado: ' + codigo + ' — ' + nombre).removeClass('text-red-600').addClass('text-green-700');
        limpiarLista();
    }

    function pintarResultados(items, mas) {
        var lista = jQuery('#lista-prestadores');
        var etiqueta = etiquetaBusqueda();
        lista.empty();
        if (!items || !items.length) {
            lista.addClass('hidden');
            jQuery('#aviso-prestador').text('No hay ' + (etiqueta === 'obra social' ? 'obras sociales' : 'prestadores') + ' con ese código o nombre.').removeClass('text-green-700').addClass('text-red-600');
            return;
        }
        jQuery.each(items, function (_, item) {
            var boton = jQuery('<button type="button" class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-slate-100 last:border-b-0"></button>');
            boton.text(item.codigo + ' — ' + item.nombre);
            boton.on('click', function () {
                marcarElegido(item.codigo, item.nombre);
            });
            lista.append(boton);
        });
        if (mas) {
            lista.append('<li class="px-3 py-2 text-xs text-slate-500">Hay más coincidencias. Escriba un poco más para achicar la lista.</li>');
        }
        lista.removeClass('hidden');
        if (items.length === 1) {
            jQuery('#aviso-prestador').text('Hay una coincidencia. Haga clic para seleccionarla.').removeClass('text-red-600 text-green-700').addClass('text-slate-500');
        } else {
            jQuery('#aviso-prestador').text('Hay varias coincidencias. Elija una de la lista.').removeClass('text-red-600 text-green-700').addClass('text-slate-500');
        }
    }

    function buscarCodigo() {
        var alcance = jQuery('#alcance').val();
        var texto = jQuery.trim(jQuery('#codigo-visible').val());
        var url = alcance === 'obra_social' ? 'ajax_buscar_obra_social.php' : 'ajax_buscar_prestador.php';
        jQuery('#codigo').val('');
        codigoElegido = '';
        if (alcance !== 'prestador' && alcance !== 'obra_social') {
            return;
        }
        if (texto.length < 2) {
            limpiarLista();
            jQuery('#aviso-prestador').text('Escriba al menos 2 caracteres del código o del nombre.').removeClass('text-red-600 text-green-700').addClass('text-slate-500');
            return;
        }
        jQuery('#aviso-prestador').text('Buscando...').removeClass('text-red-600 text-green-700').addClass('text-slate-500');
        jQuery.getJSON(url, { q: texto })
            .done(function (data) {
                if (!data || data.ok === false) {
                    limpiarLista();
                    jQuery('#aviso-prestador').text(data && data.mensaje ? data.mensaje : 'No se pudo buscar.').removeClass('text-green-700').addClass('text-red-600');
                    return;
                }
                pintarResultados(data.items, data.mas);
            })
            .fail(function () {
                limpiarLista();
                jQuery('#aviso-prestador').text('No se pudo buscar.').removeClass('text-green-700').addClass('text-red-600');
            });
    }

    function extensionPermitida(nombre) {
        var partes = String(nombre || '').toLowerCase().split('.');
        var extension = partes.length > 1 ? partes.pop() : '';
        var i;
        for (i = 0; i < extensionesArchivo.length; i++) {
            if (extensionesArchivo[i] === extension) {
                return true;
            }
        }
        return false;
    }

    function mostrarArchivoElegido(archivo) {
        if (!archivo) {
            jQuery('#nombre-archivo').text('');
            return;
        }
        if (!extensionPermitida(archivo.name)) {
            jQuery('#archivo').val('');
            jQuery('#nombre-archivo').text('Ese tipo de archivo no está permitido. Use PDF, Excel, Word, PNG o JPG.').addClass('text-red-600').removeClass('text-blue-800');
            return;
        }
        jQuery('#nombre-archivo').text(archivo.name).removeClass('text-red-600').addClass('text-blue-800');
    }

    function actualizarAlcance() {
        var alcance = jQuery('#alcance').val();
        limpiarLista();
        jQuery('#codigo').val('');
        jQuery('#codigo-visible').val('');
        codigoElegido = '';
        jQuery('#aviso-prestador').text('').removeClass('text-red-600 text-green-700');
        if (alcance === 'general') {
            jQuery('#bloque-codigo').hide();
            return;
        }
        jQuery('#bloque-codigo').show();
        if (alcance === 'prestador') {
            jQuery('#etiqueta-codigo').text('Código o nombre del prestador');
            jQuery('#codigo-visible').attr('placeholder', 'Código o parte del nombre').removeAttr('maxlength');
            jQuery('#aviso-prestador').text('Escriba el código o parte del nombre y elija el prestador.');
        } else {
            jQuery('#etiqueta-codigo').text('Código de la obra social');
            jQuery('#codigo-visible').attr('placeholder', 'Código o parte del nombre').removeAttr('maxlength');
            jQuery('#aviso-prestador').text('Escriba el código o parte del nombre y elija la obra social.');
        }
    }

    jQuery('#alcance').on('change', actualizarAlcance);
    jQuery('#codigo-visible').on('input', function () {
        var alcance = jQuery('#alcance').val();
        if (alcance !== 'prestador' && alcance !== 'obra_social') {
            return;
        }
        if (jQuery(this).val() === codigoElegido) {
            return;
        }
        clearTimeout(esperaBusqueda);
        esperaBusqueda = setTimeout(buscarCodigo, 250);
    });
    jQuery('#codigo-visible').on('keydown', function (evento) {
        var alcance = jQuery('#alcance').val();
        if (evento.key !== 'Enter' || (alcance !== 'prestador' && alcance !== 'obra_social')) {
            return;
        }
        var primero = jQuery('#lista-prestadores button').first();
        if (primero.length) {
            evento.preventDefault();
            primero.trigger('click');
        }
    });
    jQuery('#archivo').on('change', function () {
        mostrarArchivoElegido(this.files && this.files[0] ? this.files[0] : null);
    });
    var zonaArchivo = document.getElementById('zona-archivo');
    if (zonaArchivo) {
        zonaArchivo.addEventListener('dragover', function (evento) {
            evento.preventDefault();
            zonaArchivo.classList.add('border-blue-500', 'bg-blue-50');
        });
        zonaArchivo.addEventListener('dragleave', function (evento) {
            if (evento.relatedTarget && zonaArchivo.contains(evento.relatedTarget)) {
                return;
            }
            zonaArchivo.classList.remove('border-blue-500', 'bg-blue-50');
        });
        zonaArchivo.addEventListener('drop', function (evento) {
            evento.preventDefault();
            zonaArchivo.classList.remove('border-blue-500', 'bg-blue-50');
            var archivos = evento.dataTransfer && evento.dataTransfer.files ? evento.dataTransfer.files : null;
            var input = document.getElementById('archivo');
            if (!archivos || !archivos.length || !input) {
                return;
            }
            if (typeof DataTransfer === 'undefined') {
                mostrarArchivoElegido(null);
                jQuery('#nombre-archivo').text('No se pudo tomar el archivo arrastrado. Use el clic para elegirlo.').addClass('text-red-600').removeClass('text-blue-800');
                return;
            }
            var transferencia = new DataTransfer();
            transferencia.items.add(archivos[0]);
            input.files = transferencia.files;
            mostrarArchivoElegido(input.files[0]);
        });
    }
    jQuery('#form-subida-archivos').on('submit', function (evento) {
        var alcance = jQuery('#alcance').val();
        if ((alcance === 'prestador' || alcance === 'obra_social') && jQuery.trim(jQuery('#codigo').val()) === '') {
            evento.preventDefault();
            var texto = alcance === 'obra_social' ? 'Seleccione una obra social de la lista.' : 'Seleccione un prestador de la lista.';
            jQuery('#aviso-prestador').text(texto).removeClass('text-green-700').addClass('text-red-600');
        }
    });
    actualizarAlcance();
});
</script>
