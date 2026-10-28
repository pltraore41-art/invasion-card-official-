<section class="page-head row-between">
  <div>
    <p class="eyebrow">Admin <?= e(BRAND_NAME) ?></p>
    <h1><?= e($registration['full_name']) ?></h1>
    <p class="muted">Référence : <?= e($registration['reference']) ?></p>
  </div>
  <div class="btn-group-inline">
    <button class="btn btn-primary no-print" type="button" onclick="window.print()">Imprimer la fiche</button>
    <a class="btn btn-secondary" href="/admin">Retour</a>
  </div>
</section>

<section class="print-sheet">
  <header class="print-sheet-header">
    <p><?= e(BRAND_NAME) ?> — <?= e(LAUNCH_EVENT) ?></p>
    <h1>Fiche participant</h1>
    <p>Référence : <?= e($registration['reference']) ?></p>
  </header>
  <section class="print-section">
    <h2>Informations personnelles</h2>
    <dl class="print-data">
      <dt>Prénom</dt><dd><?= e($registration['first_name']) ?></dd>
      <dt>Nom</dt><dd><?= e($registration['last_name']) ?></dd>
      <dt>Âge</dt><dd><?= e($registration['age'] ?? 'Non renseigné') ?></dd>
      <dt>Ville ou commune</dt><dd><?= e($registration['city'] ?: 'Non renseignée') ?></dd>
    </dl>
  </section>
  <section class="print-section">
    <h2>Participation</h2>
    <dl class="print-data">
      <dt>Présence</dt><dd><?= e($registration['presence_label']) ?></dd>
      <dt>Nombre d'accompagnants</dt><dd><?= e($registration['companions_count'] ?: 0) ?></dd>
    </dl>
  </section>
  <section class="print-section">
    <h2>Préférences et objectif</h2>
    <dl class="print-data">
      <dt>Boissons</dt><dd><?= e($registration['beverage_label']) ?></dd>
      <dt>Connaissance du cardigan</dt><dd><?= e($registration['knowledge_label']) ?></dd>
      <dt>Objectif principal</dt><dd><?= e($registration['intention_label']) ?></dd>
      <dt>Taille recherchée</dt><dd><?= e($registration['desired_size'] ?: 'Non renseignée') ?></dd>
      <dt>Couleur ou modèle préféré</dt><dd><?= e($registration['preferred_color'] ?: 'Non renseigné') ?></dd>
      <dt>Budget envisagé</dt><dd><?= e($registration['budget'] ?: 'Non renseigné') ?></dd>
      <dt>Commentaire ou question</dt><dd class="print-comment"><?= e($registration['comment'] ?: 'Aucun') ?></dd>
    </dl>
  </section>
  <footer class="print-sheet-footer">
    <p>Inscription créée le <?= e(format_datetime($registration['created_at'] ?? null)) ?></p>
    <p>Dernière mise à jour le <?= e(format_datetime($registration['updated_at'] ?? null)) ?></p>
  </footer>
</section>

<form method="post" class="form-grid no-print">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Informations personnelles</legend>
    <div class="field-row">
      <label>Prénom<input type="text" name="first_name" value="<?= e($registration['first_name']) ?>" required></label>
      <label>Nom<input type="text" name="last_name" value="<?= e($registration['last_name']) ?>" required></label>
    </div>
    <div class="field-row">
      <label>Âge<input type="number" name="age" min="0" value="<?= e($registration['age'] ?? '') ?>"></label>
      <label>Ville ou commune<input type="text" name="city" value="<?= e($registration['city'] ?? '') ?>" required></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Participation</legend>
    <div class="field-row">
      <label>Présence
        <select name="attendance">
          <?php foreach (PRESENCE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $registration['attendance'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Nombre de personnes qui accompagnent le participant<input type="number" min="0" name="companions_count" value="<?= e($registration['companions_count'] ?? 0) ?>" required></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Préférences</legend>
    <div class="field-row">
      <label>Boissons
        <select name="beverages">
          <?php foreach (BEVERAGE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $registration['beverages'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Connaissance du cardigan
        <select name="cardigan_knowledge">
          <?php foreach (KNOWLEDGE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $registration['cardigan_knowledge'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="field-row">
      <label>Objectif principal
        <select name="intention">
          <?php foreach (INTENTION_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $registration['intention'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Taille recherchée<input type="text" name="desired_size" value="<?= e($registration['desired_size'] ?? '') ?>"></label>
    </div>
    <div class="field-row">
      <label>Couleur / modèle préféré<input type="text" name="preferred_color" value="<?= e($registration['preferred_color'] ?? '') ?>"></label>
      <label>Budget envisagé<input type="text" name="budget" value="<?= e($registration['budget'] ?? '') ?>"></label>
    </div>
    <label>Commentaire ou question<textarea name="comment"><?= e($registration['comment'] ?? '') ?></textarea></label>
  </fieldset>

  <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer les modifications</button></div>
</form>

<form method="post" action="/admin/<?= rawurlencode($registration['reference']) ?>/delete" class="danger-form no-print">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-secondary" onclick="return confirm('Confirmer la suppression de cette inscription ?');">Supprimer l'inscription</button>
</form>
