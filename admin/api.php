<?php
/**
 * Ramus Interior admin panel API. Behind the session login in auth.php.
 */

declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$root       = dirname(__DIR__);
$dataDir    = $root . '/data';
$projectsFile   = $dataDir . '/projects.json';
$categoriesFile = $dataDir . '/categories.json';
$uploadsDir = $root . '/images/uploads';

const MAX_UPLOAD_BYTES = 8 * 1024 * 1024; // 8MB
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

function fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function ok($data = []): void {
    echo json_encode(array_merge(['status' => 'ok'], $data));
    exit;
}

function read_json_file(string $path) {
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    flock($fh, LOCK_SH);
    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function write_json_file(string $path, $data): void {
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
    $fh = fopen($tmp, 'w');
    if (!$fh) fail(500, 'Veri yazılamadı.');
    flock($fh, LOCK_EX);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    flock($fh, LOCK_UN);
    fclose($fh);
    rename($tmp, $path);
}

function clean_text(?string $v, int $maxLen = 500): string {
    $v = trim((string)$v);
    $v = strip_tags($v);
    if (function_exists('mb_substr')) $v = mb_substr($v, 0, $maxLen);
    return $v;
}

/** Minimal allowlist HTML sanitizer for rich-text content fields. */
function sanitize_html(?string $html): string {
    $html = (string)$html;
    $allowedTags = '<p><br><b><strong><i><em><u><h3><ul><ol><li><a>';
    $clean = strip_tags($html, $allowedTags);
    // Strip event handler attributes and javascript: links, keep only href on <a>.
    $clean = preg_replace('/<a\b[^>]*href=["\']?(javascript:[^"\'>\s]*)["\']?[^>]*>/i', '<a>', $clean);
    $clean = preg_replace_callback('/<a\b([^>]*)>/i', function ($m) {
        if (preg_match('/href\s*=\s*"([^"]*)"/i', $m[1], $hrefMatch) ||
            preg_match("/href\s*=\s*'([^']*)'/i", $m[1], $hrefMatch)) {
            $href = $hrefMatch[1];
            if (stripos($href, 'javascript:') === 0) return '<a>';
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" rel="noopener">';
        }
        return '<a>';
    }, $clean);
    $clean = preg_replace('/<(p|br|b|strong|i|em|u|h3|ul|ol|li)\b[^>]*>/i', '<$1>', $clean);
    return $clean;
}

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $map = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','ä'=>'a','ß'=>'ss'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'kategori';
}

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;

if ($method === 'GET') {
    if ($resource === 'projects') {
        ok(['projects' => read_json_file($projectsFile)]);
    }
    if ($resource === 'categories') {
        ok(['categories' => read_json_file($categoriesFile)]);
    }
    fail(404, 'Bilinmeyen kaynak.');
}

if ($method !== 'POST') {
    fail(405, 'Desteklenmeyen istek yöntemi.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'upload_image') {
    if (empty($_FILES['file'])) fail(400, 'Dosya bulunamadı.');
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) fail(400, 'Yükleme hatası.');
    if ($file['size'] > MAX_UPLOAD_BYTES) fail(400, 'Dosya çok büyük (maks. 8MB).');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(ALLOWED_MIME[$mime])) fail(400, 'Desteklenmeyen dosya türü.');

    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
    $ext = ALLOWED_MIME[$mime];
    $base = slugify(pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) fail(500, 'Dosya kaydedilemedi.');
    ok(['path' => 'images/uploads/' . $filename]);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];

