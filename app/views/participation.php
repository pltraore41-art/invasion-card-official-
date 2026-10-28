<section class="page-head">
  <p class="eyebrow"><?= e(LAUNCH_EVENT) ?></p>
  <h1>Confirmez votre présence</h1>
  <p class="muted">Saisissez la référence de votre invitation et votre nom de famille pour confirmer ou mettre à jour votre réponse.</p>
</section>

<form method="post" class="form-grid">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Retrouver votre invitation</legend>
    <div class="field-row">
      <label for="reference">Référence de l'invitation<input id="reference" type="text" name="reference" autocomplete="off" required></label>
      <label for="last_name">Nom de famille<input id="last_name" type="text" name="last_name" autocomplete="family-name" required></label>
    </div>
  </fieldset>
  <fieldset>
    <legend>Votre réponse</legend>
    <div class="field-row">
      <label for="attendance">Présence
        <select id="attendance" name="attendance" required>
          <option value="">Choisir une réponse</option>
          <?php foreach (PRESENCE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label for="companions_count">Nombre d'accompagnants (si vous venez)<input id="companions_count" type="number" name="companions_count" min="0" value="0"></label>
    </div>
  </fieldset>
  <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer ma réponse</button></div>
</form>
