<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=periodos_liquidacion');
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo '<div class="rounded-lg bg-red-50 text-red-700 px-4 py-3">Acceso denegado.</div>';
    return;
}

$filas = array();
$error = '';
$flash = array('tipo' => '', 'texto' => '');
if (isset($_SESSION['flash_periodos']) && is_array($_SESSION['flash_periodos'])) {
    $flash = $_SESSION['flash_periodos'];
}
unset($_SESSION['flash_periodos']);

$editar = array('codigo' => '', 'nombre' => '', 'periodo' => '');
$idEditar = isset($_GET['editar']) ? (int) $_GET['editar'] : 0;

try {
    $pdo = Database::getConnection();
    asegurarTablaPeriodoLiquidacion($pdo);
    $filas = $pdo->query(
        'SELECT id, codigo, nombre, periodo, fechaupdate, usuario
         FROM a_liquidaciones_periodo
         ORDER BY codigo ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($idEditar > 0) {
        foreach ($filas as $fila) {
            if ((int) $fila['id'] === $idEditar) {
                $editar = $fila;
                break;
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<?php if ($error !== '') { ?>
    <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 mb-4 text-sm"><?php echo h($error); ?></div>
<?php } ?>
<?php if ($flash['texto'] !== '') { ?>
    <div class="rounded-lg px-4 py-3 mb-4 text-sm <?php echo $flash['tipo'] === 'ok' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'; ?>"><?php echo h($flash['texto']); ?></div>
<?php } ?>

<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-1"><?php echo $editar['codigo'] !== '' ? 'Editar período' : 'Nueva regla'; ?></h2>
    <p class="text-sm text-slate-500 mb-4">El prestador verá liquidaciones desde ese período inclusive. Formato aa/mm, por ejemplo 26/10. Si no hay regla, sigue viendo desde 25/01.</p>
    <form method="post" action="index.php?seccion=periodos_liquidacion" class="space-y-4" id="form-periodo">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="codigo" id="codigo-prestador" value="<?php echo h($editar['codigo']); ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 relative">
                <label for="buscar-prestador" class="block text-sm font-medium text-slate-700 mb-1">Prestador</label>
                <input type="text" id="buscar-prestador" autocomplete="off"
                       value="<?php echo $editar['codigo'] !== '' ? h($editar['codigo'] . ' - ' . $editar['nombre']) : ''; ?>"
                       placeholder="Código, nombre o nombre de fantasía"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <div id="lista-prestadores" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow hidden max-h-60 overflow-auto"></div>
            </div>
            <div>
                <label for="periodo" class="block text-sm font-medium text-slate-700 mb-1">Desde el período</label>
                <input type="text" name="periodo" id="periodo" maxlength="5" inputmode="numeric"
                       value="<?php echo h($editar['periodo']); ?>"
                       placeholder="26/10"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg px-4 py-2 text-sm bg-blue-800 hover:bg-blue-900 text-white">Guardar</button>
            <?php if ($editar['codigo'] !== '') { ?>
                <a href="index.php?seccion=periodos_liquidacion" class="rounded-lg px-4 py-2 text-sm border border-slate-300 text-slate-700">Cancelar</a>
            <?php } ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto p-4">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="py-2 pr-3">Código</th>
                <th class="py-2 pr-3">Nombre</th>
                <th class="py-2 pr-3">Desde</th>
                <th class="py-2 pr-3">Actualizado</th>
                <th class="py-2 pr-3">Usuario</th>
                <th class="py-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($filas) === 0) { ?>
                <tr><td colspan="6" class="py-4 text-slate-500">No hay reglas cargadas.</td></tr>
            <?php } ?>
            <?php foreach ($filas as $fila) {
                $marca = strtotime((string) $fila['fechaupdate']);
                $cuando = $marca ? date('d/m/Y H:i', $marca) : '';
                ?>
                <tr class="border-b border-slate-100">
                    <td class="py-2 pr-3 font-medium"><?php echo h($fila['codigo']); ?></td>
                    <td class="py-2 pr-3"><?php echo h($fila['nombre']); ?></td>
                    <td class="py-2 pr-3"><?php echo h($fila['periodo']); ?></td>
                    <td class="py-2 pr-3"><?php echo h($cuando); ?></td>
                    <td class="py-2 pr-3"><?php echo h($fila['usuario']); ?></td>
                    <td class="py-2">
                        <a href="index.php?seccion=periodos_liquidacion&editar=<?php echo (int) $fila['id']; ?>" class="text-blue-700 hover:underline mr-3">Editar</a>
                        <form method="post" action="index.php?seccion=periodos_liquidacion" class="inline" onsubmit="return confirm('¿Borrar la regla de <?php echo h($fila['codigo']); ?>?');">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="id" value="<?php echo (int) $fila['id']; ?>">
                            <button type="submit" class="text-red-700 hover:underline">Borrar</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<script>
(function () {
    var campo = document.getElementById('buscar-prestador');
    var codigo = document.getElementById('codigo-prestador');
    var lista = document.getElementById('lista-prestadores');
    var periodo = document.getElementById('periodo');
    var espera = null;
    if (!campo || !codigo || !lista) {
        return;
    }
    periodo.addEventListener('input', function () {
        var digitos = periodo.value.replace(/\D/g, '').slice(0, 4);
        periodo.value = digitos.length > 2 ? digitos.slice(0, 2) + '/' + digitos.slice(2) : digitos;
    });
    campo.addEventListener('input', function () {
        codigo.value = '';
        clearTimeout(espera);
        var texto = campo.value.trim();
        if (texto.length < 2) {
            lista.classList.add('hidden');
            lista.innerHTML = '';
            return;
        }
        espera = setTimeout(function () {
            fetch('ajax_buscar_prestador_periodo.php?q=' + encodeURIComponent(texto))
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (data) {
                    lista.innerHTML = '';
                    var items = data && data.items ? data.items : [];
                    if (items.length === 0) {
                        lista.innerHTML = '<p class="px-3 py-2 text-sm text-slate-500">No hay prestadores.</p>';
                    }
                    items.forEach(function (item) {
                        var boton = document.createElement('button');
                        boton.type = 'button';
                        boton.className = 'block w-full text-left px-3 py-2 text-sm hover:bg-slate-100';
                        var etiqueta = item.codigo + ' - ' + item.nombre;
                        if (item.fantasia && item.fantasia.toLowerCase() !== String(item.nombre).toLowerCase()) {
                            etiqueta += ' (' + item.fantasia + ')';
                        }
                        boton.textContent = etiqueta;
                        boton.addEventListener('click', function () {
                            codigo.value = item.codigo;
                            campo.value = item.codigo + ' - ' + item.nombre;
                            lista.classList.add('hidden');
                        });
                        lista.appendChild(boton);
                    });
                    lista.classList.remove('hidden');
                });
        }, 250);
    });
    document.getElementById('form-periodo').addEventListener('submit', function (evento) {
        if (codigo.value.trim() === '') {
            evento.preventDefault();
            alert('Seleccione un prestador de la lista.');
        }
    });
})();
</script>
