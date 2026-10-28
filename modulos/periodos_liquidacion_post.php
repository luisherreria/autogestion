<?php

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php?seccion=periodos_liquidacion');
    exit;
}

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';
$texto = 'No se pudo guardar.';
$tipo = 'error';

try {
    $pdo = Database::getConnection();
    asegurarTablaPeriodoLiquidacion($pdo);

    if ($accion === 'borrar') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id < 1) {
            $texto = 'No se indicó qué regla borrar.';
        } else {
            $borrar = $pdo->prepare('DELETE FROM a_liquidaciones_periodo WHERE id = :id');
            $borrar->execute(array(':id' => $id));
            $texto = 'Regla borrada.';
            $tipo = 'ok';
        }
    } elseif ($accion === 'guardar') {
        $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
        $periodo = isset($_POST['periodo']) ? trim($_POST['periodo']) : '';
        if (periodoLiquidacionOrden($periodo) === '') {
            $texto = 'El período debe tener formato aa/mm, por ejemplo 26/10.';
        } elseif ($codigo === '') {
            $texto = 'Seleccione un prestador de la lista.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT TRIM(CODIGO) AS codigo, TRIM(NOMBRE) AS nombre
                 FROM cartilla.ebamp
                 WHERE TRIM(CODIGO) = :codigo
                 LIMIT 1'
            );
            $stmt->execute(array(':codigo' => $codigo));
            $prestador = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prestador) {
                $texto = 'Ese prestador no existe en EBAMP.';
            } else {
                $usuario = isset($_SESSION['usuario']) ? trim((string) $_SESSION['usuario']) : '';
                $usuario = function_exists('mb_strtoupper') ? mb_strtoupper($usuario, 'UTF-8') : strtoupper($usuario);
                $guardar = $pdo->prepare(
                    'INSERT INTO a_liquidaciones_periodo (codigo, nombre, periodo, fechaupdate, usuario)
                     VALUES (:codigo, :nombre, :periodo, NOW(), :usuario)
                     ON DUPLICATE KEY UPDATE
                        nombre = VALUES(nombre),
                        periodo = VALUES(periodo),
                        fechaupdate = NOW(),
                        usuario = VALUES(usuario)'
                );
                $guardar->execute(array(
                    ':codigo' => $prestador['codigo'],
                    ':nombre' => $prestador['nombre'],
                    ':periodo' => $periodo,
                    ':usuario' => $usuario,
                ));
                $texto = 'Período guardado para ' . $prestador['codigo'] . '.';
                $tipo = 'ok';
            }
        }
    }
} catch (Exception $e) {
    $texto = 'No se pudo guardar. ' . $e->getMessage();
}

$_SESSION['flash_periodos'] = array('tipo' => $tipo, 'texto' => $texto);
header('Location: index.php?seccion=periodos_liquidacion');
exit;
