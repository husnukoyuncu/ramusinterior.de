<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = admin_config();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Constant-time-ish comparison for the username, real check is the hash.
    $userOk = hash_equals($cfg['username'], $username);
    $passOk = password_verify($password, $cfg['password_hash']);

    if ($userOk && $passOk) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Kullanıcı adı veya şifre yanlış.';
    usleep(400000); // slow down brute-force attempts a little
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş — Ramus Interior Panel</title>
<link rel="icon" href="../favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Cormorant+Garamond:wght@500;600&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;height:100%;}
  body{font-family:'Hanken Grotesk',system-ui,sans-serif;background:#f1ece4;color:#221f1a;display:flex;align-items:center;justify-content:center;}
  .box{width:340px;background:#fbf9f5;border:1px solid #e6e0d6;border-radius:16px;padding:32px 28px;box-shadow:0 12px 30px rgba(0,0,0,.06);}
  .brand{display:flex;align-items:center;gap:11px;margin-bottom:22px;}
  .brand-mark{width:34px;height:34px;border-radius:9px;background:#221f1a;color:#f1ece4;display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:21px;font-weight:600;flex:none;}
  .brand-name{font-family:'Cormorant Garamond',serif;font-size:19px;font-weight:600;}
  .brand-sub{font-size:10.5px;color:#9a9085;font-weight:600;letter-spacing:1px;text-transform:uppercase;}
  label{display:block;font-size:12.5px;font-weight:600;color:#6f675c;margin:14px 0 6px;}
  input{width:100%;padding:10px 12px;border:1px solid #e6e0d6;border-radius:9px;font-size:14px;background:#fff;}
  input:focus{outline:none;border-color:#9c7a54;}
  button{width:100%;margin-top:20px;padding:11px;border:none;border-radius:9px;background:#221f1a;color:#fbf9f5;font-size:14px;font-weight:600;cursor:pointer;}
  button:hover{background:#37322b;}
  .error{margin-top:14px;font-size:13px;color:#a8492f;background:#fbf6f4;border:1px solid #e7d3cc;padding:9px 11px;border-radius:8px;}
</style>
</head>
<body>
  <form class="box" method="post" autocomplete="off">
    <div class="brand">
      <div class="brand-mark">R</div>
      <div>
        <div class="brand-name">Ramus Interior</div>
        <div class="brand-sub">Proje Yönetimi</div>
      </div>
    </div>
    <label for="username">Kullanıcı adı</label>
    <input type="text" id="username" name="username" required autofocus>
    <label for="password">Şifre</label>
    <input type="password" id="password" name="password" required>
    <button type="submit">Giriş yap</button>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
  </form>
</body>
</html>
