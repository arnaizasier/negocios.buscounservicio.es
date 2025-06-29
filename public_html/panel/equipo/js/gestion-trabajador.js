// Funciones utilitarias
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const fileText = document.querySelector('.file-text');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
            fileText.textContent = input.files[0].name;
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
        const isEdit = document.body.dataset.isEdit === 'true';
        fileText.textContent = isEdit ? 'Seleccionar foto (opcional - mantener actual)' : 'Seleccionar foto (opcional)';
    }
}

function validateImageFile(file, fileInput, imagePreview) {
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const tamanoMaximo = 5 * 1024 * 1024; 
    const tamanoMinimo = 1024; 
    
    if (!tiposPermitidos.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Tipo de archivo no válido',
            text: 'Solo se aceptan archivos JPG, PNG, GIF y WebP',
            confirmButtonColor: '#024ddf'
        });
        fileInput.value = '';
        imagePreview.style.display = 'none';
        return false;
    }
    
    if (file.size > tamanoMaximo) {
        Swal.fire({
            icon: 'error',
            title: 'Archivo demasiado grande',
            text: 'El tamaño máximo permitido es 5MB',
            confirmButtonColor: '#024ddf'
        });
        fileInput.value = '';
        imagePreview.style.display = 'none';
        return false;
    }
    
    if (file.size < tamanoMinimo) {
        Swal.fire({
            icon: 'error',
            title: 'Archivo demasiado pequeño',
            text: 'El tamaño mínimo permitido es 1KB',
            confirmButtonColor: '#024ddf'
        });
        fileInput.value = '';
        imagePreview.style.display = 'none';
        return false;
    }
    
    const extension = file.name.split('.').pop().toLowerCase();
    const extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!extensionesPermitidas.includes(extension)) {
        Swal.fire({
            icon: 'error',
            title: 'Extensión no permitida',
            text: 'Solo se permiten archivos con extensión JPG, JPEG, PNG, GIF o WebP',
            confirmButtonColor: '#024ddf'
        });
        fileInput.value = '';
        imagePreview.style.display = 'none';
        return false;
    }
    
    const nombreSeguro = /^[a-zA-Z0-9._-]+$/;
    const nombreSinExtension = file.name.substring(0, file.name.lastIndexOf('.'));
    
    if (!nombreSeguro.test(nombreSinExtension.replace(/[^a-zA-Z0-9._-]/g, ''))) {
        console.warn('Nombre de archivo contiene caracteres especiales');
    }
    
    return true;
}

function validateImageDimensions(file, fileInput, imagePreview) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = function() {
            if (this.width < 50 || this.height < 50) {
                Swal.fire({
                    icon: 'error',
                    title: 'Imagen demasiado pequeña',
                    text: 'Las dimensiones mínimas son 50x50 píxeles',
                    confirmButtonColor: '#024ddf'
                });
                fileInput.value = '';
                imagePreview.style.display = 'none';
                resolve(false);
                return;
            }
            
            if (this.width > 5000 || this.height > 5000) {
                Swal.fire({
                    icon: 'error',
                    title: 'Imagen demasiado grande',
                    text: 'Las dimensiones máximas son 5000x5000 píxeles',
                    confirmButtonColor: '#024ddf'
                });
                fileInput.value = '';
                imagePreview.style.display = 'none';
                resolve(false);
                return;
            }
            
            resolve(true);
        };
        img.onerror = function() {
            Swal.fire({
                icon: 'error',
                title: 'Error al cargar imagen',
                text: 'No se pudo procesar el archivo de imagen',
                confirmButtonColor: '#024ddf'
            });
            fileInput.value = '';
            imagePreview.style.display = 'none';
            resolve(false);
        };
        img.src = URL.createObjectURL(file);
    });
}

