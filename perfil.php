<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

$perfilCodigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$perfilEmail = isset($_SESSION['email']) ? trim($_SESSION['email']) : '';
$perfilNombre = '';
$perfilApellido = '';
$perfilTelefono = '';
$perfilMensaje = '';
$perfilTipo = '';

function asegurarTablaUsuariosPrestadores($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS usuarios_prestadores (
            id INT NOT NULL AUTO_INCREMENT,
            codigo_prestador VARCHAR(50) NOT NULL DEFAULT '',
            email_login VARCHAR(150) NOT NULL DEFAULT '',
            nombre VARCHAR(100) NOT NULL DEFAULT '',
            apellido VARCHAR(100) NOT NULL DEFAULT '',
            telefono_contacto VARCHAR(50) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_prestador_email (codigo_prestador, email_login)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function buscarPerfilPrestador($pdo, $codigo, $email)
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, apellido, telefono_contacto
         FROM usuarios_prestadores
         WHERE codigo_prestador = :codigo AND email_login = :email
         LIMIT 1'
    );
    $stmt->execute(array(
        ':codigo' => $codigo,
        ':email' => $email,
    ));
    return $stmt->fetch();
}

try {
    $pdo = Database::getConnection();
    asegurarTablaUsuariosPrestadores($pdo);

    if ($perfilEmail === '') {
        $perfilTipo = 'error';
        $perfilMensaje = 'La sesión no tiene un email para asociar el perfil.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $perfilNombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $perfilApellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
        $perfilTelefono = isset($_POST['telefono_contacto']) ? trim($_POST['telefono_contacto']) : '';

        if ($perfilNombre === '' || $perfilApellido === '' || $perfilTelefono === '') {
            $perfilTipo = 'error';
            $perfilMensaje = 'Complete nombre, apellido y teléfono de contacto.';
        } else {
            $existente = buscarPerfilPrestador($pdo, $perfilCodigo, $perfilEmail);
            if ($existente) {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios_prestadores
                     SET nombre = :nombre, apellido = :apellido, telefono_contacto = :telefono
                     WHERE id = :id AND codigo_prestador = :codigo AND email_login = :email'
                );
                $stmt->execute(array(
                    ':nombre' => $perfilNombre,
                    ':apellido' => $perfilApellido,
                    ':telefono' => $perfilTelefono,
                    ':id' => $existente['id'],
                    ':codigo' => $perfilCodigo,
                    ':email' => $perfilEmail,
                ));
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios_prestadores
                        (codigo_prestador, email_login, nombre, apellido, telefono_contacto)
                     VALUES
                        (:codigo, :email, :nombre, :apellido, :telefono)'
                );
                $stmt->execute(array(
                    ':codigo' => $perfilCodigo,
                    ':email' => $perfilEmail,
                    ':nombre' => $perfilNombre,
                    ':apellido' => $perfilApellido,
                    ':telefono' => $perfilTelefono,
                ));
            }
            $_SESSION['usuario_nombre'] = $perfilNombre;
            $_SESSION['usuario_apellido'] = $perfilApellido;
            header('Location: index.php');
            exit;
        }
    } else {
        $existente = buscarPerfilPrestador($pdo, $perfilCodigo, $perfilEmail);
        if ($existente) {
            $perfilNombre = trim($existente['nombre']);
            $perfilApellido = trim($existente['apellido']);
            $perfilTelefono = trim($existente['telefono_contacto']);
            $_SESSION['usuario_nombre'] = $perfilNombre;
            $_SESSION['usuario_apellido'] = $perfilApellido;
        }
    }
} catch (Exception $e) {
    $perfilTipo = 'error';
    $perfilMensaje = 'No se pudo guardar el perfil.';
}

function mostrarFormularioPerfil()
{
    global $perfilNombre, $perfilApellido, $perfilTelefono, $perfilMensaje, $perfilTipo, $perfilEmail;
    ?>
    <div class="max-w-lg mx-auto">
        <?php if ($perfilMensaje !== '') { ?>
            <div class="mb-4 rounded-lg px-4 py-3 text-sm <?php echo $perfilTipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'; ?>">
                <?php echo h($perfilMensaje); ?>
            </div>
        <?php } ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <p class="text-sm text-slate-500 mb-6">Estos datos identifican al operador. El ingreso al portal no cambia.</p>
            <form method="post" action="perfil.php" class="space-y-4">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                    <input type="text" name="nombre" id="nombre" required maxlength="100"
                           value="<?php echo h($perfilNombre); ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
                <div>
                    <label for="apellido" class="block text-sm font-medium text-slate-700 mb-1">Apellido</label>
                    <input type="text" name="apellido" id="apellido" required maxlength="100"
                           value="<?php echo h($perfilApellido); ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
                <div>
                    <label for="telefono_contacto" class="block text-sm font-medium text-slate-700 mb-1">Teléfono de contacto</label>
                    <input type="text" name="telefono_contacto" id="telefono_contacto" required maxlength="50"
                           value="<?php echo h($perfilTelefono); ?>"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
                <button type="submit" class="w-full bg-blue-800 hover:bg-blue-900 text-white font-medium rounded-lg py-2.5" <?php echo $perfilEmail === '' ? 'disabled="disabled"' : ''; ?>>
                    Guardar cambios
                </button>
            </form>
        </div>
    </div>
    <?php
}

$GLOBALS['forzarSeccion'] = 'perfil';
$GLOBALS['renderPerfil'] = true;
require dirname(__FILE__) . '/index.php';
