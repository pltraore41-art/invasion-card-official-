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
      <label for="group_type">Vous venez seul(e) ou accompagné(e) ?
        <select id="group_type" name="group_type" required>
          <option value="alone">Seul(e)</option>
          <option value="accompanied">Accompagné(e)</option>
        </select>
      </label>
    </div>
    <div class="field-row">
      <label for="companions_count" id="companions-wrapper" class="hidden">Nombre de personnes qui vous accompagnent
        <input id="companions_count" type="number" name="companions_count" min="1" max="20" value="1" disabled>
      </label>
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
      <label for="desired_size">Taille de cardigan<input id="desired_size" type="text" name="desired_size" maxlength="30" placeholder="Ex. S, M, L, XL" required></label>
    </div>
    <div class="field-row">
      <label for="preferred_color">Couleur ou modèle préféré<input id="preferred_color" type="text" name="preferred_color" maxlength="120"></label>
      <div class="price-note"><strong>Prix unitaire : 15 000 F CFA</strong><span>L'achat n'est pas obligatoire pour participer.</span></div>
    </div>
    <div class="purchase-box hidden" id="purchase-box">
      <label for="cardigan_quantity">Quantité de cardigans envisagée
        <input id="cardigan_quantity" type="number" name="cardigan_quantity" min="1" max="20" value="1" inputmode="numeric" disabled>
      </label>
      <p class="purchase-total" id="purchase-total" aria-live="polite"></p>
    </div>
    <label for="comment">Commentaire ou question<textarea id="comment" name="comment"></textarea></label>
  </fieldset>

  <div class="form-actions"><button type="submit" class="btn btn-primary">Valider mon inscription</button></div>
</form>

<script>
  (() => {
    const intentionSelect = document.getElementById('intention');
    const purchaseBox = document.getElementById('purchase-box');
    const quantityInput = document.getElementById('cardigan_quantity');
    const attendanceSelect = document.getElementById('attendance');
    const groupTypeSelect = document.getElementById('group_type');
    const companionsWrapper = document.getElementById('companions-wrapper');
    const companionsInput = document.getElementById('companions_count');
    const purchaseTotal = document.getElementById('purchase-total');

    function updatePurchaseField() {
      const buying = intentionSelect.value === 'buy';
      purchaseBox.classList.toggle('hidden', !buying);
      quantityInput.disabled = !buying;
      quantityInput.required = buying;
      if (!buying) quantityInput.value = '0';
      else if (Number(quantityInput.value) < 1) quantityInput.value = '1';
      purchaseTotal.textContent = buying
        ? `Estimation indicative : ${(Number(quantityInput.value || 1) * 15000).toLocaleString('fr-FR')} F CFA`
        : '';
    }

    function updateCompanionsField() {
      const needsCount = attendanceSelect.value === 'present' && groupTypeSelect.value === 'accompanied';
      companionsWrapper.classList.toggle('hidden', !needsCount);
      companionsInput.disabled = !needsCount;
      companionsInput.required = needsCount;
      if (!needsCount) companionsInput.value = '0';
      else if (Number(companionsInput.value) < 1) companionsInput.value = '1';
    }

    intentionSelect.addEventListener('change', updatePurchaseField);
    quantityInput.addEventListener('input', updatePurchaseField);
    attendanceSelect.addEventListener('change', updateCompanionsField);
    groupTypeSelect.addEventListener('change', updateCompanionsField);
    updatePurchaseField();
    updateCompanionsField();
  })();
</script>
