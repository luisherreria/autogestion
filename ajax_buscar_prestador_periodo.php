<?php

require_once dirname(__FILE__) . '/includes/bootstrap.php';
require_once dirname(__FILE__) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('ok' => false, 'items' => array()));
    exit;
}

$texto = isset($_GET['q']) ? trim($_GET['q']) : '';
if (isset($_POST['q'])) {
    $texto = trim($_POST['q']);
}
$texto = str_replace(array('%', '_'), '', $texto);
if (strlen($texto) < 2) {
    echo json_encode(array('ok' => true, 'items' => array()));
    exit;
}

try {
    $pdo = Database::getConnection();
    $like = '%' . $texto . '%';
    $stmt = $pdo->prepare(
        'SELECT TRIM(CODIGO) AS codigo, TRIM(NOMBRE) AS nombre, TRIM(NOMFANTAS) AS fantasia
         FROM cartilla.ebamp
         WHERE TRIM(CODIGO) LIKE :codigo
            OR NOMBRE LIKE :nombre
            OR NOMFANTAS LIKE :fantasia
         GROUP BY TRIM(CODIGO), TRIM(NOMBRE), TRIM(NOMFANTAS)
         ORDER BY CASE WHEN TRIM(CODIGO) = :exacto THEN 0 ELSE 1 END, TRIM(NOMBRE)
         LIMIT 15'
    );
    $stmt->execute(array(
        ':codigo' => $like,
        ':nombre' => $like,
        ':fantasia' => $like,
        ':exacto' => $texto,
    ));
    echo json_encode(array('ok' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)));
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'items' => array()));
}
exit;
