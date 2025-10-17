/**
 * manage.js - Lógica para el Panel de Administración (manage.php)
 */
document.addEventListener('DOMContentLoaded', function() {

    // 1. Funcionalidad de Acordeón para las secciones
    // ========================================================================
    const sections = document.querySelectorAll('.page-manage .section');
    sections.forEach(section => {
        const header = section.querySelector('.section-header');
        const body = section.querySelector('.section-body');

        if (header && body) {
            header.addEventListener('click', () => {
                const isActive = header.classList.contains('active');
                
                // Cerrar todas las demás secciones
                sections.forEach(s => {
                    s.querySelector('.section-header')?.classList.remove('active');
                    s.querySelector('.section-body').style.maxHeight = null;
                });

                // Abrir o cerrar la sección actual
                if (!isActive) {
                    header.classList.add('active');
                    body.style.maxHeight = body.scrollHeight + 'px';
                }
            });
        }
    });

    // Abrir la primera sección por defecto
    const firstSectionHeader = document.querySelector('.page-manage .section .section-header');
    if (firstSectionHeader) {
        firstSectionHeader.click();
    }

    // 2. Funcionalidad para listas dinámicas (Teléfonos, Sucursales)
    // ========================================================================
    document.querySelectorAll('.add-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const inputName = this.dataset.name;
            const placeholder = this.dataset.placeholder;
            const list = document.getElementById(targetId);

            if (list) {
                const newItem = document.createElement('div');
                newItem.className = 'repeatable-item';
                newItem.innerHTML = `
                    <input type="text" name="${inputName}" placeholder="${placeholder}">
                    <button type="button" class="delete-item-btn">Eliminar</button>
                `;
                list.insertBefore(newItem, this);
            }
        });
    });

    // Delegación de eventos para botones de eliminar
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('delete-item-btn')) {
            e.target.closest('.repeatable-item').remove();
        }
        if (e.target && e.target.classList.contains('delete-service-btn')) {
            if (confirm('¿Estás seguro de que quieres eliminar este servicio? El cambio se guardará al hacer clic en "Guardar Cambios".')) {
                e.target.closest('tr').remove();
            }
        }
    });

    // 3. Funcionalidad para añadir Redes Sociales y Servicios (tablas)
    // ========================================================================
    const addSocialBtn = document.getElementById('add-social-link-btn');
    if (addSocialBtn) {
        addSocialBtn.addEventListener('click', function() {
            const tableBody = document.getElementById('social-links-table').querySelector('tbody');
            const newId = 'new_social_' + Date.now();
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="social_links[${newId}][id]" value="${newId}" placeholder="ID único (ej: tiktok)"></td>
                <td><input type="text" name="social_links[${newId}][label]" placeholder="Etiqueta" required></td>
                <td><input type="text" name="social_links[${newId}][url]" placeholder="URL completa" required></td>
                <td><textarea name="social_links[${newId}][svg_path]" rows="2" placeholder="<path d='...'/>"></textarea></td>
                <td><button type="button" class="delete-item-btn">Eliminar</button></td>
            `;
            tableBody.appendChild(newRow);
        });
    }

    const addServiceBtn = document.getElementById('add-service-btn');
    if (addServiceBtn) {
        addServiceBtn.addEventListener('click', function() {
            const tableBody = document.getElementById('services-table').querySelector('tbody');
            const newId = 'new_service_' + Date.now();
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="services[${newId}][id]" value="${newId}" readonly class="readonly-id"></td>
                <td><input type="text" name="services[${newId}][label]" placeholder="Nombre del Botón" required></td>
                <td><input type="text" name="services[${newId}][url]" placeholder="URL de destino" required></td>
                <td><input type="text" name="services[${newId}][category]" placeholder="Categoría (ej: LAN)"></td>
                <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][requires_login]" value="1" checked></td>
                <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][redirect]" value="1"></td>
                <td><button type="button" class="delete-service-btn">Eliminar</button></td>
            `;
            tableBody.appendChild(newRow);
        });
    }

    // Delegación de eventos para eliminar filas de tablas
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.matches('#social-links-table .delete-item-btn') || e.target.matches('#services-table .delete-service-btn'))) {
             if (confirm('¿Estás seguro de que quieres eliminar esta fila? El cambio se guardará al hacer clic en "Guardar Cambios".')) {
                e.target.closest('tr').remove();
            }
        }
    });

    // 4. Confirmación antes de guardar
    // ========================================================================
    const saveBtn = document.getElementById('saveConfigBtn');
    const configForm = document.getElementById('configForm');

    if (saveBtn && configForm) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres guardar todos los cambios? Esta acción sobreescribirá la configuración actual.')) {
                configForm.submit();
            }
        });
    }
});