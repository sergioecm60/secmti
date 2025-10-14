/**
 * Sistema de Modales Reutilizable
 * Maneja la apertura, cierre y comportamiento de modales
 */
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.init();
    }
    
    init() {
        // Manejar clicks en botones de cerrar
        document.addEventListener('click', (e) => {
            const closeBtn = e.target.closest('[data-modal-close]');
            if (closeBtn) {
                const modalId = closeBtn.dataset.modalClose;
                this.close(modalId);
            }
            
            // Manejar click en overlay (backdrop)
            const overlay = e.target.closest('.modal-overlay');
            if (overlay && e.target === overlay) {
                const backdropClose = overlay.dataset.backdropClose !== 'false';
                if (backdropClose) {
                    this.close(overlay.id);
                }
            }
        });
        
        // Manejar tabs
        document.addEventListener('click', (e) => {
            const tab = e.target.closest('.modal-tab');
            if (tab) {
                this.switchTab(tab);
            }
        });
        
        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close(this.activeModal);
            }
        });
    }
    
    /**
     * Abre un modal
     * @param {string} modalId - ID del modal a abrir
     * @param {Object} options - Opciones adicionales
     */
    open(modalId, options = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error(`Modal con ID "${modalId}" no encontrado`);
            return;
        }
        
        // Cerrar modal activo si existe
        if (this.activeModal && this.activeModal !== modalId) {
            this.close(this.activeModal);
        }
        
        // Abrir modal
        modal.classList.add('active');
        this.activeModal = modalId;
        document.body.style.overflow = 'hidden';
        
        // Callback onOpen
        if (options.onOpen && typeof options.onOpen === 'function') {
            options.onOpen(modal);
        }
        
        // Event personalizado
        modal.dispatchEvent(new CustomEvent('modal:opened', { detail: options }));
    }
    
    /**
     * Cierra un modal
     * @param {string} modalId - ID del modal a cerrar
     * @param {Object} options - Opciones adicionales
     */
    close(modalId, options = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.remove('active');
        
        if (this.activeModal === modalId) {
            this.activeModal = null;
            document.body.style.overflow = '';
        }
        
        // Callback onClose
        if (options.onClose && typeof options.onClose === 'function') {
            options.onClose(modal);
        }
        
        // Event personalizado
        modal.dispatchEvent(new CustomEvent('modal:closed', { detail: options }));
    }
    
    /**
     * Alterna entre tabs dentro de un modal
     * @param {HTMLElement} tabButton - Botón de tab clickeado
     */
    switchTab(tabButton) {
        const tabId = tabButton.dataset.tab;
        const modal = tabButton.closest('.modal-container');
        
        if (!modal) return;
        
        // Desactivar todos los tabs y contenidos
        modal.querySelectorAll('.modal-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        modal.querySelectorAll('.modal-tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Activar tab y contenido seleccionado
        tabButton.classList.add('active');
        const content = document.getElementById(tabId);
        if (content) {
            content.classList.add('active');
        }
    }
}

// Instancia global
const modalManager = new ModalManager();