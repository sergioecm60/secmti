document.addEventListener('DOMContentLoaded', function () {
    // Leer la configuración pasada desde PHP
    const pageDataElement = document.getElementById('page-data');
    if (!pageDataElement || !pageDataElement.textContent) {
        console.error('Error: No se encontró el elemento #page-data. La configuración no pudo cargarse.');
        return; // Detener la ejecución si no hay configuración
    }
    const config = JSON.parse(pageDataElement.textContent); // Ahora es seguro parsear

    const loginModal = document.getElementById('loginModal');
    const usuarioInput = document.getElementById('usuarioInput');
    const passInput = document.getElementById('passInput');
    const loginBtn = document.getElementById('loginBtn');
    const captchaQuestion = document.getElementById('captchaQuestion');
    const captchaInput = document.getElementById('captchaInput');
    const loginError = document.getElementById('loginError'); // Mantener el ID para JS, pero el CSS usará la clase

    // 1. El estado de la sesión del usuario viene de la configuración.
    let isUserLoggedIn = config.isUserLoggedIn;
    const servicios = config.services;

    let pendingButton = null;

    // Función para obtener y mostrar una nueva pregunta de captcha
    async function refreshCaptcha() {
        try {
            const response = await fetch('captcha_generator.php');
            if (!response.ok) {
                // Si el servidor responde con un error (4xx, 5xx), lanzamos una excepción.
                throw new Error('No se pudo contactar al servidor de captcha.');
            }
            const data = await response.json();
            // Comprobamos que la respuesta tenga la propiedad 'question'.
            captchaQuestion.textContent = data.question || 'Pregunta no válida.';
            captchaInput.value = ''; // Limpiar la respuesta anterior
        } catch (error) {
            console.error('Error en refreshCaptcha:', error);
            captchaQuestion.textContent = 'Error al cargar captcha.';
        }
    }

    // Mostrar modal de login
    function mostrarLogin(btnId) {
        pendingButton = btnId;
        loginModal.classList.add('active');
        loginModal.setAttribute('aria-hidden', 'false');
        usuarioInput.value = '';
        passInput.value = '';
        loginError.style.display = 'none';
        refreshCaptcha(); // Cargar una nueva pregunta de captcha
        usuarioInput.focus();
    }

    // Ocultar modal
    function ocultarLogin() {
        loginModal.classList.remove('active');
        loginModal.setAttribute('aria-hidden', 'true');
        pendingButton = null;
    }

    // Evento de login
    loginBtn.addEventListener('click', async function () {
        loginError.style.display = 'none';
        const user = usuarioInput.value;
        const pass = passInput.value;
        const captcha = captchaInput.value;

        try {
            const response = await fetch('login_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user, pass, captcha })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Error de autenticación.');
            }

            if (result.success) {
                // 3. Actualizamos el estado en el cliente para no volver a pedir login.
                isUserLoggedIn = true;

                // BUGFIX: Guardamos la información del servicio ANTES de ocultar el modal,
                // ya que ocultarLogin() resetea `pendingButton`.
                const servicio = pendingButton ? servicios[pendingButton] : null;

                ocultarLogin(); // Ahora sí, ocultamos el modal.

                if (servicio) { // Procedemos solo si encontramos el servicio
                    if (servicio.redirect) {
                        window.location.href = servicio.url;
                    } else {
                        window.open(servicio.url, '_blank');
                    }
                } else {
                    // Si el login fue exitoso pero no hay un servicio pendiente,
                    // simplemente recargamos la página para reflejar el estado de login.
                    console.warn('Login exitoso, pero no se encontró un servicio pendiente. Recargando página.');
                    window.location.reload();
                }
            }
        } catch (error) {
            loginError.textContent = error.message;
            loginError.style.display = 'block';
            refreshCaptcha(); // Obtener un nuevo captcha después de un error
        }
    });

    // Enter para login, Escape para cerrar
    loginModal.addEventListener('keydown', function (e) {
        if (e.key === "Enter") loginBtn.click();
        if (e.key === "Escape") ocultarLogin();
    });

    // Cerrar al hacer clic fuera del modal
    loginModal.addEventListener('click', function (e) {
        if (e.target === loginModal) ocultarLogin();
    });

    // Asignar eventos a los botones de servicios
    Object.keys(servicios).forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', () => {
                const servicio = servicios[id];
                // 2. Comprobamos si se requiere login Y si el usuario NO está ya logueado.
                if (servicio.requires_login && !isUserLoggedIn) {
                    mostrarLogin(id);
                } else {
                    // Si no requiere login, o si ya estamos logueados, procedemos.
                    if (servicio.redirect) {
                        window.location.href = servicio.url;
                    } else {
                        window.open(servicio.url, '_blank');
                    }
                }
            });
        }
    });
});