<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';

if (isset($_SESSION['logueado']) && $_SESSION['logueado'] == 1) {
    header('Location: index.php');
    exit;
}

$config = appConfig();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';

    if ($usuario === '' || $clave === '') {
        $error = 'Complete usuario y clave.';
    } elseif (strcasecmp($usuario, $config['admin_usuario']) === 0 && $clave === $config['clave']) {
        session_regenerate_id(true);
        $_SESSION['logueado'] = 1;
        $_SESSION['es_admin'] = 1;
        $_SESSION['nombre'] = 'Luis';
        $_SESSION['codigo'] = '';
        $_SESSION['mail_auto'] = '';
        $_SESSION['mail_deb'] = '';
        header('Location: index.php');
        exit;
    } elseif ($clave !== $config['clave']) {
        $error = 'Usuario o clave incorrectos.';
    } else {
        try {
            $pdo = Database::getConnection();
            $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), strtolower($usuario)) . '%';
            $stmt = $pdo->prepare(
                'SELECT CODIGO, NOMBRE, MAIL_AUTO, MAIL_DEB
                 FROM ebamp
                 WHERE MAIL_AUTO IS NOT NULL
                   AND TRIM(MAIL_AUTO) <> \'\'
                   AND LOWER(MAIL_AUTO) LIKE :mail
                 ORDER BY CODIGO ASC'
            );
            $stmt->execute(array(':mail' => $like));
            $filas = $stmt->fetchAll();

            $prestador = null;
            foreach ($filas as $fila) {
                if (emailEstaEnLista($fila['MAIL_AUTO'], $usuario)) {
                    $prestador = $fila;
                    break;
                }
            }

            if ($prestador === null) {
                $error = 'Usuario o clave incorrectos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['logueado'] = 1;
                $_SESSION['es_admin'] = 0;
                $_SESSION['codigo'] = trim($prestador['CODIGO']);
                $_SESSION['nombre'] = trim($prestador['NOMBRE']);
                $_SESSION['mail_auto'] = trim($prestador['MAIL_AUTO']);
                $_SESSION['mail_deb'] = trim($prestador['MAIL_DEB']);
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'No se pudo validar el acceso. ' . $e->getMessage();
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-blue-800 text-white px-6 py-8 text-center">
            <div class="text-4xl mb-3"><i class="fa-solid fa-hospital"></i></div>
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
</body>
</html>
