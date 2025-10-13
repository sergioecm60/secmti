<?php
/**
 * templates/credentials_list.php
 * Muestra la lista de credenciales para un servicio.
 * Se espera que la variable $service esté disponible en el scope.
 */
?>
<?php if (!empty($service['credentials'])): ?>
<div class="credentials-box">
    <?php foreach ($service['credentials'] as $cred): ?>
    <div class="cred-row">
        <span>
            👤 <strong><?= htmlspecialchars($cred['username']) ?></strong>
            <?php if (!empty($cred['role']) && strtolower($cred['role']) !== 'user'): ?>
            <small class="role-badge">(<?= htmlspecialchars($cred['role']) ?>)</small>
            <?php endif; ?>
        </span>
        <?php if (!empty($cred['password'])): // Mostrar el botón solo si hay una contraseña para copiar ?>
            <button type="button" class="copy-cred-btn" data-id="<?= $cred['id'] ?>" title="Copiar contraseña para <?= htmlspecialchars($cred['username']) ?>">📋</button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>