function validateFormFields() {
    const negocioId = document.getElementById('negocio_id');
    const nombre = document.getElementById('nombre');
    const apellido = document.getElementById('apellido');
    const rol = document.getElementById('rol');
    
    if (!negocioId.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Campo requerido',
            text: 'Por favor selecciona un negocio',
            confirmButtonColor: '#024ddf'
        });
        negocioId.focus();
        return false;
    }
    
    if (!nombre.value || nombre.value.trim().length < 2) {
        Swal.fire({
            icon: 'warning',
            title: 'Nombre inválido',
            text: 'El nombre debe tener al menos 2 caracteres',
            confirmButtonColor: '#024ddf'
        });
        nombre.focus();
        return false;
    }
    
    if (!apellido.value || apellido.value.trim().length < 2) {
        Swal.fire({
            icon: 'warning',
            title: 'Apellido inválido',
            text: 'El apellido debe tener al menos 2 caracteres',
            confirmButtonColor: '#024ddf'
        });
        apellido.focus();
        return false;
    }
    
    if (!rol.value || rol.value.trim().length < 2) {
        Swal.fire({
            icon: 'warning',
            title: 'Puesto inválido',
            text: 'El puesto debe tener al menos 2 caracteres',
            confirmButtonColor: '#024ddf'
        });
        rol.focus();
        return false;
    }
    
    return true;
}

function eliminarTrabajador(trabajadorId, csrfToken) {
    if (!trabajadorId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'ID de trabajador no válido',
            confirmButtonColor: '#024ddf'
        });
        return;
    }
    
    Swal.fire({
        title: '¿Eliminar trabajador?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '¿Estás completamente seguro?',
                text: 'Se eliminará permanentemente al trabajador y su foto asociada',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar definitivamente',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((finalResult) => {
                if (finalResult.isConfirmed) {
                    ejecutarEliminacion(trabajadorId, csrfToken);
                }
            });
        }
    });
}

