<section class="page-head">
  <p class="eyebrow">Pointage — <?= e(BRAND_NAME) ?></p>
  <h1><?= e($registration['full_name']) ?></h1>
  <p class="muted">Référence : <?= e($registration['reference']) ?> · <?= e(EVENT_DATE) ?></p>
</section>

<section class="panel">
  <?php if ($checkedInAt !== null): ?>
    <h2>Participant déjà pointé</h2>
    <p>Pointage enregistré le <?= e(format_datetime($checkedInAt)) ?>.</p>
  <?php else: ?>
    <h2>Billet valide</h2>
    <p>Présence déclarée : <?= e($registration['presence_label']) ?>.</p>
    <?php if ($registration['attendance'] === 'present'): ?>
      <p>Nombre de personnes prévues : <?= (int) $registration['companions_count'] + 1 ?></p>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">Confirmer le pointage</button>
    </form>
  <?php endif; ?>

  <form method="post" action="/admin/<?= rawurlencode($registration['reference']) ?>/delete" class="danger-form">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-secondary" onclick="return confirm('Confirmer la suppression de ce participant ?');">Supprimer ce participant</button>
  </form>
</section>
