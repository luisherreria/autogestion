<?php

function appConfig()
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__FILE__) . '/../config/app.php';
    }
    return $config;
}

function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatearFecha($valor)
{
    $valor = trim((string) $valor);
    if ($valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
        return '';
    }
    $marca = strtotime($valor);
    if ($marca === false) {
        return $valor;
    }
    return date('d/m/Y', $marca);
}

function formatearImporte($valor)
{
    return '$ ' . number_format((float) $valor, 2, ',', '.');
}

function urlDescarga($tipo, $archivo, $ver)
{
    $url = 'descargar.php?tipo=' . rawurlencode($tipo) . '&archivo=' . rawurlencode($archivo);
    if ($ver) {
        $url .= '&modo=ver';
    }
    return $url;
}

function listarArchivos($directorio)
{
    $items = array();
    if (!is_dir($directorio)) {
        return $items;
    }
    $nombres = scandir($directorio);
    if ($nombres === false) {
        return $items;
    }
    foreach ($nombres as $nombre) {
        if ($nombre === '.' || $nombre === '..' || $nombre === '.gitkeep' || $nombre[0] === '.') {
            continue;
        }
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (is_file($ruta)) {
            $items[] = $nombre;
        }
    }
    natcasesort($items);
    return array_values($items);
}

function buscarArchivo($directorio, $candidatos)
{
    foreach ($candidatos as $nombre) {
        $nombre = basename($nombre);
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            continue;
        }
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombre;
        if (is_file($ruta)) {
            return $nombre;
        }
    }
    return '';
}

function candidatosPdfNumero($numero)
{
    $numero = trim((string) $numero);
    $candidatos = array();
    if ($numero === '') {
        return $candidatos;
    }
    $candidatos[] = $numero . '.pdf';
    $sinCeros = ltrim($numero, '0');
    if ($sinCeros !== '' && $sinCeros !== $numero) {
        $candidatos[] = $sinCeros . '.pdf';
    }
    return $candidatos;
}

function emailEstaEnLista($listaMails, $emailIngresado)
{
    $emailIngresado = strtolower(trim($emailIngresado));
    if ($emailIngresado === '') {
        return false;
    }

    $porComa = explode(',', (string) $listaMails);
    $limpios = array();
    foreach ($porComa as $trozo) {
        $porPuntoComa = explode(';', $trozo);
        foreach ($porPuntoComa as $correo) {
            $correo = strtolower(trim($correo));
            if ($correo !== '') {
                $limpios[] = $correo;
            }
        }
    }

    return in_array($emailIngresado, $limpios, true);
}

function esUsuarioLuis()
{
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        return true;
    }
    return isset($_SESSION['nombre']) && $_SESSION['nombre'] == 'Luis';
}

function asegurarTablaAuditoria($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auditoria_log (
            id INT NOT NULL AUTO_INCREMENT,
            codigo_prestador VARCHAR(50) DEFAULT NULL,
            usuario_email VARCHAR(255) DEFAULT NULL,
            fecha_hora DATETIME NOT NULL,
            modulo VARCHAR(100) DEFAULT NULL,
            accion_detalle VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function codigoCarpetaValido($codigo)
{
    $codigo = trim((string) $codigo);
    if ($codigo === '' || strpos($codigo, '..') !== false) {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9 _-]{1,20}$/', $codigo)) {
        return '';
    }
    return $codigo;
}

function prestadorPorCodigo($pdo, $codigo)
{
    $codigo = trim((string) $codigo);
    if ($codigo === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT TRIM(CODIGO) AS codigo, TRIM(NOMBRE) AS nombre
         FROM EBAMP
         WHERE TRIM(CODIGO) = :codigo
         LIMIT 1'
    );
    $stmt->execute(array(':codigo' => $codigo));
    $fila = $stmt->fetch();
    if (!$fila) {
        return null;
    }
    return $fila;
}

function buscarPrestadores($pdo, $texto, $limite)
{
    $texto = trim((string) $texto);
    $texto = str_replace(array('%', '_'), '', $texto);
    $limite = (int) $limite;
    if ($limite < 1) {
        $limite = 15;
    }
    if ($limite > 30) {
        $limite = 30;
    }
    if ($texto === '') {
        return array('items' => array(), 'mas' => false);
    }

    $like = '%' . $texto . '%';
    $sql = 'SELECT TRIM(CODIGO) AS codigo, TRIM(NOMBRE) AS nombre
            FROM EBAMP
            WHERE TRIM(CODIGO) LIKE :por_codigo
               OR NOMBRE LIKE :por_nombre
            GROUP BY TRIM(CODIGO), TRIM(NOMBRE)
            ORDER BY CASE WHEN TRIM(CODIGO) = :exacto THEN 0 ELSE 1 END, TRIM(NOMBRE)
            LIMIT ' . ($limite + 1);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        ':por_codigo' => $like,
        ':por_nombre' => $like,
        ':exacto' => $texto,
    ));
    $filas = $stmt->fetchAll();
    $mas = false;
    if (count($filas) > $limite) {
        $mas = true;
        $filas = array_slice($filas, 0, $limite);
    }
    return array('items' => $filas, 'mas' => $mas);
}

