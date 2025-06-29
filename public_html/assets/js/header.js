// JavaScript para manejar la funcionalidad del menú
function toggleMenu() {
    const navLinks = document.getElementById('busMainNav');
    const menuOverlay = document.getElementById('busMenuOverlay');
    const burgerTablet = document.getElementById('burger-toggle-tablet');
    const burgerMobile = document.getElementById('burger-toggle-mobile');
    
    // Toggle la clase active en el menú de navegación
    navLinks.classList.toggle('active');
    
    // Toggle la clase active en el overlay
    menuOverlay.classList.toggle('active');
    
    // Sincronizar el estado de los checkboxes de burger menu
    if (navLinks.classList.contains('active')) {
        // Si el menú está abierto
        if (burgerTablet) burgerTablet.checked = true;
        if (burgerMobile) burgerMobile.checked = true;
        
        // Eliminar esta línea para evitar el foco automático
        // navLinks.querySelector('a').focus();
    } else {
        // Si el menú está cerrado
        if (burgerTablet) burgerTablet.checked = false;
        if (burgerMobile) burgerMobile.checked = false;
    }
}

// Cerrar el menú con la tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const navLinks = document.getElementById('busMainNav');
        if (navLinks.classList.contains('active')) {
            toggleMenu();
        }
    }
});

// Cerrar el menú al cambiar el tamaño de la ventana
window.addEventListener('resize', function() {
    const navLinks = document.getElementById('busMainNav');
    const burgerTablet = document.getElementById('burger-toggle-tablet');
    const burgerMobile = document.getElementById('burger-toggle-mobile');
    const menuOverlay = document.getElementById('busMenuOverlay');
    
    if (navLinks.classList.contains('active')) {
        navLinks.classList.remove('active');
        menuOverlay.classList.remove('active');
        
        // Restablecer los estados de los checkbox
        if (burgerTablet) burgerTablet.checked = false;
        if (burgerMobile) burgerMobile.checked = false;
    }
});


document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.bus-burger-menu svg').forEach(svg => {
        svg.classList.add('loaded');
    });
});


// Script para el efecto glassmorphism del header al hacer scroll
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.bus-header');
    let lastScrollTop = 0;
    let ticking = false;

    function updateHeader() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Añadir o quitar la clase 'scrolled' basado en la posición del scroll
        if (scrollTop > 50) { // Cambiar después de 50px de scroll
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop;
        ticking = false;
    }

    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }

    // Listener para el evento scroll con optimización de rendimiento
    window.addEventListener('scroll', requestTick);
    
    // También verificar en el load inicial por si la página se carga con scroll
    updateHeader();
});

// Funcionalidad del menú hamburguesa (si la necesitas)
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.bus-burger-menu input');
    const navLinks = document.querySelector('.bus-nav-links');
    const menuOverlay = document.querySelector('.bus-menu-overlay');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('change', function() {
            if (this.checked) {
                navLinks.classList.add('active');
                if (menuOverlay) {
                    menuOverlay.classList.add('active');
                }
            } else {
                navLinks.classList.remove('active');
                if (menuOverlay) {
                    menuOverlay.classList.remove('active');
                }
            }
        });
    }

    // Cerrar menú al hacer click en el overlay
    if (menuOverlay) {
        menuOverlay.addEventListener('click', function() {
            navLinks.classList.remove('active');
            menuOverlay.classList.remove('active');
            if (menuToggle) {
                menuToggle.checked = false;
            }
        });
    }

    // Cerrar menú al hacer click en un enlace (para móviles)
    const navLinkItems = document.querySelectorAll('.bus-nav-links a');
    navLinkItems.forEach(link => {
        link.addEventListener('click', function() {
            navLinks.classList.remove('active');
            if (menuOverlay) {
                menuOverlay.classList.remove('active');
            }
            if (menuToggle) {
                menuToggle.checked = false;
            }
        });
    });
});