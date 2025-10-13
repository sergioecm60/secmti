<?php
/**
 * templates/notes_section.php
 * Muestra la sección de notas para un servidor.
 * Se espera que la variable $server esté disponible en el scope.
 */
?>
<?php if (!empty($server['notes'])): ?>
<div class="info-row">
    <div class="info-label">📝 Notas</div>
    <small class="server-notes"><?= nl2br(htmlspecialchars($server['notes'])) ?></small>
</div>
<?php endif; ?>