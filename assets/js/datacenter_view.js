/**
 * Gestión de Contenedores de Servidores Mejorados
 */

class ServerCardManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.attachEventListeners();
        this.loadSavedStates();
    }
    
    /**
     * Adjunta todos los event listeners
     */
    attachEventListeners() {
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;

        // Toggle de acordeón de servidor
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.toggle-server-btn');
            if (toggleBtn) {
                e.stopPropagation();
                const card = toggleBtn.closest('.server-card');
                this.toggleServerCard(card);
            }
        });
        
        // Click en el header también expande/colapsa
        document.addEventListener('click', (e) => {
            const header = e.target.closest('.server-header');
            // Asegurarse de no interferir con los botones de acción
            if (header && !e.target.closest('.server-header-actions')) {
                const card = header.closest('.server-card');
                this.toggleServerCard(card);
            }
        });
        
        // Tabs dentro de cada servidor
        document.addEventListener('click', (e) => {
            const tab = e.target.closest('.server-tab');
            if (tab) {
                this.switchTab(tab);
            }
        });
        
        // Copiar credenciales
        document.addEventListener('click', async (e) => {
            const copyBtn = e.target.closest('.copy-cred-btn');
            if (copyBtn) {
                await this.copyCredential(copyBtn);
            }
        });

        // Manejar clicks en enlaces externos y controles globales
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="open-external"]');
            if (btn) {
                const url = btn.dataset.url;
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            }

            if (e.target.closest('.expand-all-btn')) {
                if (window.serverCardManager) {
                    window.serverCardManager.expandAll();
                }
            }

            if (e.target.closest('.collapse-all-btn')) {
                if (window.serverCardManager) {
                    window.serverCardManager.collapseAll();
                }
            }
        });

        // Botón principal "+ Servidor"
        document.getElementById('addServerBtn')?.addEventListener('click', () => {
            if (typeof showServerModal === 'function') {
                showServerModal(null);
            } else {
                console.error('La función showServerModal no está definida.');
            }
        });

        // Botones de acción rápida (Editar, Eliminar)
        document.addEventListener('click', async (e) => {
            const actionBtn = e.target.closest('.server-quick-action');
            if (!actionBtn) return;

            e.stopPropagation(); // Evitar que el header se active
            const serverId = actionBtn.dataset.serverId;
            const action = actionBtn.dataset.action;

            if (action === 'edit') {
                // Lógica para abrir el modal de edición
                // Esta función `showServerModal` debe existir globalmente o ser parte de otra clase
                if (typeof showServerModal === 'function') {
                    const response = await fetch(`api/datacenter.php?action=get_server_details&id=${serverId}`);
                    const result = await response.json();
                    if (result.success) {
                        showServerModal(result.server);
                    } else {
                        this.showNotification('Error al cargar datos del servidor: ' + result.message, 'error');
                    }
                } else {
                    console.error('La función showServerModal no está definida.');
                }
            } else if (action === 'delete') {
                const serverName = actionBtn.dataset.serverName;
                if (confirm(`¿Eliminar "${serverName}" y todos sus servicios? Esta acción no se puede deshacer.`)) {
                    try {
                        const response = await fetch('datacenter_view.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new URLSearchParams({
                                action: 'delete_server',
                                server_id: serverId,
                                csrf_token: csrfToken
                            })
                        });
                        const result = await response.json();
                        if (result.success) {
                            const cardToRemove = document.querySelector(`.server-card[data-server-id='${serverId}']`);
                            if (cardToRemove) {
                                cardToRemove.style.transition = 'opacity 0.5s, transform 0.5s';
                                cardToRemove.style.opacity = '0';
                                cardToRemove.style.transform = 'scale(0.9)';
                                setTimeout(() => cardToRemove.remove(), 500);
                            }
                            this.showNotification('✅ ' + (result.message || 'Servidor eliminado correctamente'), 'success');
                        } else {
                            throw new Error(result.message);
                        }
                    } catch (error) {
                        this.showNotification('Error al eliminar el servidor: ' + error.message, 'error');
                    }
                }
            }
        });
    }
    
    /**
     * Expande o colapsa una card de servidor
     */
    toggleServerCard(card) {
        if (!card) return;
        
        const isExpanded = card.classList.contains('expanded');
        const body = card.querySelector('.server-body');
        
        if (isExpanded) {
            card.classList.remove('expanded');
            body.style.maxHeight = '0';
        } else {
            card.classList.add('expanded');
            body.style.maxHeight = body.scrollHeight + 'px';
        }
        
        this.saveCardState(card);
    }
    
    /**
     * Cambia entre tabs dentro de un servidor
     */
    switchTab(tabButton) {
        const card = tabButton.closest('.server-card');
        if (!card) return;
        
        const tabId = tabButton.dataset.tab;
        
        card.querySelectorAll('.server-tab').forEach(tab => tab.classList.remove('active'));
        card.querySelectorAll('.server-tab-content').forEach(content => content.classList.remove('active'));
        
        tabButton.classList.add('active');
        const content = card.querySelector(`#${tabId}`);
        if (content) {
            content.classList.add('active');
        }
        
        if (card.classList.contains('expanded')) {
            const body = card.querySelector('.server-body');
            body.style.maxHeight = body.scrollHeight + 'px';
        }
    }
    
    /**
     * Copia una credencial al portapapeles
     */
    async copyCredential(button) {
        const credId = button.dataset.id;
        const credType = button.dataset.type || 'dc_credential';
        const originalHTML = button.innerHTML;

        try {
            button.innerHTML = '⏳';
            button.disabled = true;
            
            const response = await fetch(`api/datacenter.php?action=get_password&id=${credId}&type=${credType}`);
            const data = await response.json();
            
            if (data.success && data.password) {
                // Usar la API del portapapeles si está disponible (contexto seguro: HTTPS)
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(data.password);
                    button.innerHTML = '✓';
                    this.showNotification('Contraseña copiada al portapapeles', 'success');
                } else {
                    // Fallback para contextos no seguros (HTTP)
                    const textArea = document.createElement('textarea');
                    textArea.value = data.password;
                    textArea.style.position = 'absolute';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        button.innerHTML = '✓';
                        this.showNotification('Contraseña copiada (método de respaldo)', 'success');
                    } catch (err) {
                        console.error('Fallback: Error al copiar', err);
                        throw new Error('No se pudo copiar la contraseña');
                    } finally {
                        document.body.removeChild(textArea);
                    }
                }
            } else {
                throw new Error(data.message || 'Error al obtener contraseña');
            }
            
        } catch (error) {
            console.error('Error al copiar credencial:', error);
            button.innerHTML = '✗';
            this.showNotification('Error al copiar: ' + error.message, 'error');
        } finally {
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 2000);
        }
    }
    
    /**
     * Guarda el estado de una card en localStorage
     */
    saveCardState(card) {
        const serverId = card.dataset.serverId;
        if (!serverId) return;
        
        const isExpanded = card.classList.contains('expanded');
        const states = JSON.parse(localStorage.getItem('serverCardStates') || '{}');
        states[serverId] = isExpanded;
        localStorage.setItem('serverCardStates', JSON.stringify(states));
    }
    
    /**
     * Carga estados guardados de las cards
     */
    loadSavedStates() {
        const states = JSON.parse(localStorage.getItem('serverCardStates') || '{}');
        
        Object.keys(states).forEach(serverId => {
            if (states[serverId]) {
                const card = document.querySelector(`.server-card[data-server-id="${serverId}"]`);
                if (card) {
                    card.classList.add('expanded');
                    const body = card.querySelector('.server-body');
                    if (body) {
                        body.style.maxHeight = body.scrollHeight + 'px';
                    }
                }
            }
        });
    }
    
    /**
     * Muestra una notificación temporal
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('slide-out');
            notification.addEventListener('animationend', () => {
                notification.remove();
            });
        }, 3000);
    }
    
    expandAll() {
        document.querySelectorAll('.server-card:not(.expanded)').forEach(card => this.toggleServerCard(card));
    }
    
    collapseAll() {
        document.querySelectorAll('.server-card.expanded').forEach(card => this.toggleServerCard(card));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.serverCardManager = new ServerCardManager();
});

/**
 * Muestra el modal para agregar o editar un servidor.
 * Esta función ahora es global para ser accesible por ServerCardManager.
 * @param {object|null} serverData - Los datos del servidor para editar, o null para crear uno nuevo.
 */
