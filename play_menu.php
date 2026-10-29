<?php
require 'auth.php';      // starts the session and loads db.php
require_login();         // not logged in -> index.php

$stmt = db()->prepare("SELECT id, title, created_at FROM notes WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute([(int)$_SESSION['user_id']]);
$notes = $stmt->fetchAll();
?>
<!DOCTYPE html>
  <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Play a game</title>
    <style>
    body{font-family:system-ui,sans-serif;background:#f4f6fb;margin:0;padding:30px}
    .wrap{max-width:720px;margin:0 auto}
    .note{background:#fff;border-radius:10px;padding:14px 18px;margin:10px 0;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:0 1px 4px rgba(0,0,0,.08)}
    .note small{color:#888;display:block}
    .btns a{display:inline-block;margin:3px;padding:8px 14px;border-radius:6px;background:#00b894;color:#fff;text-decoration:none;font-size:14px}
    .btns a.b{background:#8faa7b}
    </style>
    </head>
    <body>
    <div class="wrap">
      <h1>🎮 Play a game from your notes</h1>
      <p><a href="studyhub.php">&larr; Back to home</a></p>
      <?php if (!$notes): ?>
        <p>You have no notes yet. Upload one first.</p>
      <?php endif; ?>
      <?php foreach ($notes as $n): ?>
        <div class="note">
          <div><strong><?= htmlspecialchars($n['title']) ?></strong><small><?= htmlspecialchars($n['created_at']) ?></small></div>
          <div class="btns">
            <a href="api/generate_games.php?note_id=<?= (int)$n['id'] ?>&t=A&go=1">🦠 Tile Match</a>
            <a class="b" href="api/generate_games.php?note_id=<?= (int)$n['id'] ?>&t=B&go=1">⌨️ Typing Game</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </body>
</html>