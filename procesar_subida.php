<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';

$esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'subir') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$esAdmin) {
        echo json_encode(array('status' => 'error', 'mensaje' => 'Acceso denegado.'));
        exit;
    }

    $alcance = isset($_POST['alcance']) ? trim($_POST['alcance']) : '';
    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';

    if ($codigo === '') {
        echo json_encode(array('status' => 'error', 'mensaje' => 'Debe ingresar el código correspondiente.'));
        exit;
    }

    try {
        $pdo = Database::getConnection();
        $entidadNombre = '';

        if ($alcance === 'obrasocial') {
            $sql = 'SELECT TRIM(TADESCRIP) AS nombre
                    FROM OBRASOC
                    WHERE TRIM(TACODIGO) = :codigo
                      AND TAFECHAFIN >= CURDATE()
                    LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':codigo' => $codigo));
            $obraSocial = $stmt->fetch();

            if (!$obraSocial) {
                echo json_encode(array(
                    'status' => 'error',
                    'mensaje' => 'La Obra Social con código \'' . h($codigo) . '\' no existe o no se encuentra vigente (TAFECHAFIN vencida).',
                ));
                exit;
            }
            $entidadNombre = $obraSocial['nombre'];
        } elseif ($alcance === 'prestador') {
            $prestador = prestadorPorCodigo($pdo, $codigo);
            if ($prestador === null) {
                $busqueda = buscarPrestadores($pdo, $codigo, 15);
                if (count($busqueda['items']) === 1) {
                    $prestador = $busqueda['items'][0];
                } elseif (count($busqueda['items']) > 1) {
                    echo json_encode(array(
                        'status' => 'elegir',
                        'mensaje' => 'Hay varios prestadores en EBAMP. Elija uno de la lista.',
                        'items' => $busqueda['items'],
                        'mas' => $busqueda['mas'],
                    ));
                    exit;
                }
            }
            if ($prestador === null) {
                echo json_encode(array(
                    'status' => 'error',
                    'mensaje' => 'No hay prestadores en EBAMP con el código o nombre \'' . h($codigo) . '\'.',
                ));
                exit;
            }
            $codigo = $prestador['codigo'];
            $entidadNombre = $prestador['nombre'];
        } else {
            echo json_encode(array('status' => 'error', 'mensaje' => 'Selección de alcance no válida.'));
            exit;
        }

        if (!isset($_FILES['archivo_pdf']) || !isset($_FILES['archivo_pdf']['error']) || $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('status' => 'error', 'mensaje' => 'Debe adjuntar un archivo PDF.'));
            exit;
        }

        $nombreOriginal = isset($_FILES['archivo_pdf']['name']) ? basename($_FILES['archivo_pdf']['name']) : '';
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            echo json_encode(array('status' => 'error', 'mensaje' => 'Debe adjuntar un archivo PDF.'));
            exit;
        }

        $nombreSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreOriginal);
        if ($nombreSeguro === '' || $nombreSeguro === null) {
            $nombreSeguro = 'documento.pdf';
        }

        $folderUploads = dirname(__FILE__) . '/uploads/';
        if (!is_dir($folderUploads) && !mkdir($folderUploads, 0755, true)) {
            echo json_encode(array('status' => 'error', 'mensaje' => 'Error al guardar el archivo.'));
            exit;
        }

        $destino = $folderUploads . time() . '_' . $nombreSeguro;
        if (!is_uploaded_file($_FILES['archivo_pdf']['tmp_name']) || !move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $destino)) {
            echo json_encode(array('status' => 'error', 'mensaje' => 'Error al guardar el archivo.'));
            exit;
        }

        echo json_encode(array(
            'status' => 'success',
            'mensaje' => 'Archivo ingresado correctamente para: <strong>' . h($entidadNombre) . '</strong>.',
            'archivo' => $nombreOriginal,
            'entidad' => $entidadNombre,
        ));
    } catch (Exception $e) {
        echo json_encode(array('status' => 'error', 'mensaje' => 'No se pudo validar el código. ' . h($e->getMessage())));
    }
    exit;
}