function showServerModal(serverData = null) {
    const modal = document.getElementById('serverModal');
    const form = document.getElementById('serverForm');
    if (!modal || !form) {
        console.error('El modal o el formulario del servidor no se encontraron en el DOM.');
        return;
    }

    form.reset();
    document.getElementById('servicesContainer').innerHTML = ''; // Limpiar servicios

    // Resetear pestañas a la primera
    const firstTab = modal.querySelector('.modal-tab');
    if (firstTab) {
        // El nuevo ModalManager se encarga de esto, pero podemos forzarlo si es necesario.
        modalManager.switchTab(firstTab);
    }

    if (serverData) {
        // Editar
        modal.querySelector('.modal-title').textContent = 'Editar Servidor';
        document.getElementById('serverId').value = serverData.id;
        document.getElementById('serverLocation').value = serverData.location_id || '';
        document.getElementById('serverLabel').value = serverData.label || '';
        document.getElementById('serverType').value = serverData.type || 'physical';
        document.getElementById('serverStatus').value = serverData.status || 'active';
        document.getElementById('hwModel').value = serverData.hw_model || '';
        document.getElementById('hwCpu').value = serverData.hw_cpu || '';
        document.getElementById('hwRam').value = serverData.hw_ram || '';
        document.getElementById('hwDisk').value = serverData.hw_disk || '';
        document.getElementById('netIpLan').value = serverData.net_ip_lan || '';
        document.getElementById('netIpWan').value = serverData.net_ip_wan || '';
        document.getElementById('netDns').value = Array.isArray(serverData.net_dns) ? serverData.net_dns.join(', ') : '';
        document.getElementById('netHostExt').value = serverData.net_host_external || '';
        document.getElementById('netGateway').value = serverData.net_gateway || '';
        document.getElementById('serverNotes').value = serverData.notes || '';
        document.getElementById('serverUsername').value = serverData.username || '';
        document.getElementById('serverPassword').value = ''; // No rellenar por seguridad

        // Poblar servicios (si la función existe)
        if (typeof createServiceElement === 'function') {
            const servicesContainer = document.getElementById('servicesContainer');
            (serverData.services || []).forEach(service => {
                servicesContainer.appendChild(createServiceElement(service));
            });
        }
    } else {
        // Crear
        modal.querySelector('.modal-title').textContent = 'Agregar Servidor';
        document.getElementById('serverId').value = 'new_' + Date.now();
    }
    
    modalManager.open('serverModal');
}