function asegurarTablaArchivos($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS archivos_subidos (
            id INT NOT NULL AUTO_INCREMENT,
            nombre_archivo VARCHAR(255) NOT NULL,
            ruta VARCHAR(500) NOT NULL,
            categoria VARCHAR(50) NOT NULL,
            alcance VARCHAR(30) NOT NULL,
            codigo_asociado VARCHAR(40) DEFAULT NULL,
            fecha DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function carpetasDeDirectorio($directorio)
{
    $carpetas = array();
    if (!is_dir($directorio)) {
        return $carpetas;
    }
    $nombres = scandir($directorio);
    if ($nombres === false) {
        return $carpetas;
    }
    foreach ($nombres as $nombre) {
        if ($nombre === '.' || $nombre === '..' || $nombre[0] === '.') {
            continue;
        }
        if (is_dir($directorio . DIRECTORY_SEPARATOR . $nombre)) {
            $carpetas[] = $nombre;
        }
    }
    return $carpetas;
}

function codigosCarpetasPrestador($pdo, $codigo)
{
    $codigos = array();
    $codigo = trim((string) $codigo);
    if ($codigo !== '') {
        $codigos[] = $codigo;
    }
    if ($codigo === '') {
        return $codigos;
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT TRIM(obrasoc) AS codigo
         FROM obramed
         WHERE TRIM(medico) = :codigo
           AND (fechabaja IS NULL OR fechabaja = '0000-00-00' OR fechabaja > CURDATE())"
    );
    $stmt->execute(array(':codigo' => $codigo));
    foreach ($stmt->fetchAll() as $fila) {
        $obra = trim($fila['codigo']);
        if ($obra !== '' && !in_array($obra, $codigos, true)) {
            $codigos[] = $obra;
        }
    }
    return $codigos;
}

function listarArchivosVisibles($directorio, $carpetasPermitidas)
{
    $items = listarArchivos($directorio);
    $vistas = array('general' => true);
    foreach ($carpetasPermitidas as $carpeta) {
        $carpeta = trim((string) $carpeta);
        if ($carpeta !== '') {
            $vistas[$carpeta] = true;
        }
    }

    foreach (array_keys($vistas) as $carpeta) {
        $sub = $directorio . DIRECTORY_SEPARATOR . $carpeta;
        foreach (listarArchivos($sub) as $nombre) {
            $items[] = $carpeta . '/' . $nombre;
        }
    }

    return $items;
}

function asegurarTablaArchivosCoseguros($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS archivos_coseguros (
            id INT NOT NULL AUTO_INCREMENT,
            nombre_archivo VARCHAR(255) NOT NULL,
            ruta VARCHAR(500) NOT NULL,
            alcance VARCHAR(30) NOT NULL,
            codigo VARCHAR(40) DEFAULT NULL,
            fecha DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $pdo->exec(
        "INSERT INTO archivos_coseguros (nombre_archivo, ruta, alcance, codigo, fecha)
         SELECT s.nombre_archivo, s.ruta,
                CASE WHEN s.alcance = 'obra_social' THEN 'obrasocial' ELSE s.alcance END,
                s.codigo_asociado, s.fecha
         FROM archivos_subidos s
         WHERE s.categoria = 'coseguros'
           AND NOT EXISTS (
                SELECT 1 FROM archivos_coseguros c WHERE c.ruta = s.ruta
           )"
    );
}

function registrarArchivoCoseguro($pdo, $nombre, $ruta, $alcance, $codigo, $fecha)
{
    asegurarTablaArchivosCoseguros($pdo);
    if ($alcance === 'obra_social') {
        $alcance = 'obrasocial';
    }
    $existe = $pdo->prepare('SELECT id FROM archivos_coseguros WHERE ruta = :ruta LIMIT 1');
    $existe->execute(array(':ruta' => $ruta));
    if ($existe->fetch()) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO archivos_coseguros (nombre_archivo, ruta, alcance, codigo, fecha)
         VALUES (:nombre, :ruta, :alcance, :codigo, :fecha)'
    );
    $stmt->execute(array(
        ':nombre' => $nombre,
        ':ruta' => $ruta,
        ':alcance' => $alcance,
        ':codigo' => $codigo,
        ':fecha' => $fecha,
    ));
}

function rutaRelativaCoseguro($ruta)
{
    $ruta = str_replace('\\', '/', (string) $ruta);
    $prefijo = 'archivos/coseguros/';
    if (strpos($ruta, $prefijo) === 0) {
        return substr($ruta, strlen($prefijo));
    }
    return basename($ruta);
}

function codigosObraSocialSesion($pdo, $codigoPrestador)
{
    $codigos = array();
    if (isset($_SESSION['obra_social']) && trim($_SESSION['obra_social']) !== '') {
        $codigos[] = trim($_SESSION['obra_social']);
    }
    if (isset($_SESSION['codigo_obra']) && trim($_SESSION['codigo_obra']) !== '') {
        $codigos[] = trim($_SESSION['codigo_obra']);
    }

    $codigoPrestador = trim((string) $codigoPrestador);
    if ($codigoPrestador === '') {
        return $codigos;
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT TRIM(obrasoc) AS codigo
         FROM obramed
         WHERE TRIM(medico) = :codigo
           AND (fechabaja IS NULL OR fechabaja = '0000-00-00' OR fechabaja > CURDATE())"
    );
    $stmt->execute(array(':codigo' => $codigoPrestador));
    foreach ($stmt->fetchAll() as $fila) {
        $obra = trim($fila['codigo']);
        if ($obra !== '' && !in_array($obra, $codigos, true)) {
            $codigos[] = $obra;
        }
    }
    return $codigos;
}

function mapaModulosPermiso()
{
    return array(
        'ver_autorizaciones' => array('clave' => 'autorizaciones', 'titulo' => 'Autorizaciones'),
        'ver_obras_sociales' => array('clave' => 'obras_sociales', 'titulo' => 'Obras Sociales Vigentes'),
        'ver_coseguros' => array('clave' => 'coseguros', 'titulo' => 'Coseguros y APB'),
        'ver_normativas' => array('clave' => 'normativas', 'titulo' => 'Normativas'),
        'ver_contratos' => array('clave' => 'contratos', 'titulo' => 'Contratos'),
        'ver_pagos' => array('clave' => 'pagos', 'titulo' => 'Liquidaciones'),
        'ver_empadronamiento' => array('clave' => 'ver_empadronamiento', 'titulo' => 'Emp. Afiliados'),
    );
}

function columnasCorreoPrestador()
{
    return array(
        'MAIL_AUTO' => 'mail_auto',
        'MAIL_DEB' => 'mail_deb',
        'MAIL_PAGO' => 'mail_pago',
        'MAILCONTRA' => 'mailcontra',
    );
}

function etiquetaTipoCorreo($tipo)
{
    $etiquetas = array(
        'mail_auto' => 'Autorizaciones (mail_auto)',
        'mail_deb' => 'Débitos (mail_deb)',
        'mail_pago' => 'Pagos (mail_pago)',
        'mailcontra' => 'Contratos (mailcontra)',
    );
    $tipo = (string) $tipo;
    return isset($etiquetas[$tipo]) ? $etiquetas[$tipo] : $tipo;
}

function permisosVacios()
{
    $permisos = array();
    foreach (mapaModulosPermiso() as $modulo) {
        $permisos[$modulo['clave']] = 0;
    }
    return $permisos;
}

function permisosCompletos()
{
    $permisos = array();
    foreach (mapaModulosPermiso() as $modulo) {
        $permisos[$modulo['clave']] = 1;
    }
    return $permisos;
}

function asegurarTablaPermisos($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS a_permisos (
            id INT NOT NULL AUTO_INCREMENT,
            tipo_correo VARCHAR(50) NOT NULL,
            ver_autorizaciones TINYINT(1) DEFAULT 0,
            ver_obras_sociales TINYINT(1) DEFAULT 0,
            ver_coseguros TINYINT(1) DEFAULT 0,
            ver_normativas TINYINT(1) DEFAULT 0,
            ver_contratos TINYINT(1) DEFAULT 0,
            ver_pagos TINYINT(1) DEFAULT 0,
            ver_empadronamiento TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY tipo_correo (tipo_correo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
    $columnaEmpadronamiento = $pdo->query("SHOW COLUMNS FROM a_permisos LIKE 'ver_empadronamiento'")->fetch();
    if (!$columnaEmpadronamiento) {
        $pdo->exec('ALTER TABLE a_permisos ADD COLUMN ver_empadronamiento TINYINT(1) DEFAULT 0');
    }

    $semilla = array(
        array('mail_auto', 1, 1, 1, 1, 0, 0),
        array('mail_deb', 0, 1, 1, 1, 1, 1),
        array('mail_pago', 0, 1, 1, 1, 1, 1),
        array('mailcontra', 0, 1, 1, 1, 1, 0),
    );
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO a_permisos
            (tipo_correo, ver_autorizaciones, ver_obras_sociales, ver_coseguros, ver_normativas, ver_contratos, ver_pagos)
         VALUES
            (:tipo, :autorizaciones, :obras, :coseguros, :normativas, :contratos, :pagos)'
    );
    foreach ($semilla as $fila) {
        $stmt->execute(array(
            ':tipo' => $fila[0],
            ':autorizaciones' => $fila[1],
            ':obras' => $fila[2],
            ':coseguros' => $fila[3],
            ':normativas' => $fila[4],
            ':contratos' => $fila[5],
            ':pagos' => $fila[6],
        ));
    }
}

function tiposCorreoDelEmail($fila, $email)
{
    $tipos = array();
    foreach (columnasCorreoPrestador() as $columna => $tipo) {
        $lista = isset($fila[$columna]) ? $fila[$columna] : '';
        if (emailEstaEnLista($lista, $email)) {
            $tipos[] = $tipo;
        }
    }
    return $tipos;
}

function permisosPorTipos($pdo, $tipos)
{
    asegurarTablaPermisos($pdo);
    $permisos = permisosVacios();
    if (!is_array($tipos) || count($tipos) === 0) {
        return $permisos;
    }

    $marcas = array();
    $params = array();
    $indice = 0;
    foreach ($tipos as $tipo) {
        $tipo = trim((string) $tipo);
        if ($tipo === '') {
            continue;
        }
        $clave = ':tipo' . $indice;
        $marcas[] = $clave;
        $params[$clave] = $tipo;
        $indice++;
    }
    if (count($marcas) === 0) {
        return $permisos;
    }

    $sql = 'SELECT ' . implode(', ', array_keys(mapaModulosPermiso())) . '
            FROM a_permisos
            WHERE tipo_correo IN (' . implode(', ', $marcas) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $fila) {
        foreach (mapaModulosPermiso() as $columna => $modulo) {
            if (!empty($fila[$columna])) {
                $permisos[$modulo['clave']] = 1;
            }
        }
    }
    return $permisos;
}

function tiposCorreoPorCodigo($pdo, $codigo, $email)
{
    $codigo = trim((string) $codigo);
    $email = trim((string) $email);
    if ($codigo === '' || $email === '') {
        return array();
    }
    $stmt = $pdo->prepare(
        'SELECT MAIL_AUTO, MAIL_DEB, MAIL_PAGO, MAILCONTRA
         FROM ebamp
         WHERE TRIM(CODIGO) = :codigo
         ORDER BY CODIGO ASC'
    );
    $stmt->execute(array(':codigo' => $codigo));
    $tipos = array();
    foreach ($stmt->fetchAll() as $fila) {
        foreach (tiposCorreoDelEmail($fila, $email) as $tipo) {
            if (!in_array($tipo, $tipos, true)) {
                $tipos[] = $tipo;
            }
        }
    }
    return $tipos;
}

function refrescarPermisosSesion($pdo)
{
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        $_SESSION['tipos_correo'] = array();
        $_SESSION['permisos'] = permisosCompletos();
        return;
    }

    if (!isset($_SESSION['tipos_correo']) || !is_array($_SESSION['tipos_correo'])) {
        $codigo = isset($_SESSION['codigo']) ? $_SESSION['codigo'] : '';
        $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
        $_SESSION['tipos_correo'] = tiposCorreoPorCodigo($pdo, $codigo, $email);
    }
    $_SESSION['permisos'] = permisosPorTipos($pdo, $_SESSION['tipos_correo']);
}

function tienePermiso($modulo)
{
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        return true;
    }
    return isset($_SESSION['permisos'][$modulo]) && $_SESSION['permisos'][$modulo];
}

function asegurarTablaNotificaciones($pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notificaciones_historial (
            id_notificacion INT NOT NULL AUTO_INCREMENT,
            cod_prestador VARCHAR(50) DEFAULT NULL,
            fecha_emision DATETIME NOT NULL,
            asunto_mail VARCHAR(255) DEFAULT NULL,
            tipo_notificacion VARCHAR(50) DEFAULT NULL,
            cuerpo_html MEDIUMTEXT,
            archivo_adjunto VARCHAR(255) DEFAULT NULL,
            estado_lectura TINYINT(1) NOT NULL DEFAULT 0,
            fecha_lectura DATETIME DEFAULT NULL,
            usuario_lector VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id_notificacion),
            KEY idx_notif_prestador (cod_prestador, estado_lectura)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function tiposNotificacionPermitidos()
{
    $tipos = array();
    if (tienePermiso('pagos')) {
        $tipos = array_merge($tipos, array('RESUMEN', 'PAGO', 'CSN', 'DOC_RESP', 'DEBITO'));
    }
    if (tienePermiso('autorizaciones')) {
        $tipos = array_merge($tipos, array('VIGENCIA', 'COSEGUROS_VIG'));
    }
    return $tipos;
}

function correosLineasSesion()
{
    $lineas = array('mail_auto', 'mail_deb', 'mail_pago', 'mailcontra');
    $tipos = isset($_SESSION['tipos_correo']) && is_array($_SESSION['tipos_correo']) ? $_SESSION['tipos_correo'] : array();
    $correos = array();
    foreach ($tipos as $tipo) {
        $tipo = trim((string) $tipo);
        if (!in_array($tipo, $lineas, true)) {
            continue;
        }
        $lista = isset($_SESSION[$tipo]) ? (string) $_SESSION[$tipo] : '';
        $porComa = explode(',', $lista);
        foreach ($porComa as $trozo) {
            $porPuntoComa = explode(';', $trozo);
            foreach ($porPuntoComa as $correo) {
                $correo = strtolower(trim($correo));
                if ($correo === '' || strpos($correo, '@') === false || in_array($correo, $correos, true)) {
                    continue;
                }
                $correos[] = $correo;
            }
        }
    }
    $email = isset($_SESSION['email']) ? strtolower(trim($_SESSION['email'])) : '';
    if ($email !== '' && strpos($email, '@') !== false && !in_array($email, $correos, true)) {
        $correos[] = $email;
    }
    return $correos;
}

function filtroNotificaciones($soloNoLeidas)
{
    $tipos = tiposNotificacionPermitidos();
    $marcas = array();
    $parametros = array();
    foreach ($tipos as $indice => $tipo) {
        $clave = ':tipo' . $indice;
        $marcas[] = $clave;
        $parametros[$clave] = $tipo;
    }
    $esAdmin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    $where = array();
    if (count($marcas) === 0) {
        $where[] = '1 = 0';
    } else {
        $where[] = 'tipo_notificacion IN (' . implode(', ', $marcas) . ')';
    }
    if (!$esAdmin) {
        $partes = array('TRIM(cod_prestador) = :codigo');
        $parametros[':codigo'] = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
        $correos = correosLineasSesion();
        foreach ($correos as $indice => $correo) {
            $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $correo) . '%';
            $partes[] = 'remitente_mail LIKE :email_rem' . $indice;
            $partes[] = 'destinatarios_mail LIKE :email_des' . $indice;
            $parametros[':email_rem' . $indice] = $like;
            $parametros[':email_des' . $indice] = $like;
        }
        $where[] = '(' . implode(' OR ', $partes) . ')';
    }
    if ($soloNoLeidas) {
        $where[] = 'estado_lectura = 0';
    }
    return array(
        'where' => implode(' AND ', $where),
        'parametros' => $parametros,
    );
}

function notificacionVisible($fila)
{
    if (!is_array($fila)) {
        return false;
    }
    $tipo = strtoupper(trim((string) $fila['tipo_notificacion']));
    if (!in_array($tipo, tiposNotificacionPermitidos(), true)) {
        return false;
    }
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        return true;
    }
    $codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
    if ($codigo !== '' && strcasecmp(trim((string) $fila['cod_prestador']), $codigo) === 0) {
        return true;
    }
    $remitente = isset($fila['remitente_mail']) ? strtolower((string) $fila['remitente_mail']) : '';
    $destinatarios = isset($fila['destinatarios_mail']) ? strtolower((string) $fila['destinatarios_mail']) : '';
    foreach (correosLineasSesion() as $correo) {
        if (strpos($remitente, $correo) !== false || strpos($destinatarios, $correo) !== false) {
            return true;
        }
    }
    return false;
}

