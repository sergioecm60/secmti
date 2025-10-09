document.addEventListener('DOMContentLoaded', function () {
    // Toggle colapsar servidor
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.toggle-server-btn');
        if (toggleBtn) {
            const card = e.target.closest('.server-card');
            card.classList.toggle('collapsed');
            toggleBtn.textContent = card.classList.contains('collapsed') ? '▶' : '▼';
        }
    });

    // --- MANEJO DEL MODAL ---
    const serverModal = document.getElementById('serverModal');
    const addServerBtn = document.getElementById('addServerBtn');
    const closeBtn = serverModal.querySelector('.close');
    const cancelBtn = serverModal.querySelector('.cancel-btn');

    addServerBtn?.addEventListener('click', showServerModal);
    closeBtn?.addEventListener('click', closeServerModal);
    cancelBtn?.addEventListener('click', closeServerModal);

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target === serverModal) {
            closeServerModal();
        }
    }

    function showServerModal() {
        serverModal.classList.add('active');
        document.getElementById('modalTitle').textContent = 'Agregar Servidor';
        document.getElementById('serverForm').reset();
        document.getElementById('serverId').value = 'new_' + Date.now();
        const servicesContainer = document.getElementById('servicesContainer');
        servicesContainer.innerHTML = '';
        document.getElementById('addServiceToModalBtn').classList.remove('hidden');
    }

    function closeServerModal() {
        serverModal.classList.remove('active');
    }

    // --- LÓGICA DE EDICIÓN Y CREACIÓN DINÁMICA ---

    window.editServer = async function(serverId) {
        try {
            const response = await fetch(`api/datacenter.php?action=get_server_details&id=${serverId}`);
            const result = await response.json();

            if (result.success && result.data) {
                const server = result.data;
                const form = document.getElementById('serverForm');
                form.reset();

                document.getElementById('modalTitle').textContent = 'Editar Servidor: ' + server.label;
                
                document.getElementById('serverId').value = server.id;
                document.getElementById('serverLabel').value = server.label;
                document.getElementById('serverLocation').value = server.location_id || '';
                document.getElementById('serverType').value = server.type;
                document.getElementById('hwModel').value = server.hw_model;
                document.getElementById('hwCpu').value = server.hw_cpu;
                document.getElementById('hwRam').value = server.hw_ram;
                document.getElementById('hwDisk').value = server.hw_disk;
                document.getElementById('netIpLan').value = server.net_ip_lan;
                document.getElementById('netIpWan').value = server.net_ip_wan;
                document.getElementById('netHostExt').value = server.net_host_external;
                document.getElementById('netGateway').value = server.net_gateway;
                document.getElementById('serverNotes').value = server.notes;

                const servicesContainer = document.getElementById('servicesContainer');
                servicesContainer.innerHTML = '<h3>⚙️ Servicios</h3>';
                server.services.forEach(service => {
                    servicesContainer.appendChild(createServiceElement(service, server.id));
                });

                document.getElementById('addServiceToModalBtn').classList.remove('hidden');
                serverModal.classList.add('active');
            } else {
                alert('Error: ' + (result.message || 'No se pudieron cargar los detalles del servidor.'));
            }
        } catch (error) {
            console.error('Error al cargar datos del servidor:', error);
            alert('Error de conexión al intentar cargar los detalles del servidor.');
        }
    }

    function createServiceElement(service = {}, serverId = null) {
        const serviceId = service.id || 'new_svc_' + Date.now();
        const div = document.createElement('div');
        div.className = 'service-item';
        div.innerHTML = `
            <input type="hidden" name="services[${serviceId}][id]" value="${service.id || ''}">
            <div class="service-header">
                <input type="text" name="services[${serviceId}][name]" placeholder="Nombre del servicio" value="${service.name || ''}" required>
                <button type="button" class="delete-service-btn">✕</button>
            </div>
            <div class="service-details">
                <input type="text" name="services[${serviceId}][url_internal]" placeholder="URL Interna" value="${service.url_internal || ''}">
                <input type="text" name="services[${serviceId}][url_external]" placeholder="URL Externa" value="${service.url_external || ''}">
                <div class="inline-fields">
                    <select name="services[${serviceId}][protocol]">
                        <option value="https" ${service.protocol === 'https' ? 'selected' : ''}>HTTPS</option>
                        <option value="http" ${service.protocol === 'http' ? 'selected' : ''}>HTTP</option>
                        <option value="ssh" ${service.protocol === 'ssh' ? 'selected' : ''}>SSH</option>
                        <option value="rdp" ${service.protocol === 'rdp' ? 'selected' : ''}>RDP</option>
                    </select>
                    <input type="text" name="services[${serviceId}][port]" placeholder="Puerto" value="${service.port || ''}">
                </div>
                <div class="credentials-list" data-service-id="${serviceId}">
                    <label>🔐 Credenciales</label>
                    ${(service.credentials || []).map(cred => createCredentialHtml(serviceId, cred, serverId)).join('')}
                    <button type="button" class="add-credential-btn">+ Credencial</button>
                </div>
            </div>
        `;
        return div;
    }

    function createCredentialHtml(serviceId, cred = {}, serverId = null) {
        const credId = cred.id || 'new_cred_' + Date.now();
        return `
            <div class="credential-item" data-server-id="${serverId || ''}">
                <input type="hidden" name="services[${serviceId}][credentials][${credId}][id]" value="${cred.id || ''}">
                <input type="text" name="services[${serviceId}][credentials][${credId}][username]" placeholder="Usuario" value="${cred.username || ''}" required>
                <input type="password" name="services[${serviceId}][credentials][${credId}][password]" placeholder="${cred.id ? 'Nueva contraseña (opcional)' : 'Contraseña (requerida)'}" autocomplete="new-password">
                <input type="text" name="services[${serviceId}][credentials][${credId}][role]" placeholder="Rol" value="${cred.role || 'user'}">
                <button type="button" class="delete-cred-btn">✕</button>
            </div>
        `;
    }

    document.getElementById('addServiceToModalBtn')?.addEventListener('click', function() {
        document.getElementById('servicesContainer').appendChild(createServiceElement(undefined, document.getElementById('serverId').value));
    });

    serverModal?.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-service-btn')) e.target.closest('.service-item').remove();
        if (e.target.classList.contains('delete-cred-btn')) e.target.closest('.credential-item').remove();
        if (e.target.classList.contains('add-credential-btn')) {
            const serviceId = e.target.closest('.credentials-list').dataset.serviceId;
            const serverId = e.target.closest('.credential-item')?.dataset.serverId || document.getElementById('serverId').value; // Get serverId from parent or current form
            const newCredHtml = createCredentialHtml(serviceId, undefined, serverId);
            e.target.insertAdjacentHTML('beforebegin', newCredHtml);
        }
    });

    // --- LÓGICA DE ELIMINACIÓN ---

    function createDeleteForm(action, idName, idValue) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Submit to the same page
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${APP_CONFIG.csrfToken}">
            <input type="hidden" name="action" value="${action}">
            <input type="hidden" name="${idName}" value="${idValue}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    window.deleteServer = function(serverId, serverName) {
        if (confirm(`¿Eliminar servidor "${serverName}"? Se eliminarán también sus servicios y credenciales.`)) {
            createDeleteForm('delete_server', 'server_id', serverId);
        }
    }

    window.deleteService = function(serviceId, serviceName) {
        if (confirm(`¿Eliminar servicio "${serviceName}"?`)) {
            createDeleteForm('delete_service', 'service_id', serviceId);
        }
    }

    window.deleteCredential = function(credId, username) {
        if (confirm(`¿Eliminar credencial de "${username}"?`)) {
            createDeleteForm('delete_credential', 'credential_id', credId);
        }
    }
});