/**
 * Lógica para la creación dinámica de servicios y credenciales dentro del modal.
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('serverModal');
    if (!modal) return;

    // Botón para agregar un nuevo servicio al modal
    document.getElementById('addServiceModalBtn')?.addEventListener('click', function() {
        const servicesContainer = document.getElementById('servicesContainer');
        if (servicesContainer) {
            servicesContainer.appendChild(createServiceElement());
        }
    });

    // Delegación de eventos para los botones de agregar/eliminar credenciales y servicios
    modal.addEventListener('click', function(e) {
        // Eliminar un servicio
        if (e.target.classList.contains('delete-service-btn')) {
            if (confirm('¿Eliminar este servicio? Los cambios se aplicarán al guardar.')) {
                e.target.closest('.dynamic-item-container').remove();
            }
        }

        // Agregar una credencial a un servicio
        if (e.target.classList.contains('add-credential-btn')) {
            const serviceContainer = e.target.closest('.dynamic-item-container');
            const serviceId = serviceContainer.querySelector('input[type=hidden]').name.match(/\[(.*?)\]/)[1];
            const credList = serviceContainer.querySelector('.credentials-list-dynamic');
            if (credList) {
                credList.appendChild(createCredentialElement({}, serviceId));
            }
        }

        // Eliminar una credencial
        if (e.target.classList.contains('credential-delete-btn')) {
            e.target.closest('.credential-item-dynamic').remove();
        }
    });

    // ============================================================================
    // VALIDACIÓN INTELIGENTE CON PESTAÑAS
    // ============================================================================
    const serverForm = document.getElementById('serverForm');
    if (serverForm) {
        // Escuchar evento 'invalid' en cualquier campo del formulario, usando fase de captura.
        serverForm.addEventListener('invalid', function(e) {
            const invalidField = e.target;
            const tabContent = invalidField.closest('.modal-tab-content');
            
            if (tabContent && !tabContent.classList.contains('active')) {
                // Prevenir el mensaje de error nativo temporalmente
                e.preventDefault();
                
                const tabButton = document.querySelector(`.modal-tab[data-tab="${tabContent.id}"]`);
                
                if (tabButton) {
                    // Cambiar a la pestaña correcta
                    modalManager.switchTab(tabButton);
                    
                    // Efecto visual opcional
                    tabContent.style.animation = 'highlight 0.7s ease';

                    // Después de un breve delay, enfocar el campo y mostrar el error
                    setTimeout(() => {
                        invalidField.focus();
                        invalidField.reportValidity();
                        // Limpiar la animación para futuras validaciones
                        setTimeout(() => {
                            tabContent.style.animation = '';
                        }, 700);
                    }, 150);
                }
            }
        }, true);
    }
});

/**
 * Crea y devuelve un elemento HTML para un servicio.
 * @param {object} serviceData - Datos del servicio para pre-rellenar el formulario.
 * @returns {HTMLElement} El elemento div del servicio.
 */
