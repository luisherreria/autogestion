<?php

if (!esUsuarioLuis()) {
    $_SESSION['flash_archivos'] = array(
        'tipo' => 'error',
        'texto' => 'Acceso denegado.',
    );
    header('Location: index.php?seccion=admin_archivos');
    exit;
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

if (isset($_POST['accion']) && $_POST['accion'] === 'borrar') {
    $idBorrar = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $texto = 'No se pudo borrar el archivo.';
    $tipo = 'error';
    if ($idBorrar > 0) {
        try {
            $pdo = Database::getConnection();
            asegurarTablaArchivos($pdo);
            $stmt = $pdo->prepare(
                'SELECT id, nombre_archivo, ruta, categoria
                 FROM archivos_subidos
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(array(':id' => $idBorrar));
            $fila = $stmt->fetch();
            if (!$fila) {
                $texto = 'El archivo ya no está en el registro.';
            } elseif (!isset($config['dirs'][$fila['categoria']])) {
                $texto = 'No se puede borrar ese archivo.';
            } else {
                $base = realpath($config['dirs'][$fila['categoria']]);
                $relativa = str_replace('\\', '/', $fila['ruta']);
                $proyecto = realpath(dirname(__FILE__) . '/..');
                $candidato = $proyecto . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
                $real = realpath($candidato);
                if ($real !== false && $base !== false && is_file($real) && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) {
                    unlink($real);
                }
                $borrar = $pdo->prepare('DELETE FROM archivos_subidos WHERE id = :id');
                $borrar->execute(array(':id' => $idBorrar));
                if ($fila['categoria'] === 'coseguros') {
                    asegurarTablaArchivosCoseguros($pdo);
                    $borrarCoseguro = $pdo->prepare('DELETE FROM archivos_coseguros WHERE ruta = :ruta');
                    $borrarCoseguro->execute(array(':ruta' => $relativa));
                }
                $texto = 'Archivo borrado: ' . $fila['nombre_archivo'];
                $tipo = 'ok';
            }
        } catch (Exception $e) {
            $texto = 'No se pudo borrar el archivo. ' . $e->getMessage();
        }
    }
    $_SESSION['flash_archivos'] = array('tipo' => $tipo, 'texto' => $texto);
    header('Location: index.php?seccion=admin_archivos');
    exit;
}

$categoria = isset($_POST['categoria']) ? $_POST['categoria'] : '';
$alcance = isset($_POST['alcance']) ? $_POST['alcance'] : '';
$codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
$mensaje = '';

if (!isset($categorias[$categoria]) || !isset($alcances[$alcance])) {
    $mensaje = 'La categoría o el alcance no son válidos.';
} elseif (!isset($config['dirs'][$categoria])) {
    $mensaje = 'No hay una carpeta configurada para esa categoría.';
} elseif ($alcance !== 'general' && codigoCarpetaValido($codigo) === '') {
    $mensaje = ($alcance === 'prestador')
        ? 'Seleccione un prestador de la lista (código o parte del nombre).'
        : 'Ingrese un código válido, sin barras ni puntos.';
} elseif ($alcance === 'prestador') {
    try {
        $pdo = Database::getConnection();
        $prestador = prestadorPorCodigo($pdo, $codigo);
        if ($prestador === null) {
            $mensaje = 'El prestador no existe en EBAMP. Elija uno de la lista.';
        } else {
            $codigo = $prestador['codigo'];
        }
    } catch (Exception $e) {
        $mensaje = 'No se pudo validar el prestador. ' . $e->getMessage();
    }
}

if ($mensaje !== '') {
    $_SESSION['flash_archivos'] = array('tipo' => 'error', 'texto' => $mensaje);
    header('Location: index.php?seccion=admin_archivos');
    exit;
}

$extensionesPermitidas = array('pdf', 'xls', 'xlsx', 'doc', 'docx', 'png', 'jpg', 'jpeg');

if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
    $mensaje = 'Seleccione un archivo.';
} else {
    $archivo = $_FILES['archivo'];
    $errorCarga = isset($archivo['error']) ? (int) $archivo['error'] : UPLOAD_ERR_NO_FILE;
    $nombreOriginal = isset($archivo['name']) ? basename($archivo['name']) : '';
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if ($errorCarga === UPLOAD_ERR_NO_FILE) {
        $mensaje = 'Seleccione un archivo.';
    } elseif ($errorCarga === UPLOAD_ERR_INI_SIZE || $errorCarga === UPLOAD_ERR_FORM_SIZE) {
        $mensaje = 'El archivo supera el tamaño permitido.';
    } elseif ($errorCarga !== UPLOAD_ERR_OK) {
        $mensaje = 'No se pudo recibir el archivo.';
    } elseif (!in_array($extension, $extensionesPermitidas, true) || !is_uploaded_file($archivo['tmp_name'])) {
        $mensaje = 'Solo se aceptan PDF, Excel, Word, PNG o JPG.';
    } else {
        $extensionOriginal = $extension;
        $nombreOriginal = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombreOriginal);
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($nombreOriginal === '' || !in_array($extension, $extensionesPermitidas, true)) {
            $extension = $extensionOriginal;
            $nombreOriginal = 'archivo.' . $extension;
        }

        $carpetaAlcance = ($alcance === 'general') ? 'general' : codigoCarpetaValido($codigo);
        $directorio = $config['dirs'][$categoria] . DIRECTORY_SEPARATOR . $carpetaAlcance;

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
            $mensaje = 'No se pudo crear la carpeta de destino.';
        } else {
            $destino = $directorio . DIRECTORY_SEPARATOR . $nombreOriginal;
            if (is_file($destino)) {
                $base = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                $nombreOriginal = $base . '_' . date('YmdHis') . '.' . $extension;
                $destino = $directorio . DIRECTORY_SEPARATOR . $nombreOriginal;
            }

            if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                $mensaje = 'No se pudo guardar el archivo en el servidor.';
            } else {
                try {
                    $pdo = Database::getConnection();
                    asegurarTablaArchivos($pdo);
                    $stmt = $pdo->prepare(
                        'INSERT INTO archivos_subidos
                            (nombre_archivo, ruta, categoria, alcance, codigo_asociado, fecha)
                         VALUES
                            (:nombre, :ruta, :categoria, :alcance, :codigo, :fecha)'
                    );
                    $rutaRegistro = 'archivos/' . $categoria . '/' . $carpetaAlcance . '/' . $nombreOriginal;
                    $codigoRegistro = ($alcance === 'general') ? '' : $carpetaAlcance;
                    $fechaRegistro = date('Y-m-d H:i:s');
                    $stmt->execute(array(
                        ':nombre' => $nombreOriginal,
                        ':ruta' => $rutaRegistro,
                        ':categoria' => $categoria,
                        ':alcance' => $alcance,
                        ':codigo' => $codigoRegistro,
                        ':fecha' => $fechaRegistro,
                    ));
                    if ($categoria === 'coseguros') {
                        registrarArchivoCoseguro($pdo, $nombreOriginal, $rutaRegistro, $alcance, $codigoRegistro, $fechaRegistro);
                    }
                    $mensaje = 'Archivo subido: ' . $nombreOriginal;
                    $_SESSION['flash_archivos'] = array('tipo' => 'ok', 'texto' => $mensaje);
                    header('Location: index.php?seccion=admin_archivos');
                    exit;
                } catch (Exception $e) {
                    $mensaje = 'El archivo se guardó, pero no se pudo registrar en la base. ' . $e->getMessage();
                }
            }
        }
    }
}

$_SESSION['flash_archivos'] = array('tipo' => 'error', 'texto' => $mensaje);
header('Location: index.php?seccion=admin_archivos');
exit;
