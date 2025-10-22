/**
 * hosting-manager.js - Lógica para Gestión de Hosting
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Modal HTML template
    const modalHTML = `
        <div id="hostModal" class="modal-overlay" aria-hidden="true">
            <div class="modal-container modal-large">
                <div class="modal-header">
                    <h2 class="modal-title">Gestionar Servidor de Hosting</h2>
                    <button type="button" class="modal-close" data-modal-close="hostModal" aria-label="Cerrar">&times;</button>
                </div>
                <form id="hostForm" method="POST" action="hosting_manager.php">
                    <input type="hidden" name="action" value="save_host">
                    <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                    <input type="hidden" name="host_id" id="hostId">
                    
                    <div class="modal-body">
                        <!-- Datos del Servidor -->
                        <fieldset class="modal-fieldset">
                            <legend>Datos del Servidor</legend>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="hostLabel">Etiqueta *</label>
                                    <input type="text" id="hostLabel" name="label" required>
                                </div>
                                <div class="form-group">
                                    <label for="hostHostname">Hostname *</label>
                                    <input type="text" id="hostHostname" name="hostname" required placeholder="ejemplo.com">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="hostCpanelPort">Puerto cPanel</label>
                                    <input type="number" id="hostCpanelPort" name="cpanel_port" value="2083">
                                </div>
                                <div class="form-group">
                                    <label for="hostWebmailPort">Puerto Webmail</label>
                                    <input type="number" id="hostWebmailPort" name="webmail_port" value="2096">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="hostNotes">Notas</label>
                                <textarea id="hostNotes" name="notes" rows="2"></textarea>
                            </div>
                        </fieldset>

                        <!-- Cuentas cPanel -->
                        <fieldset class="modal-fieldset">
                            <legend>👤 Cuentas cPanel</legend>
                            <div id="cpanelAccountsContainer"></div>
                            <button type="button" class="btn-add" id="addCpanelBtn">+ Agregar Cuenta cPanel</button>
                        </fieldset>

                        <!-- Cuentas FTP -->
                        <fieldset class="modal-fieldset">
                            <legend>🔒 Cuentas FTP</legend>
                            <div id="ftpAccountsContainer"></div>
                            <button type="button" class="btn-add" id="addFtpBtn">+ Agregar Cuenta FTP</button>
                        </fieldset>

                        <!-- Cuentas Email -->
                        <fieldset class="modal-fieldset">
                            <legend>✉️ Cuentas de Email</legend>
                            <div id="emailAccountsContainer"></div>
                            <button type="button" class="btn-add" id="addEmailBtn">+ Agregar Cuenta Email</button>
                        </fieldset>

                        <!-- Cuentas de Terminal Server -->
                        <fieldset class="modal-fieldset">
                            <legend>💻 Cuentas de Terminal Server</legend>
                            <div id="terminalServerAccountsContainer"></div>
                            <button type="button" class="btn-add" id="addTerminalServerBtn">+ Agregar Cuenta Terminal Server</button>
                        </fieldset>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" data-modal-close="hostModal">Cancelar</button>
                        <button type="submit" class="btn-primary">💾 Guardar Servidor</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Insertar modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('hostModal');
    const form = document.getElementById('hostForm');
    const hostIdInput = document.getElementById('hostId');

    // Contenedores dinámicos
    const cpanelContainer = document.getElementById('cpanelAccountsContainer');
    const ftpContainer = document.getElementById('ftpAccountsContainer');
    const emailContainer = document.getElementById('emailAccountsContainer');
    const terminalServerContainer = document.getElementById('terminalServerAccountsContainer');

    // Funciones para abrir/cerrar modal
    function openModal() {
        modalManager.open('hostModal');
    }

    function closeModal() {
        modalManager.close('hostModal');
    }

    // Función para crear campo de cuenta cPanel
    function createCpanelField(account = null) {
        const id = account ? account.id : 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const div = document.createElement('div');
        div.className = 'dynamic-item';
        div.innerHTML = `
            <div class="dynamic-item-header">
                <strong>Cuenta cPanel</strong>
                <button type="button" class="btn-remove remove-cpanel">🗑️ Eliminar</button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="cpanel_label_${id}">Etiqueta</label>
                    <input type="text" id="cpanel_label_${id}" name="cpanel_accounts[${id}][label]" value="${account ? account.label || '' : ''}" placeholder="Mi Sitio Web">
                </div>
                <div class="form-group">
                    <label for="cpanel_user_${id}">Usuario *</label>
                    <input type="text" id="cpanel_user_${id}" name="cpanel_accounts[${id}][username]" value="${account ? account.username : ''}" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="cpanel_pass_${id}">Contraseña ${account ? '(dejar vacío para no cambiar)' : '*'}</label>
                    <input type="password" id="cpanel_pass_${id}" name="cpanel_accounts[${id}][password]" ${account ? '' : 'required'}>
                </div>
                <div class="form-group">
                    <label for="cpanel_domain_${id}">Dominio</label>
                    <input type="text" id="cpanel_domain_${id}" name="cpanel_accounts[${id}][domain]" value="${account ? account.domain || '' : ''}" placeholder="ejemplo.com">
                </div>
            </div>
            <div class="form-group">
                <label for="cpanel_notes_${id}">Notas</label>
                <textarea id="cpanel_notes_${id}" name="cpanel_accounts[${id}][notes]" rows="2">${account ? account.notes || '' : ''}</textarea>
            </div>
        `;
        cpanelContainer.appendChild(div);
    }

    // Función para crear campo de cuenta FTP
    function createFtpField(account = null) {
        const id = account ? account.id : 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const div = document.createElement('div');
        div.className = 'dynamic-item';
        div.innerHTML = `
            <div class="dynamic-item-header">
                <strong>Cuenta FTP</strong>
                <button type="button" class="btn-remove remove-ftp">🗑️ Eliminar</button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="ftp_user_${id}">Usuario *</label>
                    <input type="text" id="ftp_user_${id}" name="ftp_accounts[${id}][username]" value="${account ? account.username : ''}" required>
                </div>
                <div class="form-group">
                    <label for="ftp_pass_${id}">Contraseña ${account ? '(dejar vacío para no cambiar)' : '*'}</label>
                    <input type="password" id="ftp_pass_${id}" name="ftp_accounts[${id}][password]" ${account ? '' : 'required'}>
                </div>
            </div>
            <div class="form-group">
                <label for="ftp_notes_${id}">Notas</label>
                <textarea id="ftp_notes_${id}" name="ftp_accounts[${id}][notes]" rows="2">${account ? account.notes || '' : ''}</textarea>
            </div>
        `;
        ftpContainer.appendChild(div);
    }

    // Función para crear campo de email
    function createEmailField(account = null) {
        const id = account ? account.id : 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const div = document.createElement('div');
        div.className = 'dynamic-item';
        div.innerHTML = `
            <div class="dynamic-item-header">
                <strong>Cuenta de Email</strong>
                <button type="button" class="btn-remove remove-email">🗑️ Eliminar</button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="email_addr_${id}">Email *</label>
                    <input type="email" id="email_addr_${id}" name="email_accounts[${id}][email_address]" value="${account ? account.email_address : ''}" required>
                </div>
                <div class="form-group">
                    <label for="email_pass_${id}">Contraseña ${account ? '(dejar vacío para no cambiar)' : '*'}</label>
                    <input type="password" id="email_pass_${id}" name="email_accounts[${id}][password]" ${account ? '' : 'required'}>
                </div>
            </div>
            <div class="form-group">
                <label for="email_notes_${id}">Notas</label>
                <textarea id="email_notes_${id}" name="email_accounts[${id}][notes]" rows="2">${account ? account.notes || '' : ''}</textarea>
            </div>
        `;
        emailContainer.appendChild(div);
    }

    // Función para crear campo de cuenta de Terminal Server
    function createTerminalServerField(account = null) {
        const id = account ? account.id : 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const div = document.createElement('div');
        div.className = 'dynamic-item';
        div.innerHTML = `
            <div class="dynamic-item-header">
                <strong>Cuenta de Terminal Server</strong>
                <button type="button" class="btn-remove remove-terminal-server">🗑️ Eliminar</button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="ts_host_${id}">Host *</label>
                    <input type="text" id="ts_host_${id}" name="terminal_server_accounts[${id}][host]" value="${account ? account.host : ''}" required>
                </div>
                <div class="form-group">
                    <label for="ts_port_${id}">Puerto</label>
                    <input type="number" id="ts_port_${id}" name="terminal_server_accounts[${id}][port]" value="${account ? account.port : '3389'}">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="ts_user_${id}">Usuario *</label>
                    <input type="text" id="ts_user_${id}" name="terminal_server_accounts[${id}][username]" value="${account ? account.username : ''}" required>
                </div>
                <div class="form-group">
                    <label for="ts_pass_${id}">Contraseña ${account ? '(dejar vacío para no cambiar)' : '*'}</label>
                    <input type="password" id="ts_pass_${id}" name="terminal_server_accounts[${id}][password]" ${account ? '' : 'required'}>
                </div>
            </div>
            <div class="form-group">
                <label for="ts_notes_${id}">Notas</label>
                <textarea id="ts_notes_${id}" name="terminal_server_accounts[${id}][notes]" rows="2">${account ? account.notes || '' : ''}</textarea>
            </div>
        `;
        terminalServerContainer.appendChild(div);
    }

    // Botones para agregar cuentas
    document.getElementById('addCpanelBtn').addEventListener('click', () => createCpanelField());
    document.getElementById('addFtpBtn').addEventListener('click', () => createFtpField());
    document.getElementById('addEmailBtn').addEventListener('click', () => createEmailField());
    document.getElementById('addTerminalServerBtn').addEventListener('click', () => createTerminalServerField());

    // Delegación de eventos para eliminar
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-cpanel') || e.target.closest('.remove-cpanel')) {
            if (confirm('¿Eliminar esta cuenta cPanel?')) {
                e.target.closest('.dynamic-item').remove();
            }
        }
        if (e.target.classList.contains('remove-ftp') || e.target.closest('.remove-ftp')) {
            if (confirm('¿Eliminar esta cuenta FTP?')) {
                e.target.closest('.dynamic-item').remove();
            }
        }
        if (e.target.classList.contains('remove-email') || e.target.closest('.remove-email')) {
            if (confirm('¿Eliminar esta cuenta de email?')) {
                e.target.closest('.dynamic-item').remove();
            }
        }
        if (e.target.classList.contains('remove-terminal-server') || e.target.closest('.remove-terminal-server')) {
            if (confirm('¿Eliminar esta cuenta de Terminal Server?')) {
                e.target.closest('.dynamic-item').remove();
            }
        }
    });

    // Botón agregar nuevo servidor
    document.getElementById('addHostBtn').addEventListener('click', function() {
        form.reset();
        hostIdInput.value = 'new_' + Date.now();
        cpanelContainer.innerHTML = '';
        ftpContainer.innerHTML = '';
        emailContainer.innerHTML = '';
        terminalServerContainer.innerHTML = '';
        document.querySelector('#hostModal .modal-title').textContent = 'Agregar Servidor de Hosting';
        openModal();
    });

    // Botones editar servidor
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const hostData = JSON.parse(this.getAttribute('data-host'));
            
            // Llenar datos del servidor
            hostIdInput.value = hostData.id;
            document.getElementById('hostLabel').value = hostData.label || '';
            document.getElementById('hostHostname').value = hostData.hostname || '';
            document.getElementById('hostCpanelPort').value = hostData.cpanel_port || 2083;
            document.getElementById('hostWebmailPort').value = hostData.webmail_port || 2096;
            document.getElementById('hostNotes').value = hostData.notes || '';

            // Limpiar contenedores
            cpanelContainer.innerHTML = '';
            ftpContainer.innerHTML = '';
            emailContainer.innerHTML = '';
            terminalServerContainer.innerHTML = '';

            // Cargar cuentas existentes
            if (hostData.accounts && hostData.accounts.length > 0) {
                hostData.accounts.forEach(acc => createCpanelField(acc));
            }
            if (hostData.ftp_accounts && hostData.ftp_accounts.length > 0) {
                hostData.ftp_accounts.forEach(acc => createFtpField(acc));
            }
            if (hostData.emails && hostData.emails.length > 0) {
                hostData.emails.forEach(acc => createEmailField(acc));
            }
            if (hostData.terminal_server_accounts && hostData.terminal_server_accounts.length > 0) {
                hostData.terminal_server_accounts.forEach(acc => createTerminalServerField(acc));
            }

            document.querySelector('#hostModal .modal-title').textContent = 'Editar Servidor: ' + hostData.label;
            openModal();
        });
    });

    // Botones eliminar servidor
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const hostId = this.getAttribute('data-host-id');
            const hostLabel = this.getAttribute('data-host-label');
            
            if (confirm(`¿Estás seguro de eliminar el servidor "${hostLabel}"?\n\nEsto eliminará también todas sus cuentas asociadas (cPanel, FTP, Email).`)) {
                const deleteForm = document.createElement('form');
                deleteForm.method = 'POST';
                deleteForm.action = 'hosting_manager.php';
                
                deleteForm.innerHTML = `
                    <input type="hidden" name="action" value="delete_host">
                    <input type="hidden" name="host_id" value="${hostId}">
                    <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                `;
                
                document.body.appendChild(deleteForm);
                deleteForm.submit();
            }
        });
    });

    // Prevenir submit accidental con Enter
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });
});