<section class="page-head row-between">
  <div>
    <p class="eyebrow"><?= e(LAUNCH_EVENT) ?></p>
    <h1>Liste des inscriptions</h1>
    <p class="muted">Retrouvez les participants et leurs réponses à l'événement.</p>
  </div>
  <a class="btn btn-primary" href="/inscriptions/nouvelle">Nouvelle inscription</a>
</section>

<form class="filters" method="get" action="/inscriptions">
  <label for="q">Nom<input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Prénom ou nom"></label>
  <label for="ville">Ville<input id="ville" type="search" name="ville" value="<?= e($cityFilter) ?>"></label>
  <label for="presence">Présence
    <select id="presence" name="presence">
      <option value="">Toutes les réponses</option>
      <?php foreach (PRESENCE_FILTERS as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $currentPresence === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label for="intention">Objectif
    <select id="intention" name="intention">
      <option value="">Tous les objectifs</option>
      <?php foreach (INTENTION_CHOICES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $intentionFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label for="boisson">Boissons
    <select id="boisson" name="boisson">
      <option value="">Toutes les préférences</option>
      <?php foreach (BEVERAGE_CHOICES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $beverageFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn-primary">Filtrer</button>
</form>

<section class="panel panel-wide">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Référence</th><th>Nom</th><th>Ville</th><th>Présence</th><th>Accompagnants</th><th>Objectif</th><th>Boissons</th></tr></thead>
      <tbody>
        <?php foreach ($registrations as $registration): ?>
          <tr>
            <td><a href="/inscriptions/<?= rawurlencode($registration['reference']) ?>"><?= e($registration['reference']) ?></a></td>
            <td><?= e($registration['full_name']) ?></td>
            <td><?= e($registration['city'] ?: '—') ?></td>
            <td><span class="badge badge-<?= e($registration['attendance']) ?>"><?= e($registration['presence_label']) ?></span></td>
            <td><?= e($registration['attendance'] === 'present' ? $registration['companions_count'] : '—') ?></td>
            <td><?= e($registration['intention_label']) ?></td>
            <td><?= e($registration['beverage_label']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($registrations === []): ?>
          <tr><td colspan="7">Aucune inscription ne correspond à ces critères.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
