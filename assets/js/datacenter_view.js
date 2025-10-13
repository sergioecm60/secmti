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

    // --- Lógica de Copiar Contraseña ---
    document.body.addEventListener('click', async (e) => {
        if (e.target.classList.contains('copy-cred-btn')) {
            const button = e.target;
            const credId = button.dataset.id; // Corrected from credId to id
            const originalText = button.textContent;
            
            try {
                const response = await fetch('api/credentials.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: credId, csrf_token: csrfToken })
                });
                const data = await response.json();

                if (data.success && data.password) {
                    await navigator.clipboard.writeText(data.password);
                    button.textContent = '✅';
                } else {
                    throw new Error(data.message || 'No se pudo obtener la contraseña.');
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

    if (closeBtn) closeBtn.addEventListener('click', closeServerModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeServerModal);

    // --- Event listeners for UI buttons ---
    document.body.addEventListener('click', async (e) => {
        // Global button to add a server
        if (e.target.id === 'addServerBtn') {
            showServerModal(null); // Call without data to create a new one
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
});