document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar45');
    const content = document.getElementById('content45');
    const toggleBtn = document.getElementById('toggle-btn45');
    const submenuParents = document.querySelectorAll('.submenu-parent45');

    // Function to set initial sidebar state
    function setInitialSidebarState() {
        const savedState = localStorage.getItem('sidebarState');
        if (window.innerWidth <= 1400) {
            // Restaurar estado guardado o colapsar por defecto
            if (savedState === 'active') {
                sidebar.classList.add('active45');
            } else {
                sidebar.classList.remove('active45');
            }
        } else {
            sidebar.classList.remove('active45');
            // Eliminamos la funcionalidad de minimizar el sidebar en pantallas grandes
            sidebar.classList.remove('closed45');
            content.classList.remove('full45');
        }
    }

    // Set initial state on load
    setInitialSidebarState();

    // Toggle button logic
    toggleBtn.addEventListener('click', function() {
        if (window.innerWidth <= 1400) {
            sidebar.classList.toggle('active45');
            // Guardar estado
            localStorage.setItem('sidebarState', sidebar.classList.contains('active45') ? 'active' : 'inactive');
            // Prevenir scroll del body cuando el menú está abierto
            if (sidebar.classList.contains('active45')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
        // Eliminamos la funcionalidad de minimizar el sidebar en pantallas grandes
    });

    // Cerrar menú al hacer clic en un enlace (solo en móvil)
    document.querySelectorAll('.nav-link45').forEach(link => {
        if (!link.classList.contains('submenu-parent45')) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1400 && sidebar.classList.contains('active45')) {
                    sidebar.classList.remove('active45');
                    localStorage.setItem('sidebarState', 'inactive');
                    document.body.style.overflow = '';
                }
            });
        }
    });

    // Manejo de submenús
    submenuParents.forEach(parent => {
        parent.addEventListener('click', function(e) {
            if (window.innerWidth <= 1400 && !sidebar.classList.contains('active45')) {
                sidebar.classList.add('active45');
                localStorage.setItem('sidebarState', 'active');
                return;
            }

            const submenu = this.nextElementSibling;
            const icon = this.querySelector('.submenu-toggle45 i');

            e.preventDefault();

            if (submenu.classList.contains('active45')) {
                submenu.classList.remove('active45');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                document.querySelectorAll('.submenu45.active45').forEach(menu => {
                    if (menu !== submenu) {
                        menu.classList.remove('active45');
                        const menuIcon = menu.previousElementSibling.querySelector('.submenu-toggle45 i');
                        menuIcon.classList.remove('fa-chevron-up');
                        menuIcon.classList.add('fa-chevron-down');
                    }
                });

                submenu.classList.add('active45');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
    });

    // Manejar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        const savedState = localStorage.getItem('sidebarState');
        if (window.innerWidth <= 1400) {
            // Aplicar estado guardado en lugar de forzar active45
            if (savedState === 'active') {
                sidebar.classList.add('active45');
            } else {
                sidebar.classList.remove('active45');
            }
            content.classList.remove('full45');
            sidebar.classList.remove('closed45');
            document.body.style.overflow = sidebar.classList.contains('active45') ? 'hidden' : '';
        } else {
            sidebar.classList.remove('active45');
            document.body.style.overflow = '';
            // Eliminamos la minimización del sidebar en pantallas grandes
            sidebar.classList.remove('closed45');
            content.classList.remove('full45');
        }
    });
});