<section class="page-head row-between">
  <div>
    <p class="eyebrow"><?= e(LAUNCH_EVENT) ?></p>
    <h1>Inscription confirmée</h1>
    <p class="muted"><?= e($registration['full_name']) ?> — Référence : <?= e($registration['reference']) ?></p>
  </div>
  <span class="badge badge-lg badge-<?= e($registration['attendance']) ?>"><?= e($registration['presence_label']) ?></span>
</section>

<section class="panel">
  <h2>Votre billet d'accès</h2>
  <p>Conservez votre référence. Vous pouvez télécharger votre billet avec son QR code.</p>
  <div class="btn-group-inline">
    <a class="btn btn-primary" href="/inscriptions/<?= rawurlencode($registration['reference']) ?>/ticket.svg?download=1">Télécharger le billet QR</a>
    <a class="btn btn-secondary" href="/participation">Modifier ma présence</a>
    <a class="btn btn-secondary" href="/inscriptions">Voir les inscriptions</a>
  </div>
</section>

<section class="panel meta">
  <h2>Récapitulatif</h2>
  <dl class="detail-grid">
    <dt>Prénom et nom</dt><dd><?= e($registration['full_name']) ?></dd>
    <dt>Âge</dt><dd><?= e($registration['age'] ?? '—') ?></dd>
    <dt>Ville</dt><dd><?= e($registration['city'] ?: '—') ?></dd>
    <dt>Présence</dt><dd><?= e($registration['presence_label']) ?></dd>
    <dt>Accompagnants</dt><dd><?= e($registration['attendance'] === 'present' ? $registration['companions_count'] : '—') ?></dd>
    <dt>Boissons</dt><dd><?= e($registration['beverage_label']) ?></dd>
    <dt>Connaissance du cardigan</dt><dd><?= e($registration['knowledge_label']) ?></dd>
    <dt>Objectif</dt><dd><?= e($registration['intention_label']) ?></dd>
    <dt>Taille recherchée</dt><dd><?= e($registration['desired_size'] ?: '—') ?></dd>
    <dt>Couleur ou modèle</dt><dd><?= e($registration['preferred_color'] ?: '—') ?></dd>
    <dt>Budget</dt><dd><?= e($registration['budget'] ?: '—') ?></dd>
    <dt>Commentaire</dt><dd><?= e($registration['comment'] ?: '—') ?></dd>
  </dl>
</section>
