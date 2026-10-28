<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — <?= e(LAUNCH_EVENT) ?></title>
  <link rel="stylesheet" href="/static/style.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/">
        <img class="brand-logo" src="/static/invasion-logo.png" alt="<?= e(BRAND_NAME) ?>">
      </a>
      <nav class="nav">
        <a href="/">Accueil</a>
        <a href="/admin/login">Admin</a>
        <a class="btn btn-primary btn-sm" href="/inscriptions/nouvelle">S'inscrire</a>
      </nav>
    </div>
  </header>

  <main class="container main">
    <?php if ($flashes !== []): ?>
      <div class="flash-list">
        <?php foreach ($flashes as $message): ?>
          <div class="flash flash-<?= e($message['category'] ?? 'success') ?>"><?= e($message['message'] ?? '') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $contentHtml ?>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <p><strong><?= e(BRAND_NAME) ?></strong> — <?= e(BRAND_TAGLINE) ?></p>
      <p class="muted">Événement <?= e(LAUNCH_EVENT) ?> — inscriptions et suivi des participants.</p>
    </div>
  </footer>
</body>
</html>
