<?php
/**
 * templates/footer.php - Componente de pie de página reutilizable.
 */
?>
<footer class="footer">
    <strong><?= htmlspecialchars($config['footer']['line1'] ?? '') ?></strong><br>
    <div class="footer-contact-line">
        <span><?= htmlspecialchars($config['footer']['line2'] ?? '') ?></span>
        <?php if (!empty($config['footer']['whatsapp_number']) && !empty($config['footer']['whatsapp_svg_path'])): ?>
            <a href="https://wa.me/<?= htmlspecialchars($config['footer']['whatsapp_number']) ?>" target="_blank" rel="noopener noreferrer" class="footer-whatsapp-link" aria-label="Contactar por WhatsApp" tabindex="0">
                <svg class="icon" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="<?= htmlspecialchars($config['footer']['whatsapp_svg_path']) ?>"/></svg>
            </a>
        <?php endif; ?>
    </div>
    <a href="<?= htmlspecialchars($config['footer']['license_url'] ?? '#') ?>" target="_blank" rel="license">Términos y Condiciones</a>
</footer>