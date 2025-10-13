document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const modal = document.getElementById('serverModal');
    const form = document.getElementById('serverForm');

    // ========================================================================
    // UTILIDADES
    // ========================================================================

    /**
     * Copia texto al portapapeles (compatible con HTTP/HTTPS)
     */
    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'absolute';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }

    /**
     * Alterna el estado colapsado de una tarjeta de servidor
     */
    function toggleCard(card, forceState = null) {
        const body = card.querySelector('.server-body');
        const toggleBtn = card.querySelector('.toggle-server-btn');
        if (!toggleBtn) return; // Seguridad
        
        const isCollapsed = card.classList.contains('collapsed');
        const shouldExpand = forceState === 'expand' || (forceState === null && isCollapsed);

        if (shouldExpand) {
            card.classList.remove('collapsed');
            body.style.maxHeight = body.scrollHeight + 'px';
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.innerHTML = '▼';
        } else {
            card.classList.add('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'false');
            body.style.maxHeight = null;
            toggleBtn.innerHTML = '▶';
        }
    }

    /**
     * Alterna el estado de una sección de ubicación
     */
    function toggleSection(section) {
        const body = section.querySelector('.section-body');
        const button = section.querySelector('.section-toggle-btn');
        const isCollapsed = section.classList.contains('collapsed');

        if (isCollapsed) {
            section.classList.remove('collapsed');
            body.style.maxHeight = body.scrollHeight + 'px';
            button.setAttribute('aria-expanded', 'true');
        } else {
            section.classList.add('collapsed');
            body.style.maxHeight = null;
            button.setAttribute('aria-expanded', 'false');
        }
    }

    // ========================================================================
    // ACORDEÓN DE SERVIDORES
    // ========================================================================
    
    const serverCards = document.querySelectorAll('.server-card');
    
    serverCards.forEach(card => {
        const toggleBtn = card.querySelector('.toggle-server-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleCard(card);
            });
        }
    });

    // Expandir/Contraer todo
    document.getElementById('expandAllBtn')?.addEventListener('click', () => {
        serverCards.forEach(card => toggleCard(card, 'expand'));
        document.querySelectorAll('.service-section.collapsed').forEach(section => {
            section.classList.remove('collapsed');
            const body = section.querySelector('.section-body');
            body.style.maxHeight = body.scrollHeight + 'px';
        });
    });

    document.getElementById('collapseAllBtn')?.addEventListener('click', () => {
        serverCards.forEach(card => toggleCard(card, 'collapse'));
        document.querySelectorAll('.service-section').forEach(section => {
            section.classList.add('collapsed');
            const body = section.querySelector('.section-body');
            body.style.maxHeight = null;
        });
    });

    // ========================================================================
    // SECCIONES DE UBICACIÓN
    // ========================================================================
    
    document.querySelectorAll('.service-section .section-toggle-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSection(button.closest('.service-section'));
        });
    });

    // Auto-expandir secciones al cargar
    document.querySelectorAll('.service-section').forEach(section => {
        const body = section.querySelector('.section-body');
        if (body) {
            body.style.maxHeight = 'none';
            const realHeight = body.scrollHeight;
            body.style.maxHeight = realHeight + 'px';
        }
    });

    // ========================================================================
    // COPIAR CREDENCIALES
    // ========================================================================
    
    document.body.addEventListener('click', async (e) => {
        if (!e.target.classList.contains('copy-cred-btn')) return;

        const button = e.target;
        const credId = button.dataset.id;
        const credType = button.dataset.type || 'dc_credential';
        const originalText = button.textContent;
        
        if (!credId || isNaN(credId)) {
            console.error('ID de credencial inválido:', credId);
            button.textContent = '❌';
            setTimeout(() => button.textContent = originalText, 2000);
            return;
        }
        
        try {
            const response = await fetch(`api/datacenter.php?action=get_password&id=${credId}&type=${credType}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.success && typeof data.password === 'string' && data.password.length > 0) {
                await copyToClipboard(data.password);
                button.textContent = '✅';
            } else {
                throw new Error(data.message || 'Contraseña inválida');
            }
        } catch (error) {
            console.error('Error al copiar:', error);
            button.textContent = '❌';
            alert('Error al copiar la contraseña: ' + error.message);
        } finally {
            setTimeout(() => button.textContent = originalText, 2000);
        }
    });

    // ========================================================================
    // ELIMINAR SERVIDOR
    // ========================================================================
    
    document.body.addEventListener('click', async (e) => {
        if (!e.target.classList.contains('delete-btn')) return;

        const serverId = e.target.dataset.serverId;
        const serverName = e.target.dataset.serverName;

        if (!confirm(`¿Eliminar "${serverName}" y todos sus servicios? Esta acción no se puede deshacer.`)) {
            return;
        }

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
                
                showStatusMessage('success', '✅ ' + (result.message || 'Servidor eliminado correctamente'));
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            alert('Error al eliminar el servidor: ' + error.message);
        }
    });

    // ========================================================================
    // MODAL
    // ========================================================================
    
    function showStatusMessage(type, message) {
        const statusContainer = document.querySelector('.admin-header');
        if (!statusContainer) return;
        
        const msg = document.createElement('div');
        msg.className = `status-message ${type}`;
        msg.textContent = message;
        statusContainer.insertAdjacentElement('afterend', msg);
        setTimeout(() => msg.remove(), 4000);
    }

    window.showServerModal = (serverData = null) => {
        form.reset();
        document.getElementById('servicesContainer').innerHTML = ''; // Limpiar servicios

        if (serverData) {
            // Editar
            document.getElementById('modalTitle').textContent = 'Editar Servidor';
            document.getElementById('serverId').value = serverData.id;
            document.getElementById('serverLocation').value = serverData.location_id || '';
            document.getElementById('serverLabel').value = serverData.label || '';
            document.getElementById('serverType').value = serverData.type || 'physical';
            document.getElementById('hwModel').value = serverData.hw_model || '';
            document.getElementById('hwCpu').value = serverData.hw_cpu || '';
            document.getElementById('hwRam').value = serverData.hw_ram || '';
            document.getElementById('hwDisk').value = serverData.hw_disk || '';
            document.getElementById('netIpLan').value = serverData.net_ip_lan || '';
            document.getElementById('netIpWan').value = serverData.net_ip_wan || '';
            // Join the DNS array into a string for the input
            document.getElementById('netDns').value = Array.isArray(serverData.net_dns) ? 
                serverData.net_dns.join(', ') : '';
            document.getElementById('netHostExt').value = serverData.net_host_external || '';
            document.getElementById('netGateway').value = serverData.net_gateway || '';
            document.getElementById('serverNotes').value = serverData.notes || '';
            document.getElementById('serverUsername').value = serverData.username || '';
            // La contraseña no se rellena por seguridad, solo se puede establecer una nueva
            document.getElementById('serverPassword').value = '';

            // Poblar servicios
            const servicesContainer = document.getElementById('servicesContainer');
            (serverData.services || []).forEach(service => {
                servicesContainer.appendChild(createServiceElement(service));
            });
        } else {
            // Crear
            document.getElementById('modalTitle').textContent = 'Agregar Servidor';
            document.getElementById('serverId').value = 'new_' + Date.now();
        }
        
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    };

    window.closeServerModal = () => {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    };

    // Event listeners para cerrar modal
    modal.querySelector('.close')?.addEventListener('click', closeServerModal);
    modal.querySelector('.cancel-btn')?.addEventListener('click', closeServerModal);

    // Pestañas del modal
    modal.querySelector('.modal-tabs')?.addEventListener('click', (e) => {
        if (e.target.classList.contains('tab-link')) {
            switchTab(e.target.dataset.tab);
        }
    });

    // ========================================================================
    // BOTONES DE ACCIÓN
    // ========================================================================
    
    document.body.addEventListener('click', async (e) => {
        // Agregar servidor
        if (e.target.id === 'addServerBtn') {
            showServerModal(null);
        }

        // Agregar servicio rápido
        if (e.target.classList.contains('add-service-quick-btn')) {
            const editBtn = document.querySelector(`.edit-btn[data-server-id="${e.target.dataset.serverId}"]`);
            editBtn?.click();
            setTimeout(() => switchTab('tab-services'), 200);
        }

        // Editar servidor
        if (e.target.classList.contains('edit-btn')) {
            const serverId = e.target.dataset.serverId;
            try {
                const response = await fetch(`api/datacenter.php?action=get_server_details&id=${serverId}`);
                const result = await response.json();
                
                if (result.success) {
                    showServerModal(result.data);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                alert('Error al cargar datos del servidor: ' + error.message);
            }
        }
    });

    // ========================================================================
    // SERVICIOS Y CREDENCIALES DINÁMICOS
    // ========================================================================
    
    function createServiceElement(serviceData = {}) {
        const serviceId = serviceData.id || 'new_svc_' + Date.now();
        const div = document.createElement('div');
        div.className = 'dynamic-item-container';
        div.innerHTML = `
            <div class="form-grid">
                <input type="hidden" name="services[${serviceId}][id]" value="${serviceData.id || ''}">
                <input type="text" name="services[${serviceId}][name]" placeholder="Nombre del Servicio" 
                       value="${serviceData.name || ''}" required>
                <input type="text" name="services[${serviceId}][port]" placeholder="Puerto" 
                       value="${serviceData.port || ''}">
                <select name="services[${serviceId}][protocol]">
                    <option value="https" ${serviceData.protocol === 'https' ? 'selected' : ''}>HTTPS</option>
                    <option value="http" ${serviceData.protocol === 'http' ? 'selected' : ''}>HTTP</option>
                    <option value="ssh" ${serviceData.protocol === 'ssh' ? 'selected' : ''}>SSH</option>
                    <option value="rdp" ${serviceData.protocol === 'rdp' ? 'selected' : ''}>RDP</option>
                    <option value="other" ${serviceData.protocol === 'other' ? 'selected' : ''}>Otro</option>
                </select>
            </div>
            <div class="form-grid">
                <input type="text" name="services[${serviceId}][url_internal]" placeholder="URL Interna (LAN)" 
                       value="${serviceData.url_internal || ''}">
                <input type="text" name="services[${serviceId}][url_external]" placeholder="URL Externa (WAN)" 
                       value="${serviceData.url_external || ''}">
            </div>
            <textarea name="services[${serviceId}][notes]" placeholder="Notas del servicio...">${serviceData.notes || ''}</textarea>
            <div class="credentials-sub-container">
                <h5>🔑 Credenciales del Servicio</h5>
                <div class="credentials-list-dynamic"></div>
                <button type="button" class="add-btn add-credential-btn">+ Agregar Credencial</button>
            </div>
            <button type="button" class="delete-btn service-delete-btn">Eliminar Servicio</button>
        `;

        const credContainer = div.querySelector('.credentials-list-dynamic');
        (serviceData.credentials || []).forEach(cred => {
            credContainer.appendChild(createCredentialElement(cred, serviceId));
        });

        return div;
    }

    function createCredentialElement(credData = {}, serviceId) {
        const credId = credData.id || 'new_cred_' + Date.now();
        const div = document.createElement('div');
        div.className = 'form-grid credential-item-dynamic';
        div.innerHTML = `
            <input type="hidden" name="services[${serviceId}][credentials][${credId}][id]" value="${credData.id || ''}">
            <input type="text" name="services[${serviceId}][credentials][${credId}][username]" 
                   placeholder="Usuario" value="${credData.username || ''}" required>
            <input type="password" name="services[${serviceId}][credentials][${credId}][password]" 
                   placeholder="${credData.id ? 'Nueva Contraseña' : 'Contraseña'}" autocomplete="new-password">
            <input type="text" name="services[${serviceId}][credentials][${credId}][role]" 
                   placeholder="Rol (ej: admin)" value="${credData.role || ''}">
            <button type="button" class="delete-btn credential-delete-btn">✕</button>
        `;
        return div;
    }

    document.getElementById('addServiceModalBtn')?.addEventListener('click', () => {
        document.getElementById('servicesContainer').appendChild(createServiceElement());
    });

    document.getElementById('servicesContainer')?.addEventListener('click', (e) => {
        // Eliminar servicio
        if (e.target.classList.contains('service-delete-btn')) {
            if (confirm('¿Eliminar este servicio? Los cambios se aplicarán al guardar.')) {
                e.target.closest('.dynamic-item-container').remove();
            }
        }
        
        // Agregar credencial
        if (e.target.classList.contains('add-credential-btn')) {
            const serviceContainer = e.target.closest('.dynamic-item-container');
            const serviceId = serviceContainer.querySelector('input[type=hidden]').name.match(/\[(.*?)\]/)[1];
            serviceContainer.querySelector('.credentials-list-dynamic')
                .appendChild(createCredentialElement({}, serviceId));
        }
        
        // Eliminar credencial
        if (e.target.classList.contains('credential-delete-btn')) {
            e.target.closest('.credential-item-dynamic').remove();
        }
    });

    function switchTab(tabId) {
        const modalContent = modal.querySelector('.modal-content');
        
        // Ocultar todos los contenidos de pestañas
        modalContent.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Desactivar todos los enlaces de pestañas
        modalContent.querySelectorAll('.tab-link').forEach(link => {
            link.classList.remove('active');
        });

        // Mostrar el contenido y activar el enlace de la pestaña seleccionada
        modalContent.querySelector('#' + tabId).classList.add('active');
        modalContent.querySelector(`.tab-link[data-tab="${tabId}"]`).classList.add('active');
    }

    // ========================================================================
    // ENVÍO DE FORMULARIO (AJAX)
    // ========================================================================
    
    form?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const saveBtn = form.querySelector('.save-btn');
        const originalBtnText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = 'Guardando...';

        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorResult = await response.json();
                throw new Error(errorResult.message || 'Error del servidor');
            }
            
            window.location.reload();

        } catch (error) {
            alert('Error al guardar: ' + error.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
        }
    });
});