if (!$esAdmin) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carga y Validación de Archivos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding-top: 40px; }
        .card-upload { max-width: 500px; margin: 0 auto; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-custom { background-color: #1d4ed8; color: white; font-weight: 600; }
        .btn-custom:hover { background-color: #1e40af; color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="card card-upload p-4">
        <h4 class="mb-4 text-center text-primary"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Carga de Documentos</h4>
        <form id="formSubida" enctype="multipart/form-data">
            <input type="hidden" name="action" value="subir">
            <div class="mb-3">
                <label class="form-label fw-bold">Carpeta / Categoría</label>
                <input type="text" class="form-control" value="Coseguros y APB" readonly style="background-color: #e2e8f0;">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Alcance</label>
                <select name="alcance" id="alcanceSelect" class="form-select" onchange="actualizarInterfaz()">
                    <option value="obrasocial">Por obra social específica</option>
                    <option value="prestador">Por prestador específico</option>
                </select>
            </div>
            <div class="mb-3">
                <label id="lblCodigo" class="form-label fw-bold">Código de la obra social</label>
                <input type="text" name="codigo" id="codigoInput" class="form-control" placeholder="Ingrese el código de la obra social" autocomplete="off">
                <div id="listaPrestadores" class="list-group mt-1 d-none"></div>
                <div id="avisoPrestador" class="form-text"></div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Archivo PDF</label>
                <input type="file" name="archivo_pdf" id="pdfInput" class="form-control" accept="application/pdf">
            </div>
            <button type="button" class="btn btn-custom w-100 py-2" onclick="enviarFormulario()">
                <i class="bi bi-upload me-1"></i> Subir archivo
            </button>
        </form>
        <div id="resultadoMensaje" class="mt-4"></div>
    </div>
</div>

<script>
var esperaPrestador = null;

function limpiarListaPrestadores() {
    var lista = document.getElementById('listaPrestadores');
    lista.innerHTML = '';
    lista.className = 'list-group mt-1 d-none';
}

function elegirPrestador(codigo, nombre) {
    var input = document.getElementById('codigoInput');
    input.value = codigo;
    input.setAttribute('data-elegido', codigo);
    document.getElementById('avisoPrestador').textContent = 'Seleccionado: ' + codigo + ' — ' + nombre;
    limpiarListaPrestadores();
}

function pintarPrestadores(items, mas) {
    var lista = document.getElementById('listaPrestadores');
    var aviso = document.getElementById('avisoPrestador');
    lista.innerHTML = '';
    if (!items || !items.length) {
        lista.className = 'list-group mt-1 d-none';
        aviso.textContent = 'No hay prestadores en EBAMP con ese código o nombre.';
        return;
    }
    items.forEach(function (item) {
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'list-group-item list-group-item-action py-2';
        boton.textContent = item.codigo + ' — ' + item.nombre;
        boton.onclick = function () {
            elegirPrestador(item.codigo, item.nombre);
        };
        lista.appendChild(boton);
    });
    if (mas) {
        var extra = document.createElement('div');
        extra.className = 'list-group-item text-muted small';
        extra.textContent = 'Hay más coincidencias. Escriba un poco más para achicar la lista.';
        lista.appendChild(extra);
    }
    lista.className = 'list-group mt-1';
    aviso.textContent = items.length === 1
        ? 'Hay un prestador. Haga clic para seleccionarlo.'
        : 'Hay varios prestadores. Elija uno de la lista.';
}

function buscarPrestador() {
    var input = document.getElementById('codigoInput');
    var texto = input.value.trim();
    var aviso = document.getElementById('avisoPrestador');
    if (input.getAttribute('data-elegido') === texto) {
        return;
    }
    input.removeAttribute('data-elegido');
    if (texto.length < 2) {
        limpiarListaPrestadores();
        aviso.textContent = 'Escriba el código o parte del nombre.';
        return;
    }
    aviso.textContent = 'Buscando en EBAMP...';
    fetch('ajax_buscar_prestador.php?q=' + encodeURIComponent(texto))
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || data.ok === false) {
                limpiarListaPrestadores();
                aviso.textContent = data && data.mensaje ? data.mensaje : 'No se pudo buscar el prestador.';
                return;
            }
            pintarPrestadores(data.items, data.mas);
        })
        .catch(function () {
            limpiarListaPrestadores();
            aviso.textContent = 'No se pudo buscar el prestador.';
        });
}

function actualizarInterfaz() {
    var alcance = document.getElementById('alcanceSelect').value;
    var lbl = document.getElementById('lblCodigo');
    var input = document.getElementById('codigoInput');
    limpiarListaPrestadores();
    document.getElementById('avisoPrestador').textContent = '';
    input.removeAttribute('data-elegido');
    if (alcance === 'obrasocial') {
        lbl.innerText = 'Código de la obra social';
        input.placeholder = 'Ingrese el código de la obra social';
    } else {
        lbl.innerText = 'Código o nombre del prestador';
        input.placeholder = 'Código o parte del nombre';
        document.getElementById('avisoPrestador').textContent = 'Escriba el código o parte del nombre y elija el prestador.';
    }
}

document.getElementById('codigoInput').addEventListener('input', function () {
    if (document.getElementById('alcanceSelect').value !== 'prestador') {
        return;
    }
    clearTimeout(esperaPrestador);
    esperaPrestador = setTimeout(buscarPrestador, 250);
});

function enviarFormulario() {
    var codigo = document.getElementById('codigoInput').value.trim();
    var pdfInput = document.getElementById('pdfInput');
    var divRes = document.getElementById('resultadoMensaje');

    if (!codigo) {
        divRes.innerHTML = '<div class="alert alert-warning py-2">Por favor ingrese un código para validar.</div>';
        return;
    }
    if (!pdfInput.files.length) {
        divRes.innerHTML = '<div class="alert alert-warning py-2">Seleccione un archivo PDF.</div>';
        return;
    }

    var formData = new FormData(document.getElementById('formSubida'));
    divRes.innerHTML = '<div class="alert alert-info py-2"><div class="spinner-border spinner-border-sm me-2"></div>Validando en BD y subiendo...</div>';

    fetch('procesar_subida.php', {
        method: 'POST',
        body: formData
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.status === 'success') {
            divRes.innerHTML = '<div class="alert alert-success py-2">✓ ' + data.mensaje + '</div>';
            document.getElementById('formSubida').reset();
            actualizarInterfaz();
        } else if (data.status === 'elegir') {
            divRes.innerHTML = '<div class="alert alert-warning py-2">' + data.mensaje + '</div>';
            pintarPrestadores(data.items, data.mas);
        } else {
            divRes.innerHTML = '<div class="alert alert-danger py-2">✕ ' + data.mensaje + '</div>';
        }
    })
    .catch(function () {
        divRes.innerHTML = '<div class="alert alert-danger py-2">Error inesperado en el servidor.</div>';
    });
}
</script>
</body>
</html>
