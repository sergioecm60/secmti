document.addEventListener('DOMContentLoaded', function() {
    // --- Lógica para secciones colapsables (acordeón) ---
    document.querySelectorAll('.section-header').forEach(header => {
        header.addEventListener('click', () => {
            header.classList.toggle('active');
            const body = header.nextElementSibling;
            if (body.style.maxHeight) {
                body.style.maxHeight = null;
            } else {
                body.style.maxHeight = body.scrollHeight + "px";
            }
        });
    });

    // --- Lógica para listas dinámicas ---
    const contentDiv = document.querySelector('.content');
    document.querySelectorAll('.add-item-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetListId = this.dataset.target;
            const inputName = this.dataset.name;
            const placeholder = this.dataset.placeholder || '';
            const list = document.getElementById(targetListId);
            
            const newItem = document.createElement('div');
            newItem.classList.add('repeatable-item');
            newItem.innerHTML = `
                <input type="text" name="${inputName}" value="" placeholder="${placeholder}" />
                <button type="button" class="delete-item-btn">Eliminar</button>
            `;
            list.insertBefore(newItem, this);
            newItem.querySelector('input').focus();
        });
    });

    contentDiv.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('delete-item-btn')) {
            e.preventDefault();
            e.target.closest('.repeatable-item').remove();
        }
    });

    // --- Lógica para la tabla de servicios ---
    const servicesTableBody = document.querySelector('#services-table tbody');
    servicesTableBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('delete-service-btn')) {
            e.preventDefault();
            e.target.closest('tr').remove();
        }
    });

    const addServiceBtn = document.getElementById('add-service-btn');
    addServiceBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const newId = 'nuevo_' + Date.now();
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="text" name="services[${newId}][id]" placeholder="ej: miBoton" required></td>
            <td><input type="text" name="services[${newId}][label]" placeholder="ej: Mi Botón" required></td>
            <td><input type="text" name="services[${newId}][url]" placeholder="https://... o info.php" required></td>
            <td><input type="text" name="services[${newId}][category]" placeholder="Ej: Accesos WAN" required></td>
            <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][requires_login]" value="1" checked></td>
            <td class="checkbox-cell"><input type="checkbox" name="services[${newId}][redirect]" value="1"></td>
            <td><button type="button" class="delete-service-btn">Eliminar</button></td>
        `;
        servicesTableBody.appendChild(newRow);
        newRow.querySelector('input').focus();
    });

    // --- Lógica para la tabla de redes sociales ---
    const socialLinksTableBody = document.querySelector('#social-links-table tbody');
    socialLinksTableBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('delete-item-btn')) {
            e.preventDefault();
            e.target.closest('tr').remove();
        }
    });

    const addSocialLinkBtn = document.getElementById('add-social-link-btn');
    addSocialLinkBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const newId = 'nuevo_' + Date.now();
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="text" name="social_links[${newId}][id]" placeholder="ej: tiktok" required></td>
            <td><input type="text" name="social_links[${newId}][label]" placeholder="TikTok" required></td>
            <td><input type="text" name="social_links[${newId}][url]" placeholder="https://www.tiktok.com/..." required></td>
            <td><textarea name="social_links[${newId}][svg_path]" rows="2" placeholder="<path d='...' />"></textarea></td>
            <td><button type="button" class="delete-item-btn">Eliminar</button></td>
        `;
        socialLinksTableBody.appendChild(newRow);
        newRow.querySelector('input').focus();
    });

    // --- Lógica para el contador de caracteres ---
    document.querySelectorAll('input[type="text"], textarea').forEach(input => {
        const maxLength = parseInt(input.getAttribute('maxlength')) || 500;
        
        const counter = document.createElement('span');
        counter.className = 'char-counter';
        input.parentNode.appendChild(counter);
        
        function updateCounter() {
            const len = input.value.length;
            counter.textContent = `${len}/${maxLength}`;
            counter.className = 'char-counter';
            if (len > maxLength * 0.9) counter.classList.add('danger');
            else if (len > maxLength * 0.75) counter.classList.add('warning');
        }
        
        input.addEventListener('input', updateCounter);
        updateCounter();
    });
    
    // --- Lógica para el botón de guardar ---
    const saveConfigBtn = document.getElementById('saveConfigBtn');
    if (saveConfigBtn) {
        saveConfigBtn.addEventListener('click', function() {
            if (confirm('¿Guardar todos los cambios? Esta acción sobreescribirá el archivo de configuración.')) {
                document.getElementById('configForm').submit();
            }
        });
    }
});