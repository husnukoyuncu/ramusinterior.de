<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

// Without a trailing slash, relative asset URLs (admin.css, admin.js, logout.php)
// resolve against the wrong base and the page loses its styling/scripts.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestPath === '/admin') {
    header('Location: /admin/' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

require_login();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ramus Interior — Proje Yönetimi</title>
<link rel="icon" href="../favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Cormorant+Garamond:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<button class="mobile-toggle" type="button" aria-label="Menüyü aç/kapat"
        onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show');">
  <span class="icon">menu</span>
</button>
<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('show');"></div>
<div class="layout">

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">R</div>
      <div>
        <div class="brand-name">Ramus Interior</div>
        <div class="brand-sub">Proje Yönetimi</div>
      </div>
    </div>

    <nav class="nav">
      <div class="nav-section">WEB SİTESİ</div>
      <button class="nav-item" data-action="go-list" id="nav-projects">
        <span class="icon">view_list</span>
        <span style="flex:1;">Projeler</span>
        <span class="nav-badge" id="nav-projects-badge">0</span>
      </button>
      <button class="nav-item" data-action="go-categories" id="nav-categories">
        <span class="icon">sell</span>
        <span style="flex:1;">Kategoriler</span>
        <span class="nav-badge" id="nav-categories-badge">0</span>
      </button>
    </nav>

    <div class="site-status">
      <span class="status-dot"></span>
      <div style="flex:1;">
        <div class="status-name">ramusinterior.de</div>
        <div class="status-label">Yayında</div>
      </div>
      <a href="https://ramusinterior.de" target="_blank" rel="noopener"><span class="icon">open_in_new</span></a>
    </div>

    <a class="user-chip" href="logout.php" title="Çıkış yap" style="text-decoration:none;color:inherit;">
      <div class="user-avatar">RA</div>
      <div style="flex:1;">
        <div class="user-name">Yönetici</div>
        <div class="user-role">Çıkış yap</div>
      </div>
      <span class="icon" style="color:#9a9085;">logout</span>
    </a>
  </aside>

  <main class="main">
    <header class="topbar" id="topbar"></header>
    <div class="content" id="content"></div>
  </main>

</div>
<div id="toast-host"></div>
<script src="admin.js"></script>
</body>
</html>
