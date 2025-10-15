<?php
// templates/notes_section.php
if (!empty($server['notes'])): ?>
<div class="info-item info-item-spaced">
    <span class="info-label">📝 Notas</span>
    <div class="notes-box">
        <?= nl2br(htmlspecialchars($server['notes'])) ?>
    </div>
</div>
<?php endif; ?>