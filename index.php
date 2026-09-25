<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

define('PORTAL_AUTOGESTION', true);

$secciones = array(
    'autorizaciones' => array('titulo' => 'Autorizaciones', 'icono' => 'fa-file-medical'),
    'coseguros' => array('titulo' => 'Coseguros', 'icono' => 'fa-file-invoice-dollar'),
    'normativas' => array('titulo' => 'Normativas', 'icono' => 'fa-book'),
    'pagos' => array('titulo' => 'Pagos Realizados', 'icono' => 'fa-money-bill-wave'),
);

$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'autorizaciones';
if (!isset($secciones[$seccion])) {
    $seccion = 'autorizaciones';
}

$nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
$codigo = isset($_SESSION['codigo']) ? $_SESSION['codigo'] : '';
$esAdmin = !empty($_SESSION['es_admin']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($secciones[$seccion]['titulo']); ?> - Autogestión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <style>
        #tabla-datos_wrapper .dataTables_filter input,
        #tabla-datos_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
    <header class="fixed top-0 left-0 right-0 h-16 bg-blue-800 text-white flex items-center justify-between px-4 z-50 shadow">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-hospital text-2xl"></i>
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
            <?php foreach ($secciones as $clave => $item) { ?>
                <a href="index.php?seccion=<?php echo h($clave); ?>"
                   class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-slate-800 <?php echo $seccion === $clave ? 'bg-slate-800 text-white border-l-4 border-blue-500' : 'border-l-4 border-transparent'; ?>">
                    <i class="fa-solid <?php echo h($item['icono']); ?> w-5 text-center"></i>
                    <span><?php echo h($item['titulo']); ?></span>
                </a>
            <?php } ?>
        </nav>
    </aside>

    <main class="ml-64 mt-16 p-6 min-h-screen">
        <h1 class="text-2xl font-semibold text-slate-900 mb-4"><?php echo h($secciones[$seccion]['titulo']); ?></h1>
        <?php require dirname(__FILE__) . '/modulos/' . $seccion . '.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script>
        $(function () {
            if ($('#tabla-datos').length) {
                $('#tabla-datos').DataTable({
                    pageLength: 25,
                    order: [],
                    language: {
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_',
                        infoEmpty: 'Sin registros',
                        infoFiltered: '(filtrado de _MAX_)',
                        zeroRecords: 'No se encontraron registros',
                        paginate: {
                            first: 'Primero',
                            last: 'Último',
                            next: 'Siguiente',
                            previous: 'Anterior'
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
