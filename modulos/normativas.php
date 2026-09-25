<?php

if (!defined('PORTAL_AUTOGESTION')) {
    require_once dirname(__FILE__) . '/../includes/bootstrap.php';
    header('Location: ../index.php?seccion=normativas');
    exit;
}

$config = appConfig();
$archivos = listarArchivos($config['dirs']['normativas']);
?>
<?php if (count($archivos) === 0) { ?>
    <div class="bg-white rounded-xl shadow p-8 text-center text-slate-500">No hay normativas disponibles.</div>
<?php } else { ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($archivos as $archivo) { ?>
            <article class="bg-white rounded-xl shadow border border-slate-200 p-6 flex flex-col items-center text-center">
                <i class="fa-solid fa-file-pdf text-6xl text-red-600 mb-4"></i>
                <h2 class="font-medium text-slate-800 break-all mb-4"><?php echo h($archivo); ?></h2>
                <a href="<?php echo h(urlDescarga('normativas', $archivo, false)); ?>"
                   class="mt-auto inline-flex items-center gap-2 bg-blue-800 hover:bg-blue-900 text-white text-sm rounded-lg px-4 py-2">
                    <i class="fa-solid fa-download"></i> Descargar
                </a>
            </article>
        <?php } ?>
    </div>
<?php } ?>
