<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!tienePermiso('ver_empadronamiento')) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Acceso denegado.'));
    exit;
}

$busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';
$busqueda = preg_replace('/\s+/', '', $busqueda);
if ($busqueda === '' || strlen($busqueda) > 30) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Ingrese un DNI, carnet o ITROM.'));
    exit;
}

$codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
$institucion = isset($_SESSION['nombre']) ? trim($_SESSION['nombre']) : '';

try {
    $pdo = Database::getConnection();
    $telefono = telefonoPrestador($pdo, $codigo);
    if ($telefono !== '') {
        $_SESSION['telefono'] = $telefono;
    }

    $sql = "SELECT TRIM(u.NOMBRE) AS nombre,
                   TRIM(CAST(u.NRODOC AS CHAR)) AS dni,
                   TRIM(u.NROAFILIAD) AS carnet,
                   TRIM(u.OBRASOC) AS obra_social,
                   TRIM(s.TADESCRIP) AS obra_nombre,
                   TRIM(u.PLAN) AS plan
            FROM unicos u
            LEFT JOIN OBRASOC s ON TRIM(u.OBRASOC) = TRIM(s.TACODIGO)";
    $params = array(
        ':carnet' => $busqueda,
        ':itrom' => $busqueda,
        ':posnet' => $busqueda,
    );
    if ($codigo !== '') {
        $sql .= ' INNER JOIN cartilla.obramed o ON TRIM(u.OBRASOC) = TRIM(o.obrasoc)';
    }
    $sql .= " WHERE (TRIM(u.NROAFILIAD) = :carnet
                   OR TRIM(u.CNROAFI) = :itrom
                   OR TRIM(u.POSNET) = :posnet";
    if (ctype_digit($busqueda)) {
        $sql .= ' OR u.NRODOC = :dni';
        $params[':dni'] = $busqueda;
    }
    $sql .= ")
            AND (u.FBAJA IS NULL OR TRIM(u.FBAJA) = '')";
    if ($codigo !== '') {
        $sql .= " AND TRIM(o.medico) = :codigo
            AND (o.fechabaja IS NULL OR o.fechabaja = '0000-00-00' OR o.fechabaja >= CURDATE())";
        $params[':codigo'] = $codigo;
    }
    $sql .= ' ORDER BY u.id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fila = $stmt->fetch();

    if ($fila) {
        $codigoObra = trim((string) $fila['obra_social']);
        $nombreObra = trim((string) $fila['obra_nombre']);
        if ($nombreObra !== '' && strcasecmp($nombreObra, $codigoObra) !== 0) {
            $obraTexto = $nombreObra . ' (' . $codigoObra . ')';
        } elseif ($nombreObra !== '') {
            $obraTexto = $nombreObra;
        } else {
            $obraTexto = $codigoObra;
        }
        echo json_encode(array(
            'status' => 'activo',
            'afiliado' => array(
                'nombre' => $fila['nombre'],
                'dni' => $fila['dni'],
                'carnet' => $fila['carnet'],
                'obra_social' => $obraTexto,
                'plan' => trim((string) $fila['plan']),
            ),
            'institucion' => $institucion,
            'telefono' => $telefono,
        ));
        exit;
    }

    echo json_encode(array(
        'status' => 'fuera_padron',
        'busqueda' => $busqueda,
        'institucion' => $institucion,
        'telefono' => $telefono,
    ));
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'mensaje' => 'Error SQL: ' . $e->getMessage()));
}

function telefonoPrestador($pdo, $codigo)
{
    if (isset($_SESSION['telefono']) && trim($_SESSION['telefono']) !== '') {
        return trim($_SESSION['telefono']);
    }
    $stmt = $pdo->prepare(
        'SELECT TELCONS, TELPART, TELCONS2
         FROM cartilla.ebamp
         WHERE TRIM(CODIGO) = :codigo
         LIMIT 1'
    );
    $stmt->execute(array(':codigo' => $codigo));
    $fila = $stmt->fetch();
    if (!$fila) {
        return '';
    }
    $candidatos = array('TELCONS', 'TELPART', 'TELCONS2');
    foreach ($candidatos as $columna) {
        $valor = isset($fila[$columna]) ? trim($fila[$columna]) : '';
        if ($valor !== '') {
            return $valor;
        }
    }
    return '';
}
