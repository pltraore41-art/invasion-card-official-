<section class="page-head">
  <p class="eyebrow">Administration</p>
  <h1>Connexion à l'espace organisateur</h1>
</section>

<?php if (!$adminConfigured): ?>
  <div class="flash flash-error">
    L'accès administrateur n'est pas configuré. Définissez <code>ADMIN_PASSWORD</code> dans les variables d'environnement, puis redémarrez le serveur.
  </div>
<?php else: ?>
  <form method="post" class="form-grid auth-form">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Mot de passe</legend>
      <label>Mot de passe administrateur
        <input type="password" name="password" required autocomplete="current-password" placeholder="Entrez le mot de passe">
      </label>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Se connecter</button></div>
    </fieldset>
  </form>
<?php endif; ?>
