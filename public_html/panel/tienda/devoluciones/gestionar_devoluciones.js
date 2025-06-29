document.addEventListener('DOMContentLoaded', function() {
    // Initial setup if needed when the DOM is ready
});

function mostrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // Clear potential previous values when closing
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        // Reset specific fields if needed (e.g., hidden ids)
        if (modalId === 'form-aceptar-solicitud') {
            document.getElementById('aceptar-devolucion-id').value = '';
            document.getElementById('precio-original-info').textContent = '';
        } else if (modalId === 'form-cancelar-solicitud') {
            document.getElementById('cancelar-devolucion-id').value = '';
        } else if (modalId === 'form-rechazar-devolucion') {
            document.getElementById('rechazar-devolucion-id').value = '';
        }
    }
}

// Close modal if user clicks outside of the modal content
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.style.display = "none";
            // Also clear form on background click
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
            // Reset specific fields if needed
            if (modal.id === 'form-aceptar-solicitud') {
                document.getElementById('aceptar-devolucion-id').value = '';
                document.getElementById('precio-original-info').textContent = '';
            } else if (modal.id === 'form-cancelar-solicitud') {
                document.getElementById('cancelar-devolucion-id').value = '';
            } else if (modal.id === 'form-rechazar-devolucion') {
                document.getElementById('rechazar-devolucion-id').value = '';
            }
        }
    });
}

// --- Functions to populate and show specific modals ---

function mostrarFormularioAceptar(devolucionId, precioOriginal) {
    document.getElementById('aceptar-devolucion-id').value = devolucionId;
    // Optionally pre-fill amount or show original price
    const montoInput = document.getElementById('monto_devolver');
    const precioInfo = document.getElementById('precio-original-info');
    if (precioOriginal !== null && precioOriginal !== undefined) {
        montoInput.value = parseFloat(precioOriginal).toFixed(2); // Pre-fill with original price
        precioInfo.textContent = `Precio original pagado por el producto (sin envío): ${parseFloat(precioOriginal).toFixed(2)} €`;
    } else {
        montoInput.value = ''; // Clear if no price info
        precioInfo.textContent = 'No se pudo determinar el precio original.';
    }
    montoInput.placeholder = "Ingrese el monto a devolver";
    mostrarModal('form-aceptar-solicitud');
}

function mostrarFormularioCancelar(devolucionId) {
    document.getElementById('cancelar-devolucion-id').value = devolucionId;
    mostrarModal('form-cancelar-solicitud');
}

function mostrarFormularioRechazar(devolucionId) {
    document.getElementById('rechazar-devolucion-id').value = devolucionId;
    mostrarModal('form-rechazar-devolucion');
}

// --- SweetAlert Confirmation for Refund ---

function confirmarReembolso(devolucionId, montoDevuelto) {
    const montoFormateado = parseFloat(montoDevuelto).toFixed(2);
    
    Swal.fire({
        title: 'Confirmar Reembolso',
        text: `¿Deseas marcar esta devolución como completada y notificar para el reembolso de ${montoFormateado} € al cliente?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745', // Green
        cancelButtonColor: '#dc3545', // Red
        confirmButtonText: 'Sí, confirmar reembolso',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // User clicked 'Yes', submit the form programmatically
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; // Submit to the same page

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'aceptar_reembolso';
            form.appendChild(actionInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'devolucion_id';
            idInput.value = devolucionId;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    });
}