<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('data' => array(), 'ok' => false, 'mensaje' => 'No autorizado.'));
    exit;
}

$accion = isset($_POST['accion']) ? trim((string) $_POST['accion']) : '';

try {
    $pdo = Database::getConnection();
    if ($accion === 'obtener_resumen_diario') {
        echo json_encode(array('data' => resumenDiarioAutorizaciones($pdo)));
        exit;
    }
    if ($accion === 'detalle_ordenes' || $accion === 'detalle_aut_rech') {
        $fecha = isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '';
        if (!fechaResumenValida($fecha)) {
            echo json_encode(array('data' => array(), 'ok' => false, 'mensaje' => 'La fecha no es válida.'));
            exit;
        }
        $soloAutRech = ($accion === 'detalle_aut_rech');
        echo json_encode(array(
            'data' => detalleDiarioAutorizaciones($pdo, $fecha, $soloAutRech),
            'ok' => true,
        ));
        exit;
    }
    echo json_encode(array('data' => array(), 'ok' => false, 'mensaje' => 'Acción no válida.'));
} catch (Exception $e) {
    echo json_encode(array('data' => array(), 'ok' => false, 'mensaje' => 'No se pudo leer el resumen.'));
}
exit;

function fechaResumenValida($fecha)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    $marca = strtotime($fecha);
    if ($marca === false) {
        return false;
    }
    $minimo = strtotime(date('Y-m-d', strtotime('-30 days')));
    $maximo = strtotime(date('Y-m-d'));
    return $marca >= $minimo && $marca <= $maximo;
}

function nombreDiaEspanol($fecha)
{
    $dias = array(
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    );
    $numero = (int) date('N', strtotime($fecha));
    return isset($dias[$numero]) ? $dias[$numero] : '';
}

function resumenDiarioAutorizaciones($pdo)
{
    $sql = "SELECT fecha, SUM(total_ordenes) AS total_ordenes, SUM(total_aut_rech) AS total_aut_rech
            FROM (
                SELECT DATE(COFECHA) AS fecha,
                       COUNT(CONUMERO) AS total_ordenes,
                       SUM(CASE WHEN TRIM(COESTADO) IN ('AUTORIZADA', 'RECHAZADA', 'AUT') THEN 1 ELSE 0 END) AS total_aut_rech
                FROM cartilla.ordenes
                WHERE COFECHA >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND COFECHA < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                GROUP BY DATE(COFECHA)
                UNION ALL
                SELECT DATE(cofecha) AS fecha,
                       COUNT(conumero) AS total_ordenes,
                       SUM(CASE WHEN TRIM(coestado) IN ('AUTORIZADA', 'RECHAZADA', 'AUT') THEN 1 ELSE 0 END) AS total_aut_rech
                FROM cartilla.sanorden
                WHERE cofecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  AND cofecha < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                GROUP BY DATE(cofecha)
            ) resumen
            GROUP BY fecha
            ORDER BY fecha DESC";
    $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $salida = array();
    foreach ($filas as $fila) {
        $fecha = substr(trim((string) $fila['fecha']), 0, 10);
        $salida[] = array(
            'dia' => nombreDiaEspanol($fecha),
            'fecha' => date('d/m/Y', strtotime($fecha)),
            'fecha_iso' => $fecha,
            'total_ordenes' => (int) $fila['total_ordenes'],
            'total_aut_rech' => (int) $fila['total_aut_rech'],
        );
    }
    return $salida;
}

function detalleDiarioAutorizaciones($pdo, $fecha, $soloAutRech)
{
    $filtroAmbu = '';
    $filtroSano = '';
    if ($soloAutRech) {
        $filtroAmbu = " AND TRIM(COESTADO) IN ('AUTORIZADA', 'RECHAZADA', 'AUT')";
        $filtroSano = " AND TRIM(coestado) IN ('AUTORIZADA', 'RECHAZADA', 'AUT')";
    }
    $sql = "SELECT TRIM(CONUMERO) AS orden, COFECHA AS fecha, TRIM(CONOMPAC) AS paciente,
                   TRIM(COPRESTADO) AS codigo_prestador, TRIM(conomprest) AS prestador,
                   TRIM(conomobra) AS obra_social, TRIM(COESTADO) AS estado,
                   TRIM(CONROAUTO) AS conroauto, 'AMBULATORIO' AS origen
            FROM cartilla.ordenes
            WHERE DATE(COFECHA) = :fecha_ambu" . $filtroAmbu . "
            UNION ALL
            SELECT TRIM(conumero) AS orden, cofecha AS fecha, TRIM(conompac) AS paciente,
                   TRIM(coprestado) AS codigo_prestador, TRIM(conomprest) AS prestador,
                   TRIM(conomobra) AS obra_social, TRIM(coestado) AS estado,
                   TRIM(conroauto) AS conroauto, 'SANATORIAL' AS origen
            FROM cartilla.sanorden
            WHERE DATE(cofecha) = :fecha_sano" . $filtroSano . "
            ORDER BY fecha DESC, orden DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        ':fecha_ambu' => $fecha,
        ':fecha_sano' => $fecha,
    ));
    $salida = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $codigo = trim((string) $fila['codigo_prestador']);
        $nombre = trim((string) $fila['prestador']);
        $prestador = $codigo;
        if ($nombre !== '') {
            $prestador = ($codigo !== '') ? $codigo . ' - ' . $nombre : $nombre;
        }
        $obra = trim((string) $fila['obra_social']);
        $salida[] = array(
            'orden' => trim((string) $fila['orden']),
            'fecha' => formatearFecha($fila['fecha']),
            'paciente' => trim((string) $fila['paciente']),
            'prestador' => $prestador,
            'obra_social' => $obra,
            'estado' => trim((string) $fila['estado']),
            'pdf' => $soloAutRech ? rutaPdfResumenAutorizacion($fila) : '',
        );
    }
    return $salida;
}

function rutaPdfResumenAutorizacion($fila)
{
    $estado = isset($fila['estado']) ? trim((string) $fila['estado']) : '';
    $numero = isset($fila['orden']) ? trim((string) $fila['orden']) : '';
    $auto = isset($fila['conroauto']) ? trim((string) $fila['conroauto']) : '';
    if ($estado === 'AUTORIZADA' && $auto !== '' && $auto !== '0') {
        return '../uploads/pdf/' . rawurlencode($auto) . '.pdf';
    }
    if ($estado === 'AUTORIZADA' && ($auto === '' || $auto === '0')) {
        return '../uploads/pdf/orden' . rawurlencode($numero) . '.pdf';
    }
    if ($estado === 'RECHAZADA' && $numero !== '') {
        $origen = isset($fila['origen']) ? trim((string) $fila['origen']) : '';
        return 'descargar_rechazo.php?orden=' . rawurlencode($numero)
            . '&estado=RECHAZADA&origen=' . rawurlencode($origen);
    }
    return '';
}
