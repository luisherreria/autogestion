<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=busqueda_prestadores');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<div class="max-w-4xl mx-auto rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}
?>
<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Búsqueda y Acceso de Prestadores</h2>
    <p class="text-sm text-gray-600 mb-4">Ingrese el Código, Nombre o Nombre de Fantasía. Haga clic en un correo para ingresar al sistema como ese usuario.</p>

    <div class="mb-6 relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
        </div>
        <input type="text" id="inputBuscarPrestador" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: BAZTERRICA o 01-7012...">
    </div>

    <div id="resultadosPrestadores" class="space-y-4">
        <p class="text-gray-500 text-sm">Escriba al menos 3 caracteres...</p>
    </div>
</div>

<script>
function confirmarAccesoPrestador(enlace) {
    var correo = enlace.getAttribute('data-correo') || '';
    var destino = enlace.getAttribute('href');
    Swal.fire({
        icon: 'question',
        title: '¿Ingresar al sistema como ' + correo + '?',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1e40af'
    }).then(function (resultado) {
        if (resultado && resultado.isConfirmed) {
            window.location.href = destino;
        }
    });
    return false;
}

function iniciarBusquedaPrestadores() {
    if (!window.jQuery || !$('#inputBuscarPrestador').length) {
        return;
    }
    $('#inputBuscarPrestador').on('keyup', function () {
        var termino = $(this).val();
        if (termino.length >= 3) {
            $.post('ajax_buscar_prestadores.php', { busqueda: termino }, function (data) {
                $('#resultadosPrestadores').html(data);
            });
        } else {
            $('#resultadosPrestadores').html('<p class="text-gray-500 text-sm">Escriba al menos 3 caracteres...</p>');
        }
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarBusquedaPrestadores);
} else {
    iniciarBusquedaPrestadores();
}
</script>