function completarPrestadorNotificaciones($pdo)
{
    try {
    $pendientes = $pdo->query(
        "SELECT id_notificacion, destinatarios_mail
         FROM notificaciones_historial
         WHERE (cod_prestador IS NULL OR TRIM(cod_prestador) = '')
           AND fecha_emision >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (count($pendientes) === 0) {
        return;
    }

    $mapa = array();
    $stmt = $pdo->query('SELECT TRIM(CODIGO) AS codigo, TRIM(NOMBRE) AS nombre, MAIL_AUTO, MAIL_DEB, MAIL_PAGO, MAILCONTRA FROM ebamp');
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $codigo = trim((string) $fila['codigo']);
        if ($codigo === '') {
            continue;
        }
        $lista = strtolower(str_replace(array('<', '>', '"'), ' ', $fila['MAIL_AUTO'] . ' ' . $fila['MAIL_DEB'] . ' ' . $fila['MAIL_PAGO'] . ' ' . $fila['MAILCONTRA']));
        foreach (preg_split('/[\s,;]+/', $lista) as $correo) {
            $correo = trim($correo);
            if ($correo === '' || strpos($correo, '@') === false) {
                continue;
            }
            if (!isset($mapa[$correo])) {
                $mapa[$correo] = array();
            }
            $mapa[$correo][$codigo] = trim((string) $fila['nombre']);
        }
    }

    $upd = $pdo->prepare(
        'UPDATE notificaciones_historial
         SET cod_prestador = :codigo, razon_social = :nombre
         WHERE id_notificacion = :id
           AND (cod_prestador IS NULL OR TRIM(cod_prestador) = \'\')'
    );
    foreach ($pendientes as $aviso) {
        $texto = strtolower(str_replace(array('<', '>', '"'), ' ', (string) $aviso['destinatarios_mail']));
        $unicos = array();
        foreach (preg_split('/[\s,;]+/', $texto) as $correo) {
            $correo = trim($correo);
            if ($correo === '' || strpos($correo, '@') === false || !isset($mapa[$correo]) || count($mapa[$correo]) !== 1) {
                continue;
            }
            $codigosCorreo = array_keys($mapa[$correo]);
            $codigoCorreo = $codigosCorreo[0];
            $unicos[$codigoCorreo] = $mapa[$correo][$codigoCorreo];
        }
        if (count($unicos) !== 1) {
            continue;
        }
        $codigo = key($unicos);
        $nombre = $unicos[$codigo];
        $upd->execute(array(
            ':codigo' => substr($codigo, 0, 20),
            ':nombre' => substr($nombre, 0, 100),
            ':id' => (int) $aviso['id_notificacion'],
        ));
    }
    } catch (Exception $e) {
        return;
    }
}

function prestadorPuedeVerCarpeta($pdo, $carpeta)
{
    if ($carpeta === '' || strcasecmp($carpeta, 'general') === 0 || esUsuarioLuis()) {
        return true;
    }
    $codigo = isset($_SESSION['codigo']) ? trim($_SESSION['codigo']) : '';
    $permitidos = codigosCarpetasPrestador($pdo, $codigo);
    foreach ($permitidos as $permitido) {
        if (strcasecmp($permitido, $carpeta) === 0) {
            return true;
        }
    }
    return false;
}
