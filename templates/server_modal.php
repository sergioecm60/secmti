<!-- templates/server_modal.php -->
<div id="serverModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Agregar/Editar Servidor</h2>
        
        <form id="serverForm" method="POST" action="datacenter_view.php">
            <input type="hidden" name="action" value="save_server">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="server[id]" id="serverId">

            <!-- Pestañas -->
            <div class="modal-tabs">
                <button type="button" class="tab-link active" data-tab="tab-general">General</button>
                <button type="button" class="tab-link" data-tab="tab-hardware">Hardware</button>
                <button type="button" class="tab-link" data-tab="tab-network">Red</button>
                <button type="button" class="tab-link" data-tab="tab-services">Servicios</button>
                <button type="button" class="tab-link" data-tab="tab-notes">Notas</button>
            </div>

            <!-- Contenido de Pestañas -->
            <div class="tab-content active" id="tab-general">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="serverLabel">Etiqueta del Servidor *</label>
                        <input type="text" id="serverLabel" name="server[label]" required>
                    </div>
                    <div class="form-group">
                        <label for="serverLocation">Ubicación</label>
                        <select id="serverLocation" name="server[location_id]">
                            <option value="">-- Sin Ubicación --</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="serverType">Tipo de Servidor</label>
                        <select id="serverType" name="server[type]">
                            <option value="physical">Físico</option>
                            <option value="virtual">Virtual</option>
                            <option value="container">Contenedor</option>
                            <option value="cloud">Cloud</option>
                            <option value="isp">ISP</option>
                        </select>
                    </div>
                </div>
                <fieldset>
                    <legend>Credencial Principal (Opcional)</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="serverUsername">Usuario</label>
                            <input type="text" id="serverUsername" name="server[username]" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="serverPassword">Contraseña</label>
                            <input type="password" id="serverPassword" name="server[password]" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="tab-content" id="tab-hardware">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="hwModel">Modelo</label>
                        <input type="text" id="hwModel" name="server[hw_model]">
                    </div>
                    <div class="form-group">
                        <label for="hwCpu">CPU</label>
                        <input type="text" id="hwCpu" name="server[hw_cpu]">
                    </div>
                    <div class="form-group">
                        <label for="hwRam">RAM</label>
                        <input type="text" id="hwRam" name="server[hw_ram]">
                    </div>
                    <div class="form-group">
                        <label for="hwDisk">Disco</label>
                        <input type="text" id="hwDisk" name="server[hw_disk]">
                    </div>
                </div>
            </div>

            <div class="tab-content" id="tab-network">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="netIpLan">IP LAN</label>
                        <input type="text" id="netIpLan" name="server[net_ip_lan]">
                    </div>
                    <div class="form-group">
                        <label for="netIpWan">IP WAN</label>
                        <input type="text" id="netIpWan" name="server[net_ip_wan]">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="netHostExt">Host Externo</label>
                        <input type="text" id="netHostExt" name="server[net_host_external]">
                    </div>
                    <div class="form-group">
                        <label for="netGateway">Gateway</label>
                        <input type="text" id="netGateway" name="server[net_gateway]">
                    </div>
                </div>
                <div class="form-group">
                    <label for="netDns">Servidores DNS (separados por coma)</label>
                    <input type="text" id="netDns" name="server[net_dns]">
                </div>
            </div>

            <div class="tab-content" id="tab-services">
                <h3>⚙️ Servicios del Servidor</h3>
                <div id="servicesContainer">
                    <!-- Los servicios se insertarán aquí dinámicamente -->
                </div>
                <button type="button" id="addServiceModalBtn" class="add-btn">+ Agregar Servicio</button>
            </div>

            <div class="tab-content" id="tab-notes">
                <div class="form-group">
                    <label for="serverNotes">Notas Adicionales</label>
                    <textarea id="serverNotes" name="server[notes]" rows="6"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-btn">💾 Guardar</button>
                <button type="button" class="cancel-btn">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Template para el footer, si no existe -->
<?php
$footer_path = __DIR__ . '/footer.php';
if (!file_exists($footer_path)) {
    file_put_contents($footer_path, '
<footer class="footer">
    <strong>' . htmlspecialchars($config['footer']['line1'] ?? '') . '</strong><br>
    <div class="footer-contact-line">
        <span>' . htmlspecialchars($config['footer']['line2'] ?? '') . '</span>
    </div>
    <a href="' . htmlspecialchars($config['footer']['license_url'] ?? '#') . '" target="_blank" rel="license">
        Términos y Condiciones (Licencia GNU GPL v3)
    </a>
</footer>
    ');
}
?>