if ($action === 'save_project') {
    $p = $body['project'] ?? null;
    if (!is_array($p)) fail(400, 'Geçersiz proje verisi.');

    $categories = read_json_file($categoriesFile);
    $validCats = array_column($categories, 'slug');
    $category = clean_text($p['category'] ?? '', 60);
    if (!in_array($category, $validCats, true)) fail(400, 'Geçersiz kategori.');

    $images = [];
    if (!empty($p['images']) && is_array($p['images'])) {
        foreach ($p['images'] as $img) {
            $img = (string)$img;
            // Only allow paths inside images/uploads to prevent arbitrary path injection.
            if (preg_match('#^images/uploads/[A-Za-z0-9._-]+$#', $img)) $images[] = $img;
        }
    }

    $content = [];
    $name = [];
    foreach (['de', 'en', 'tr', 'ru', 'ar'] as $lang) {
        $content[$lang] = sanitize_html($p['content'][$lang] ?? '');
        $nameVal = is_array($p['name'] ?? null) ? ($p['name'][$lang] ?? '') : '';
        $name[$lang] = clean_text($nameVal, 200);
    }
    if (!array_filter($name)) {
        // Legacy/plain-string name, or nothing entered at all.
        $fallback = is_string($p['name'] ?? null) ? clean_text($p['name'], 200) : '';
        $fallback = $fallback ?: 'Adsız Proje';
        foreach ($name as $lang => &$v) { $v = $fallback; }
        unset($v);
    } else {
        // Fill any language left blank with the German (default) value.
        foreach ($name as $lang => &$v) { if ($v === '') $v = $name['de']; }
        unset($v);
    }

    $projects = read_json_file($projectsFile);
    $id = isset($p['id']) && $p['id'] !== null ? (int)$p['id'] : null;

    $clean = [
        'id'       => $id,
        'category' => $category,
        'name'     => $name,
        'location' => clean_text($p['location'] ?? '', 200),
        'area'     => clean_text($p['area'] ?? '', 20),
        'year'     => clean_text($p['year'] ?? '', 10),
        'active'   => !empty($p['active']),
        'images'   => $images,
        'content'  => $content,
    ];

    if ($id === null) {
        $maxId = 0;
        foreach ($projects as $existing) $maxId = max($maxId, (int)$existing['id']);
        $clean['id'] = $maxId + 1;
        $projects[] = $clean;
    } else {
        $found = false;
        foreach ($projects as &$existing) {
            if ((int)$existing['id'] === $id) { $existing = $clean; $found = true; break; }
        }
        unset($existing);
        if (!$found) fail(404, 'Proje bulunamadı.');
    }

    write_json_file($projectsFile, $projects);
    ok(['project' => $clean]);
}

if ($action === 'delete_project') {
    $id = isset($body['id']) ? (int)$body['id'] : null;
    if ($id === null) fail(400, 'Geçersiz id.');
    $projects = read_json_file($projectsFile);
    $filtered = array_values(array_filter($projects, fn($p) => (int)$p['id'] !== $id));
    write_json_file($projectsFile, $filtered);
    ok();
}

if ($action === 'save_category') {
    $labelsIn = $body['labels'] ?? null;
    $labels = [];
    foreach (['de', 'en', 'tr', 'ru', 'ar'] as $lang) {
        $labels[$lang] = clean_text(is_array($labelsIn) ? ($labelsIn[$lang] ?? '') : '', 60);
    }
    if ($labels['de'] === '') fail(400, 'Almanca kategori adı boş olamaz.');
    foreach ($labels as $lang => &$v) { if ($v === '') $v = $labels['de']; }
    unset($v);

    $categories = read_json_file($categoriesFile);
    $editSlug = clean_text($body['slug'] ?? '', 60);

    if ($editSlug !== '') {
        // Editing an existing category: keep its slug, update labels only.
        $found = false;
        foreach ($categories as &$c) {
            if ($c['slug'] === $editSlug) {
                $c['labels'] = $labels;
                $c['label'] = $labels['de'];
                $found = true;
                $category = $c;
                break;
            }
        }
        unset($c);
        if (!$found) fail(404, 'Kategori bulunamadı.');
        write_json_file($categoriesFile, $categories);
        ok(['category' => $category]);
    }

    // Creating a new category.
    foreach ($categories as $c) {
        if (mb_strtolower($c['label']) === mb_strtolower($labels['de'])) ok(['category' => $c]);
    }
    $slug = slugify($labels['de']);
    $existingSlugs = array_column($categories, 'slug');
    $unique = $slug; $i = 2;
    while (in_array($unique, $existingSlugs, true)) { $unique = $slug . '-' . $i++; }
    $category = ['slug' => $unique, 'label' => $labels['de'], 'labels' => $labels];
    $categories[] = $category;
    write_json_file($categoriesFile, $categories);
    ok(['category' => $category]);
}

if ($action === 'delete_category') {
    $slug = clean_text($body['slug'] ?? '', 60);
    $projects = read_json_file($projectsFile);
    foreach ($projects as $p) {
        if ($p['category'] === $slug) fail(409, 'Kullanımda olan kategori silinemez.');
    }
    $categories = read_json_file($categoriesFile);
    $filtered = array_values(array_filter($categories, fn($c) => $c['slug'] !== $slug));
    write_json_file($categoriesFile, $filtered);
    ok();
}

fail(400, 'Bilinmeyen işlem.');
