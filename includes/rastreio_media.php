<?php
/**
 * Helper para gerenciamento de fotos por código de rastreio.
 */

if (!defined('RASTREIO_UPLOAD_RELATIVE_DIR')) {
    define('RASTREIO_UPLOAD_RELATIVE_DIR', 'uploads/rastreios/');
}

if (!defined('RASTREIO_FOTO_ALLOWED_MIME')) {
    define('RASTREIO_FOTO_ALLOWED_MIME', [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ]);
}

if (!function_exists('rastreioUploadsRootDir')) {
    function rastreioUploadsRootDir(): string
    {
        return rtrim(__DIR__ . '/../uploads/', '/\\') . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('rastreioUploadsAbsoluteDir')) {
    function rastreioUploadsAbsoluteDir(): string
    {
        return rtrim(__DIR__ . '/../' . RASTREIO_UPLOAD_RELATIVE_DIR, '/\\') . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('ensureRastreioUploadsDir')) {
    function ensureRastreioUploadsDir(): string
    {
        $root = rastreioUploadsRootDir();
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $dir = rastreioUploadsAbsoluteDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $rootHtaccess = $root . '.htaccess';
        if (!file_exists($rootHtaccess)) {
            file_put_contents($rootHtaccess, "Options -Indexes\n");
        }

        $dirHtaccess = $dir . '.htaccess';
        if (!file_exists($dirHtaccess)) {
            $content = <<<HTACCESS
Options -Indexes
<FilesMatch "\\.(php|phtml|php5|php7|phar)$">
    Deny from all
</FilesMatch>
HTACCESS;
            file_put_contents($dirHtaccess, $content);
        }

        return $dir;
    }
}

if (!function_exists('resolveRastreioAbsolutePath')) {
    function resolveRastreioAbsolutePath(string $relative): string
    {
        $clean = str_replace(['\\'], '/', $relative);
        $clean = ltrim($clean, '/');
        if (strpos($clean, '..') !== false) {
            throw new InvalidArgumentException('Caminho de upload inválido.');
        }
        $root = rtrim(__DIR__ . '/../', '/\\') . DIRECTORY_SEPARATOR;
        return $root . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    }
}

if (!function_exists('handleRastreioFotoUpload')) {
    function handleRastreioFotoUpload(string $codigo, string $fieldName = 'foto_pedido'): array
    {
        if (empty($_FILES[$fieldName])) {
            return ['success' => true, 'path' => null, 'message' => null];
        }

        $file = $_FILES[$fieldName];
        if ($file['error'] === UPLOAD_ERR_NO_FILE || (int) $file['size'] === 0) {
            return ['success' => true, 'path' => null, 'message' => null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'path' => null, 'message' => 'Falha ao enviar a foto. Tente novamente.'];
        }

        $maxSize = (int) getConfig('UPLOAD_MAX_SIZE', 5242880);
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1048576, 2);
            return ['success' => false, 'path' => null, 'message' => "A foto excede o limite de {$maxMB} MB."];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mime || !array_key_exists($mime, RASTREIO_FOTO_ALLOWED_MIME)) {
            return ['success' => false, 'path' => null, 'message' => 'Formato inválido. Utilize JPG, PNG, WEBP ou GIF.'];
        }

        $ext = RASTREIO_FOTO_ALLOWED_MIME[$mime];
        $dir = ensureRastreioUploadsDir();

        $safeCodigo = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($codigo));
        if ($safeCodigo === '') {
            $safeCodigo = 'RASTREIO';
        }

        $suffix = bin2hex(random_bytes(5));

        $filename = $safeCodigo . '_' . date('YmdHis') . '_' . $suffix . '.' . $ext;
        $destination = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'path' => null, 'message' => 'Não foi possível salvar a foto enviada.'];
        }

        return [
            'success' => true,
            'path' => RASTREIO_UPLOAD_RELATIVE_DIR . $filename,
            'message' => null
        ];
    }
}

if (!function_exists('fetchRastreioFotoPath')) {
    function fetchRastreioFotoPath(PDO $pdo, string $codigo): ?string
    {
        $stmt = $pdo->prepare("SELECT arquivo FROM rastreios_midias WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && !empty($row['arquivo'])) ? $row['arquivo'] : null;
    }
}

if (!function_exists('getRastreioFoto')) {
    function getRastreioFoto(PDO $pdo, string $codigo): ?array
    {
        $relative = fetchRastreioFotoPath($pdo, $codigo);
        if (!$relative) {
            return null;
        }

        $absolute = resolveRastreioAbsolutePath($relative);
        if (!is_file($absolute)) {
            $stmt = $pdo->prepare("DELETE FROM rastreios_midias WHERE codigo = ?");
            $stmt->execute([$codigo]);
            return null;
        }

        return [
            'relative' => $relative,
            'absolute' => $absolute,
            'url' => $relative
        ];
    }
}

if (!function_exists('persistRastreioFoto')) {
    function persistRastreioFoto(PDO $pdo, string $codigo, string $relativePath): void
    {
        if ($codigo === '' || $relativePath === '') {
            return;
        }

        $anterior = fetchRastreioFotoPath($pdo, $codigo);
        $sql = "INSERT INTO rastreios_midias (codigo, arquivo, tipo) 
                VALUES (:codigo, :arquivo, 'foto')
                ON DUPLICATE KEY UPDATE arquivo = VALUES(arquivo), atualizado_em = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':arquivo' => $relativePath
        ]);

        if ($anterior && $anterior !== $relativePath) {
            deleteRastreioFotoFile($anterior);
        }
    }
}

if (!function_exists('removeRastreioFoto')) {
    function removeRastreioFoto(PDO $pdo, string $codigo): void
    {
        $anterior = fetchRastreioFotoPath($pdo, $codigo);
        $stmt = $pdo->prepare("DELETE FROM rastreios_midias WHERE codigo = ?");
        $stmt->execute([$codigo]);
        if ($anterior) {
            deleteRastreioFotoFile($anterior);
        }
    }
}

if (!function_exists('deleteRastreioFotoFile')) {
    function deleteRastreioFotoFile(string $relative): void
    {
        if ($relative === '') {
            return;
        }
        $absolute = resolveRastreioAbsolutePath($relative);
        $baseDir = realpath(ensureRastreioUploadsDir());
        $realFile = is_file($absolute) ? realpath($absolute) : false;
        if ($baseDir && $realFile && strpos($realFile, $baseDir) === 0) {
            @unlink($realFile);
        }
    }
}

