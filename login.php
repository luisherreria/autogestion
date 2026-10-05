<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';

if (isset($_SESSION['logueado']) && $_SESSION['logueado'] == 1) {
    header('Location: index.php');
    exit;
}

$config = appConfig();
$error = '';

function cargarPerfilEnSesion()
{
    $_SESSION['usuario_nombre'] = '';
    $_SESSION['usuario_apellido'] = '';
    try {
        $pdo = Database::getConnection();
        $stmtPerfil = $pdo->prepare(
            'SELECT nombre, apellido
             FROM usuarios_prestadores
             WHERE codigo_prestador = :codigo AND email_login = :email
             LIMIT 1'
        );
        $stmtPerfil->execute(array(
            ':codigo' => isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '',
            ':email' => isset($_SESSION['email']) ? trim($_SESSION['email']) : '',
        ));
        $perfil = $stmtPerfil->fetch(PDO::FETCH_ASSOC);
        if ($perfil && trim($perfil['nombre']) !== '' && trim($perfil['apellido']) !== '') {
            $_SESSION['usuario_nombre'] = trim($perfil['nombre']);
            $_SESSION['usuario_apellido'] = trim($perfil['apellido']);
        }
    } catch (Exception $e) {
        $_SESSION['usuario_nombre'] = '';
        $_SESSION['usuario_apellido'] = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';

    if ($usuario === '' || $clave === '') {
        $error = 'Complete usuario y clave.';
    } else {
        $adminEncontrado = false;
        $adminClaveOk = false;
        $adminFila = null;
        try {
            $pdo = Database::getConnection();
            $stmtAdmin = $pdo->prepare(
                'SELECT USERNAME, PASSWORD, EMAIL, FIRST_NAME, LAST_NAME
                 FROM users
                 WHERE LOWER(TRIM(USERNAME)) = :usuario
                    OR LOWER(TRIM(IFNULL(EMAIL, \'\'))) = :email
                 LIMIT 20'
            );
            $usuarioLower = strtolower($usuario);
            $stmtAdmin->execute(array(
                ':usuario' => $usuarioLower,
                ':email' => $usuarioLower,
            ));
            $admins = $stmtAdmin->fetchAll();
            foreach ($admins as $filaAdmin) {
                $adminEncontrado = true;
                if (trim((string) $filaAdmin['PASSWORD']) === $clave) {
                    $adminClaveOk = true;
                    $adminFila = $filaAdmin;
                    break;
                }
            }
        } catch (Exception $e) {
            $adminEncontrado = false;
        }

        if ($adminEncontrado && $adminClaveOk && $adminFila !== null) {
            $nombreAdmin = trim($adminFila['FIRST_NAME'] . ' ' . $adminFila['LAST_NAME']);
            if ($nombreAdmin === '') {
                $nombreAdmin = trim($adminFila['USERNAME']);
            }
            $emailAdmin = trim((string) $adminFila['EMAIL']);
            if ($emailAdmin === '') {
                $emailAdmin = trim($adminFila['USERNAME']);
            }
            session_regenerate_id(true);
            $_SESSION['logueado'] = 1;
            $_SESSION['rol'] = 'admin';
            $_SESSION['es_admin'] = 1;
            $_SESSION['nombre'] = $nombreAdmin;
            $_SESSION['usuario'] = trim($adminFila['USERNAME']);
            $_SESSION['email'] = $emailAdmin;
            $_SESSION['codigo'] = '';
            $_SESSION['mail_auto'] = $emailAdmin;
            $_SESSION['mail_deb'] = '';
            $_SESSION['mail_pago'] = '';
            $_SESSION['mailcontra'] = '';
            $_SESSION['tipos_correo'] = array();
            $_SESSION['permisos'] = permisosCompletos();
            cargarPerfilEnSesion();
            completarPrestadorNotificaciones(Database::getConnection());
            $_SESSION['mostrar_bienvenida'] = 1;
            header('Location: index.php');
            exit;
        } elseif ($adminEncontrado && !$adminClaveOk) {
            $error = 'Usuario o clave incorrectos.';
        } elseif (strcasecmp($usuario, $config['admin_usuario']) === 0 && $clave === $config['clave']) {
            session_regenerate_id(true);
            $_SESSION['logueado'] = 1;
            $_SESSION['rol'] = 'admin';
            $_SESSION['es_admin'] = 1;
            $_SESSION['nombre'] = 'Luis';
            $_SESSION['usuario'] = 'Luis';
            $_SESSION['email'] = 'Luis';
            $_SESSION['codigo'] = '';
            $_SESSION['mail_auto'] = '';
            $_SESSION['mail_deb'] = '';
            $_SESSION['mail_pago'] = '';
            $_SESSION['mailcontra'] = '';
            $_SESSION['tipos_correo'] = array();
            $_SESSION['permisos'] = permisosCompletos();
            cargarPerfilEnSesion();
            completarPrestadorNotificaciones(Database::getConnection());
            $_SESSION['mostrar_bienvenida'] = 1;
            header('Location: index.php');
            exit;
        } elseif ($clave !== $config['clave']) {
        $error = 'Usuario o clave incorrectos.';
    } else {
        try {
            $pdo = Database::getConnection();
            $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), strtolower($usuario)) . '%';
            $stmt = $pdo->prepare(
                'SELECT CODIGO, NOMBRE, MAIL_AUTO, MAIL_DEB, MAIL_PAGO, MAILCONTRA
                 FROM cartilla.ebamp
                 WHERE (LOWER(IFNULL(MAIL_AUTO, \'\')) LIKE :mail_auto
                    OR LOWER(IFNULL(MAIL_DEB, \'\')) LIKE :mail_deb
                    OR LOWER(IFNULL(MAIL_PAGO, \'\')) LIKE :mail_pago
                    OR LOWER(IFNULL(MAILCONTRA, \'\')) LIKE :mail_contra)
                   AND (FECHABAJA IS NULL
                    OR FECHABAJA = \'0000-00-00\'
                    OR FECHABAJA = \'\'
                    OR DATE(FECHABAJA) >= CURDATE())
                 ORDER BY CODIGO ASC'
            );
            $stmt->execute(array(
                ':mail_auto' => $like,
                ':mail_deb' => $like,
                ':mail_pago' => $like,
                ':mail_contra' => $like,
            ));
            $filas = $stmt->fetchAll();

            $prestador = null;
            $tiposEncontrados = array();
            foreach ($filas as $fila) {
                $tiposFila = tiposCorreoDelEmail($fila, $usuario);
                if (count($tiposFila) > 0) {
                    $prestador = $fila;
                    $tiposEncontrados = $tiposFila;
                    break;
                }
            }

            if ($prestador === null) {
                $error = 'Usuario o clave incorrectos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['logueado'] = 1;
                $_SESSION['rol'] = 'prestador';
                $_SESSION['es_admin'] = 0;
                $_SESSION['usuario'] = $usuario;
                $_SESSION['email'] = $usuario;
                $_SESSION['codigo'] = trim($prestador['CODIGO']);
                $_SESSION['nombre'] = trim($prestador['NOMBRE']);
                $_SESSION['mail_auto'] = trim((string) $prestador['MAIL_AUTO']);
                $_SESSION['mail_deb'] = trim((string) $prestador['MAIL_DEB']);
                $_SESSION['mail_pago'] = trim((string) $prestador['MAIL_PAGO']);
                $_SESSION['mailcontra'] = trim((string) $prestador['MAILCONTRA']);
                $_SESSION['tipos_correo'] = $tiposEncontrados;
                $_SESSION['permisos'] = permisosPorTipos($pdo, $tiposEncontrados);
                cargarPerfilEnSesion();
                completarPrestadorNotificaciones($pdo);
                $_SESSION['mostrar_bienvenida'] = 1;
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'No se pudo validar el acceso. ' . $e->getMessage();
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso - Autogestión Prestadores</title>
    <link rel="icon" type="image/x-icon" href="upload/img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-blue-800 text-white px-6 py-8 text-center">
            <img src="upload/img/solo_logo_comedica.png" alt="Comedica" class="w-auto h-32 mx-auto object-contain mb-3">
            <h1 class="text-2xl font-semibold">Autogestión Prestadores</h1>
            <p class="text-blue-100 text-sm mt-1">Ingrese con su usuario o email</p>
        </div>
        <form method="post" action="login.php" class="p-6 space-y-4">
            <?php if ($error !== '') { ?>
                <div class="rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">
                    <?php echo h($error); ?>
                </div>
            <?php } ?>
            <div>
                <label for="usuario" class="block text-sm font-medium text-slate-700 mb-1">Usuario / Email</label>
                <input type="text" name="usuario" id="usuario" autocomplete="username"
                       value="<?php echo isset($_POST['usuario']) ? h($_POST['usuario']) : ''; ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
                <label for="clave" class="block text-sm font-medium text-slate-700 mb-1">Clave</label>
                <input type="password" name="clave" id="clave" autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <button type="submit" class="w-full bg-blue-800 hover:bg-blue-900 text-white font-medium rounded-lg py-2.5">
                Ingresar
            </button>
        </form>
    </div>
    <div class="mt-8 text-center text-[10px] text-gray-500 leading-tight tracking-wide w-full">
        <p>&copy; <?php echo date('Y'); ?> COMEDICA S.A.</p>
        <p>Desarrollado por Luis Herrería</p>
    </div>
</body>
</html>
