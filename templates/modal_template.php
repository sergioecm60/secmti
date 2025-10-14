<?php
/**
 * Template reutilizable para modales
 * 
 * @param array $config Configuración del modal
 * @return string HTML del modal
 */
function render_modal(array $config): string {
    // Valores por defecto
    $defaults = [
        'id' => 'genericModal',
        'title' => 'Modal',
        'size' => 'medium', // small, medium, large, xl
        'tabs' => [], // Array de tabs: ['id' => 'tab1', 'label' => 'Pestaña 1']
        'content' => '', // HTML del contenido
        'footer' => true, // Mostrar footer con botones
        'cancel_text' => 'Cancelar',
        'submit_text' => 'Guardar',
        'submit_button_class' => 'save-btn',
        'form_id' => null, // ID del formulario (si aplica)
        'closable' => true, // Permitir cerrar con X
        'backdrop_close' => true, // Cerrar al hacer clic fuera
        'extra_classes' => '', // Clases CSS adicionales
    ];
    
    $config = array_merge($defaults, $config);
    
    // Clase de tamaño
    $size_class = 'modal-' . $config['size'];
    
    // Generar HTML
    ob_start();
    ?>
    
    <!-- Modal: <?= htmlspecialchars($config['title']) ?> -->
    <div id="<?= htmlspecialchars($config['id']) ?>" 
         class="modal-overlay <?= htmlspecialchars($config['extra_classes']) ?>" 
         data-backdrop-close="<?= $config['backdrop_close'] ? 'true' : 'false' ?>">
        <div class="modal-container <?= htmlspecialchars($size_class) ?>">
            <!-- Modal Header -->
            <div class="modal-header">
                <h3 class="modal-title"><?= htmlspecialchars($config['title']) ?></h3>
                <?php if ($config['closable']): ?>
                <button type="button" class="modal-close" data-modal-close="<?= htmlspecialchars($config['id']) ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Modal Tabs (si existen) -->
            <?php if (!empty($config['tabs'])): ?>
            <div class="modal-tabs">
                <?php foreach ($config['tabs'] as $index => $tab): ?>
                <button type="button" 
                        class="modal-tab <?= $index === 0 ? 'active' : '' ?>" 
                        data-tab="<?= htmlspecialchars($tab['id']) ?>">
                    <?= htmlspecialchars($tab['label']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Modal Body -->
            <div class="modal-body">
                <?php if (!empty($config['tabs'])): ?>
                    <?php foreach ($config['tabs'] as $index => $tab): ?>
                    <div class="modal-tab-content <?= $index === 0 ? 'active' : '' ?>" 
                         id="<?= htmlspecialchars($tab['id']) ?>">
                        <?= $tab['content'] ?? '' ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?= $config['content'] ?>
                <?php endif; ?>
            </div>
            
            <!-- Modal Footer -->
            <?php if ($config['footer']): ?>
            <div class="modal-footer">
                <button type="button" 
                        class="btn cancel-btn" 
                        data-modal-close="<?= htmlspecialchars($config['id']) ?>">
                    <?= htmlspecialchars($config['cancel_text']) ?>
                </button>
                <button type="submit" 
                        class="btn <?= htmlspecialchars($config['submit_button_class']) ?>"
                        <?php if ($config['form_id']): ?>
                        form="<?= htmlspecialchars($config['form_id']) ?>"
                        <?php endif; ?>>
                    <?= htmlspecialchars($config['submit_text']) ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}