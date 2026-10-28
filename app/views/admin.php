<section class="page-head row-between">
  <div>
    <p class="eyebrow">Admin <?= e(BRAND_NAME) ?></p>
    <h1>Tableau de bord participants</h1>
    <p class="muted">Suivi des inscriptions et analyse commerciale pour l'événement du 14 novembre 2026.</p>
  </div>
  <div class="btn-group-inline">
    <a class="btn btn-secondary" href="/admin/export.csv">Exporter CSV</a>
    <a class="btn btn-secondary" href="/admin/logout">Déconnexion</a>
  </div>
</section>

<section class="stats-grid">
  <article class="stat-card"><span class="stat-value"><?= (int) $stats['total'] ?></span><span class="stat-label">Inscriptions</span></article>
  <article class="stat-card stat-ok"><span class="stat-value"><?= (int) $stats['present'] ?></span><span class="stat-label">Confirmés</span></article>
  <article class="stat-card stat-warn"><span class="stat-value"><?= (int) $stats['unsure'] ?></span><span class="stat-label">Hésitants</span></article>
  <article class="stat-card stat-danger"><span class="stat-value"><?= (int) $stats['absent'] ?></span><span class="stat-label">Absents</span></article>
  <article class="stat-card stat-info"><span class="stat-value"><?= (int) $stats['expected_attendees'] ?></span><span class="stat-label">Personnes attendues</span></article>
  <article class="stat-card"><span class="stat-value"><?= (int) $stats['potential_buyers'] ?></span><span class="stat-label">Acheteurs potentiels</span></article>
  <article class="stat-card"><span class="stat-value"><?= (int) $stats['discoverers'] ?></span><span class="stat-label">Souhaitent découvrir</span></article>
</section>

<section class="panel panel-wide">
  <div class="panel-head"><h2>Répartition des préférences de boissons</h2></div>
  <div class="mini-grid">
    <?php foreach (['alcohol' => 'Alcool', 'sweet' => 'Sucré', 'both' => 'Les deux', 'none' => 'Aucune'] as $key => $label): ?>
      <div class="mini-card"><strong><?= (int) ($stats['beverage_counts'][$key] ?? 0) ?></strong><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel panel-wide">
  <div class="panel-head"><h2>Répartition par ville</h2></div>
  <div class="cloud-list">
    <?php foreach ($stats['city_counts'] as $city => $count): ?>
      <span class="tag"><?= e($city) ?> (<?= (int) $count ?>)</span>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel panel-wide">
  <div class="panel-head"><h2>Niveau de connaissance du cardigan</h2></div>
  <div class="mini-grid">
    <?php foreach (['known' => 'Le connaît déjà', 'heard' => 'En a entendu parler', 'new' => 'Le découvre'] as $key => $label): ?>
      <div class="mini-card"><strong><?= (int) ($stats['knowledge_counts'][$key] ?? 0) ?></strong><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel panel-wide">
  <div class="panel-head"><h2>Liste des participants</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Référence</th><th>Nom</th><th>Ville</th><th>Présence</th><th>Objectif</th><th>Boissons</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($registrations as $registration): ?>
          <tr>
            <td><?= e($registration['reference']) ?></td>
            <td><?= e($registration['full_name']) ?></td>
            <td><?= e($registration['city']) ?></td>
            <td><span class="badge badge-<?= e($registration['attendance']) ?>"><?= e($registration['presence_label']) ?></span></td>
            <td><?= e($registration['intention_label']) ?></td>
            <td><?= e($registration['beverage_label']) ?></td>
            <td><a href="/admin/<?= rawurlencode($registration['reference']) ?>">Voir</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($registrations === []): ?>
          <tr><td colspan="7">Aucune inscription enregistrée.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
