document.addEventListener('DOMContentLoaded', () => {
    const serverCards = document.querySelectorAll('.server-card');
    const expandAllBtn = document.getElementById('expandAllBtn');
    const collapseAllBtn = document.getElementById('collapseAllBtn');
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    // --- Lógica de Acordeón (Expandir/Contraer) ---
    const toggleCard = (card, forceState = null) => {
        const body = card.querySelector('.server-body');
        const toggleBtn = card.querySelector('.view-toggle-btn[aria-expanded]');
        const isCollapsed = card.classList.contains('collapsed');

        let shouldExpand = forceState === 'expand' || (forceState === null && isCollapsed);

        if (shouldExpand) {
            card.classList.remove('collapsed');
            body.style.maxHeight = body.scrollHeight + 'px'; // Permite que el contenido se expanda
            toggleBtn.setAttribute('aria-expanded', 'true');
        } else {
            card.classList.add('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'false');
            body.style.maxHeight = null; // Colapsa el contenido
        }
    };

    serverCards.forEach(card => {
        // Asignar el evento de toggle directamente al botón de expandir/contraer
        const toggleBtn = card.querySelector('.view-toggle-btn[aria-expanded]');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                toggleCard(card);
            });
        }
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            serverCards.forEach(card => toggleCard(card, 'expand'));
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            serverCards.forEach(card => toggleCard(card, 'collapse'));
        });
    }

    // --- Lógica para Secciones de Ubicación Colapsables ---
    document.querySelectorAll('.service-section .section-toggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const section = button.closest('.service-section');
            const body = section.querySelector('.section-body');
            const isCollapsed = section.classList.contains('collapsed');

            if (isCollapsed) {
                section.classList.remove('collapsed');
                body.style.maxHeight = body.scrollHeight + 'px';
            } else {
                section.classList.add('collapsed');
                body.style.maxHeight = null;
            }
        });
    });

    /**
     * Función robusta para copiar texto al portapapeles.
     * Usa la API moderna si está disponible (contexto seguro), si no, usa el método antiguo.
     * @param {string} text - El texto a copiar.
     */
    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            // Método moderno y seguro
            await navigator.clipboard.writeText(text);
        } else {
            // Método antiguo para contextos no seguros (HTTP en IPs locales)
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

    // --- Lógica de Copiar Contraseña ---
    document.body.addEventListener('click', async (e) => {
        if (e.target.classList.contains('copy-cred-btn')) {
            const button = e.target;
            const credId = button.dataset.id;
            const credType = button.dataset.type || 'dc_credential'; // Obtener el tipo de credencial
            const originalText = button.textContent;
            
            try {
                const response = await fetch(`api/datacenter.php?action=get_password&id=${credId}&type=${credType}`);
                const data = await response.json();

                if (data.success && typeof data.password === 'string' && data.password.length > 0) {
                    await copyToClipboard(data.password);
                    button.textContent = '✅';
                } else {
                    throw new Error(data.message || 'La contraseña recibida no es válida.');
                }
            } catch (error) {
                console.error('Error al copiar:', error);
                button.textContent = '❌';
            } finally {
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            }
        }
    });

    // --- Lógica de Eliminación ---
    document.body.addEventListener('click', async (e) => {
        if (e.target.classList.contains('delete-btn')) {
            const serverId = e.target.dataset.serverId;
            const serverName = e.target.dataset.serverName;

            if (confirm(`¿Estás seguro de que quieres eliminar el servidor "${serverName}" y todos sus servicios y credenciales? Esta acción no se puede deshacer.`)) {
                try {
                    const response = await fetch('datacenter_view.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest' // Para que PHP sepa que es AJAX
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
                        // Mostrar un mensaje de éxito temporal en la parte superior
                        const statusContainer = document.querySelector('.admin-header');
                        if (statusContainer) {
                            const successMsg = document.createElement('div');
                            successMsg.className = 'status-message success';
                            successMsg.textContent = '✅ ' + (result.message || 'Servidor eliminado correctamente.');
                            statusContainer.insertAdjacentElement('afterend', successMsg);
                            setTimeout(() => successMsg.remove(), 4000);
                        }
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    alert('Error al eliminar el servidor: ' + error.message);
                }
            }
        }
    });

    // --- Lógica del Modal (Crear/Editar) ---
    const modal = document.getElementById('serverModal');
    const form = document.getElementById('serverForm');

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
            document.getElementById('netDns').value = Array.isArray(serverData.net_dns) ? serverData.net_dns.join(', ') : '';
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
        modal.style.display = 'flex'; // Use display instead of class for simplicity
        setTimeout(() => modal.classList.add('active'), 10); // For opacity transition
    };

    window.closeServerModal = () => {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300); // Wait for transition to finish
    };

    // --- Event listeners for modal close buttons ---
    const closeBtn = modal.querySelector('.close');
    const cancelBtn = modal.querySelector('.cancel-btn');

    // --- Lógica para pestañas del modal ---
    modal.querySelector('.modal-tabs').addEventListener('click', (e) => {
        if (e.target.classList.contains('tab-link')) switchTab(e.target.dataset.tab);
    });

    if (closeBtn) closeBtn.addEventListener('click', closeServerModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeServerModal);

    // --- Event listeners for UI buttons ---
    document.body.addEventListener('click', async (e) => {
        // Global button to add a server
        if (e.target.id === 'addServerBtn') {
            showServerModal(null); // Call without data to create a new one
        }

        // Botón rápido para agregar servicio desde la tarjeta
        if (e.target.classList.contains('add-service-quick-btn')) {
            e.target.closest('.server-header-actions .edit-btn')?.click(); // Simula clic en editar
            setTimeout(() => switchTab('tab-services'), 200); // Cambia a la pestaña de servicios
        }

        if (e.target.classList.contains('edit-btn')) {
            const serverId = e.target.dataset.serverId;
            try {
                // Call our new API to get the details
                const response = await fetch(`api/datacenter.php?action=get_server_details&id=${serverId}`);
                const result = await response.json();
                if (result.success) {
                    showServerModal(result.data); // Show the modal with the received data
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                alert('Error loading server data: ' + error.message);
            }
        }
    });

    // --- Lógica para Servicios Dinámicos en el Modal ---
    function createServiceElement(serviceData = {}) {
        const serviceId = serviceData.id || 'new_svc_' + Date.now();
        const div = document.createElement('div');
        div.className = 'dynamic-item-container';
        div.innerHTML = `
            <div class="form-grid">
                <input type="hidden" name="services[${serviceId}][id]" value="${serviceData.id || ''}">
                <input type="text" name="services[${serviceId}][name]" placeholder="Nombre del Servicio" value="${serviceData.name || ''}" required>
                <input type="text" name="services[${serviceId}][port]" placeholder="Puerto" value="${serviceData.port || ''}">
                <select name="services[${serviceId}][protocol]">
                    <option value="https" ${serviceData.protocol === 'https' ? 'selected' : ''}>HTTPS</option>
                    <option value="http" ${serviceData.protocol === 'http' ? 'selected' : ''}>HTTP</option>
                    <option value="ssh" ${serviceData.protocol === 'ssh' ? 'selected' : ''}>SSH</option>
                    <option value="rdp" ${serviceData.protocol === 'rdp' ? 'selected' : ''}>RDP</option>
                    <option value="other" ${serviceData.protocol === 'other' ? 'selected' : ''}>Otro</option>
                </select>
            </div>
            <div class="form-grid">
                <input type="text" name="services[${serviceId}][url_internal]" placeholder="URL Interna (LAN)" value="${serviceData.url_internal || ''}">
                <input type="text" name="services[${serviceId}][url_external]" placeholder="URL Externa (WAN)" value="${serviceData.url_external || ''}">
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
            <input type="text" name="services[${serviceId}][credentials][${credId}][username]" placeholder="Usuario" value="${credData.username || ''}" required>
            <input type="password" name="services[${serviceId}][credentials][${credId}][password]" placeholder="${credData.id ? 'Nueva Contraseña' : 'Contraseña'}" autocomplete="new-password">
            <input type="text" name="services[${serviceId}][credentials][${credId}][role]" placeholder="Rol (ej: admin)" value="${credData.role || ''}">
            <button type="button" class="delete-btn credential-delete-btn">✕</button>
        `;
        return div;
    }

    document.getElementById('addServiceModalBtn').addEventListener('click', () => {
        document.getElementById('servicesContainer').appendChild(createServiceElement());
    });

    document.getElementById('servicesContainer').addEventListener('click', (e) => {
        if (e.target.classList.contains('service-delete-btn')) {
            if (confirm('¿Seguro que quieres eliminar este servicio? Los cambios se aplicarán al guardar.')) {
                e.target.closest('.dynamic-item-container').remove();
            }
        }
        if (e.target.classList.contains('add-credential-btn')) {
            const serviceContainer = e.target.closest('.dynamic-item-container');
            const serviceId = serviceContainer.querySelector('input[type=hidden]').name.match(/\[(.*?)\]/)[1];
            serviceContainer.querySelector('.credentials-list-dynamic').appendChild(createCredentialElement({}, serviceId));
        }
        if (e.target.classList.contains('credential-delete-btn')) {
            e.target.closest('.credential-item-dynamic').remove();
        }
    });

    function switchTab(tabId) {
        const modalContent = document.querySelector('#serverModal .modal-content');
        
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
});