function ejecutarEliminacion(trabajadorId, csrfToken) {
    const btnEliminar = document.getElementById('btn-eliminar-trabajador');
    
    Swal.fire({
        title: 'Eliminando...',
        text: 'Por favor espera mientras se elimina el trabajador',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    btnEliminar.disabled = true;
    btnEliminar.innerHTML = 'Eliminando...';
    
    const formData = new FormData();
    formData.append('trabajador_id', trabajadorId);
    formData.append('csrf_token', csrfToken);
    
    fetch('eliminar-trabajador.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Eliminado!',
                text: 'El trabajador ha sido eliminado correctamente',
                confirmButtonColor: '#024ddf'
            }).then(() => {
                window.location.href = 'index?success=deleted';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error al eliminar',
                text: data.message || 'No se pudo eliminar el trabajador',
                confirmButtonColor: '#024ddf'
            });
            btnEliminar.disabled = false;
            btnEliminar.innerHTML = 'Eliminar Trabajador';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'Por favor, inténtalo de nuevo más tarde',
            confirmButtonColor: '#024ddf'
        });
        btnEliminar.disabled = false;
        btnEliminar.innerHTML = 'Eliminar Trabajador';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const templateRangoHorarioTrabajador = document.getElementById('template-rango-horario-trabajador').innerHTML;
    const btnMismoHorario = document.getElementById('btn-mismo-horario');
    const btnHorarioPersonalizado = document.getElementById('btn-horario-personalizado');
    const tipoHorarioInput = document.getElementById('tipo-horario');
    const mensajeMismoHorario = document.getElementById('mensaje-mismo-horario');
    const horarioContainer = document.getElementById('horario-trabajador-container');
    
    function actualizarEstadoBotones() {
        const tipoActual = tipoHorarioInput.value;
        
        if (tipoActual === 'mismo_centro') {
            btnMismoHorario.classList.add('active');
            btnHorarioPersonalizado.classList.remove('active');
            mensajeMismoHorario.style.display = 'block';
            horarioContainer.style.display = 'none';
        } else {
            btnMismoHorario.classList.remove('active');
            btnHorarioPersonalizado.classList.add('active');
            mensajeMismoHorario.style.display = 'none';
            horarioContainer.style.display = 'block';
        }
    }
    
    if (btnMismoHorario) {
        btnMismoHorario.addEventListener('click', function() {
            tipoHorarioInput.value = 'mismo_centro';
            actualizarEstadoBotones();
        });
    }
    
    if (btnHorarioPersonalizado) {
        btnHorarioPersonalizado.addEventListener('click', function() {
            tipoHorarioInput.value = 'personalizado';
            actualizarEstadoBotones();
        });
    }
    
    actualizarEstadoBotones();
    
    const fileInput = document.getElementById('foto');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (fileInput) {
        fileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            
            if (file) {
                if (!validateImageFile(file, fileInput, imagePreview)) {
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
                
                const dimensionsValid = await validateImageDimensions(file, fileInput, imagePreview);
                if (!dimensionsValid) {
                    return;
                }
                
                previewImage(fileInput);
            } else {
                imagePreview.style.display = 'none';
            }
        });
    }
    
    const form = document.querySelector('form');
    if (form) {
        let isSubmitting = false;
        
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            if (!validateFormFields()) {
                e.preventDefault();
                return false;
            }
            
            isSubmitting = true;
            
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                const isEdit = document.body.dataset.isEdit === 'true';
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isEdit ? 'Actualizando...' : 'Guardando...');
            }
        });
    }
    
    document.querySelectorAll('.toggle-trabajador-abierto').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const diaId = this.id.replace('trabajador-abierto-', '');
            const rangosContainer = document.getElementById('rangos-trabajador-' + diaId);
            const estadoCerrado = document.querySelector(`#horario-trabajador-${diaId} .estado-cerrado-trabajador`);
            
            if (this.checked) {
                rangosContainer.style.display = 'block';
                estadoCerrado.value = 'false';
            } else {
                rangosContainer.style.display = 'none';
                estadoCerrado.value = 'true';
            }
        });
    });
    
    document.querySelectorAll('.btn-agregar-rango-trabajador').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const dia = this.getAttribute('data-dia');
            const rangosContainer = this.parentElement;
            const rangosExistentes = rangosContainer.querySelectorAll('.rango-horario-trabajador');
            const nuevoIndex = rangosExistentes.length;
            
            const nuevoRango = templateRangoHorarioTrabajador
                .replace(/{DIA}/g, dia)
                .replace(/{INDEX}/g, nuevoIndex);
                
            this.insertAdjacentHTML('beforebegin', nuevoRango);
        });
    });

    if (document.querySelector('.horario-trabajador-container')) {
        document.querySelector('.horario-trabajador-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-eliminar-rango-trabajador') || e.target.parentElement.classList.contains('btn-eliminar-rango-trabajador')) {
                const btn = e.target.classList.contains('btn-eliminar-rango-trabajador') ? e.target : e.target.parentElement;
                const rangoHorario = btn.closest('.rango-horario-trabajador');
                const horarioDia = rangoHorario.closest('.horario-dia-trabajador');
                const rangosContainer = rangoHorario.parentElement;
                
                const rangos = rangosContainer.querySelectorAll('.rango-horario-trabajador');
                if (rangos.length > 1) {
                    rangoHorario.remove();
                    actualizarIndicesRangosTrabajador(horarioDia);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No se puede eliminar',
                        text: 'Debe haber al menos un rango horario para los días que trabaja',
                        confirmButtonColor: '#024ddf'
                    });
                }
            }
        });
    }
    
    function actualizarIndicesRangosTrabajador(horarioDia) {
        const dia = horarioDia.id.replace('horario-trabajador-', '');
        const rangos = horarioDia.querySelectorAll('.rango-horario-trabajador');
        
        rangos.forEach(function(rango, index) {
            const selectInicio = rango.querySelector('.hora-inicio-trabajador');
            const selectFin = rango.querySelector('.hora-fin-trabajador');
            
            selectInicio.name = `horario_trabajador[${dia}][rangos][${index}][inicio]`;
            selectFin.name = `horario_trabajador[${dia}][rangos][${index}][fin]`;
        });
    }
    
    const btnEliminar = document.getElementById('btn-eliminar-trabajador');
    if (btnEliminar && window.workerData) {
        btnEliminar.addEventListener('click', function() {
            eliminarTrabajador(window.workerData.trabajadorId, window.workerData.csrfToken);
        });
    }
}); 