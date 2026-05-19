<?php
// Local-only router for `php -S localhost:8000 router.php`. Emulates the
// .htaccess rewrites used in production:
//   /api/*        -> /api/index.php?path=...
//   /uploads/*    -> served as a static file
//   /auth/callback -> /auth/callback.php
//   /directory    -> /directory.php (pretty URLs)
//   /claim, /dashboard, /admin, /institution   — likewise
//   /i/<brand>/<slug> -> /institution.php?brand=…&slug=…
// Apache on shared hosting uses the .htaccess files instead; this router is
// only for local development.

$ROOT = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// /api/*
if (preg_match('#^/api/?$#', $uri)) {
    $_GET['path'] = '';
    require $ROOT . '/api/index.php';
    return true;
}
if (preg_match('#^/api/(.+)$#', $uri, $m)) {
    $_GET['path'] = $m[1];
    require $ROOT . '/api/index.php';
    return true;
}

// /auth/callback
if (preg_match('#^/auth/callback/?$#', $uri)) {
    require $ROOT . '/auth/callback.php';
    return true;
}

// /i/<brand>/<slug>
if (preg_match('#^/i/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['brand'] = $m[1];
    $_GET['slug']  = $m[2];
    require $ROOT . '/institution.php';
    return true;
}

// Pretty URLs
$pretty = [
    '/'              => '/index.php',
    '/directory'     => '/directory.php',
    '/claim'         => '/claim.php',
    '/dashboard'     => '/dashboard.php',
    '/admin'         => '/admin.php',
    '/institution'   => '/institution.php',
    '/privacy'       => '/privacy.php',
    '/terms'         => '/terms.php',
    '/reset-password'=> '/reset-password.php',
    '/sitemap.xml'   => '/sitemap.xml.php',
    '/robots.txt'    => '/robots.txt',
];
$rtrim = rtrim($uri, '/');
if ($rtrim === '') $rtrim = '/';
if (isset($pretty[$rtrim])) {
    $target = $ROOT . $pretty[$rtrim];
    if (substr($target, -4) === '.php') {
        require $target; // PHP page — execute, not dump source.
    } else {
        // robots.txt and other static text files.
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = $ext === 'txt' ? 'text/plain; charset=utf-8' : 'text/html; charset=utf-8';
        header('Content-Type: ' . $mime);
        readfile($target);
    }
    return true;
}

// Real files — serve directly because php -S's static-file fallback
// uses the *wrong* document root when invoked with a router.
$file = $ROOT . $uri;
if (is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'js', 'mjs'   => 'application/javascript; charset=utf-8',
        'css'         => 'text/css; charset=utf-8',
        'svg'         => 'image/svg+xml',
        'png'         => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp'        => 'image/webp',
        'gif'         => 'image/gif',
        'json'        => 'application/json; charset=utf-8',
        'html', 'htm' => 'text/html; charset=utf-8',
        'pdf'         => 'application/pdf',
        'woff'        => 'font/woff',
        'woff2'       => 'font/woff2',
        'ico'         => 'image/x-icon',
        default       => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset=utf-8><title>404</title><h1>404 — not found</h1><p><a href="/">Back to home</a></p>';
return true;