function createServiceElement(serviceData = {}) {
    const serviceId = serviceData.id || 'new_svc_' + Date.now();
    const div = document.createElement('div');
    div.className = 'dynamic-item-container';
    div.innerHTML = `
        <div class="form-grid">
            <input type="hidden" name="services[${serviceId}][id]" value="${serviceData.id || ''}" form="serverForm">
            <input type="text" name="services[${serviceId}][name]" placeholder="Nombre del Servicio" value="${serviceData.name || ''}" required form="serverForm">
            <input type="text" name="services[${serviceId}][port]" placeholder="Puerto" value="${serviceData.port || ''}" form="serverForm">
            <select name="services[${serviceId}][protocol]" form="serverForm">
                <option value="https" ${serviceData.protocol === 'https' ? 'selected' : ''}>HTTPS</option>
                <option value="http" ${serviceData.protocol === 'http' ? 'selected' : ''}>HTTP</option>
                <option value="ssh" ${serviceData.protocol === 'ssh' ? 'selected' : ''}>SSH</option>
                <option value="rdp" ${serviceData.protocol === 'rdp' ? 'selected' : ''}>RDP</option>
                <option value="other" ${serviceData.protocol === 'other' ? 'selected' : ''}>Otro</option>
            </select>
        </div>
        <div class="form-grid"><input type="text" name="services[${serviceId}][url_internal]" placeholder="URL Interna (LAN)" value="${serviceData.url_internal || ''}" form="serverForm"><input type="text" name="services[${serviceId}][url_external]" placeholder="URL Externa (WAN)" value="${serviceData.url_external || ''}" form="serverForm"></div>
        <textarea name="services[${serviceId}][notes]" placeholder="Notas del servicio..." form="serverForm">${serviceData.notes || ''}</textarea>
        <div class="credentials-sub-container"><h5>🔑 Credenciales del Servicio</h5><div class="credentials-list-dynamic"></div><button type="button" class="add-btn add-credential-btn">+ Agregar Credencial</button></div>
        <button type="button" class="delete-btn delete-service-btn">Eliminar Servicio</button>
    `;
    const credContainer = div.querySelector('.credentials-list-dynamic');
    (serviceData.credentials || []).forEach(cred => credContainer.appendChild(createCredentialElement(cred, serviceId)));
    return div;
}

function createCredentialElement(credData = {}, serviceId) {
    const credId = credData.id || 'new_cred_' + Date.now();
    const div = document.createElement('div');
    div.className = 'form-grid credential-item-dynamic';
    div.dataset.credentialId = credId;
    
    div.innerHTML = `
        <input type="hidden" name="services[${serviceId}][credentials][${credId}][id]" value="${credData.id || ''}" form="serverForm">
        <input type="text" name="services[${serviceId}][credentials][${credId}][username]" placeholder="Usuario" value="${credData.username || ''}" required autocomplete="username" form="serverForm">
        <input type="password" name="services[${serviceId}][credentials][${credId}][password]" placeholder="${credData.id ? 'Nueva Contraseña (dejar vacío para mantener)' : 'Contraseña'}" ${credData.id ? '' : 'required'} autocomplete="new-password" form="serverForm">
        <input type="text" name="services[${serviceId}][credentials][${credId}][role]" placeholder="Rol (ej: admin)" value="${credData.role || ''}" autocomplete="organization-title" form="serverForm">
        <button type="button" class="delete-btn credential-delete-btn">✕</button>
    `;
    return div;
}