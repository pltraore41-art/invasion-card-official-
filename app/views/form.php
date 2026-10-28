<section class="page-head">
  <p class="eyebrow"><?= e(LAUNCH_EVENT) ?></p>
  <h1>Inscription à l'événement</h1>
  <p class="muted">Renseignez vos informations et préférences. Une référence et un billet QR seront créés après validation.</p>
</section>

<form class="form-grid" method="post" action="/inscriptions/nouvelle" id="registration-form">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Informations personnelles</legend>
    <div class="field-row">
      <label for="first_name">Prénom<input id="first_name" type="text" name="first_name" maxlength="120" autocomplete="given-name" required></label>
      <label for="last_name">Nom<input id="last_name" type="text" name="last_name" maxlength="120" autocomplete="family-name" required></label>
    </div>
    <div class="field-row">
      <label for="age">Âge<input id="age" type="number" name="age" min="0"></label>
      <label for="city">Ville ou commune<input id="city" type="text" name="city" maxlength="120" autocomplete="address-level2" required></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Participation à l'événement</legend>
    <div class="field-row">
      <label for="attendance">Présence
        <select id="attendance" name="attendance" required>
          <option value="">Choisir une réponse</option>
          <?php foreach (PRESENCE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label for="companions_count">Nombre de personnes qui vous accompagnent<input id="companions_count" type="number" name="companions_count" min="0" value="0"></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Préférences et objectif</legend>
    <div class="field-row">
      <label for="beverages">Préférence de boissons
        <select id="beverages" name="beverages" required>
          <option value="">Choisir une préférence</option>
          <?php foreach (BEVERAGE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label for="cardigan_knowledge">Connaissance du cardigan
        <select id="cardigan_knowledge" name="cardigan_knowledge" required>
          <option value="">Choisir une réponse</option>
          <?php foreach (KNOWLEDGE_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="field-row">
      <label for="intention">Objectif principal
        <select id="intention" name="intention" required>
          <option value="">Choisir un objectif</option>
          <?php foreach (INTENTION_CHOICES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label for="desired_size">Taille recherchée<input id="desired_size" type="text" name="desired_size" maxlength="30"></label>
    </div>
    <div class="field-row">
      <label for="preferred_color">Couleur ou modèle préféré<input id="preferred_color" type="text" name="preferred_color" maxlength="120"></label>
      <label for="budget">Budget envisagé<input id="budget" type="text" name="budget" maxlength="50"></label>
    </div>
    <label for="comment">Commentaire ou question<textarea id="comment" name="comment"></textarea></label>
  </fieldset>

  <div class="form-actions"><button type="submit" class="btn btn-primary">Valider mon inscription</button></div>
</form>
