<?php
require_once 'functions.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net/npm/sweetalert2@11 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; form-action 'self';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

if(!defined('SECURE_ACCESS')) {
    die('Acceso directo no permitido');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
</head>
<body>

<div class="container45">
    <?php include '../../assets/includes/sidebar.php'; ?>
    
    <div class="content45" id="content45">
        <main class="main-content">
        <div class="content-wrapper">
            <header class="page-header">
                <h1>Gestión de Clientes</h1>
            </header>
            
            <?php if (isset($mensaje_exito)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_exito); ?></div>
            <?php endif; ?>

            <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error); ?></div>
            <?php endif; ?>
            
            <div class="search-section">
                <form method="GET" class="search-form">
                    <input type="hidden" name="negocio_id" value="<?php echo $negocio_id; ?>">
                    <div class="search-input-group">
                        <input type="text" name="busqueda" class="search-input" 
                               placeholder="Buscar por nombre, apellido, email o teléfono..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <?php if (!empty($busqueda)): ?>
                        <a href="?negocio_id=<?php echo $negocio_id; ?>" class="clear-btn">Limpiar búsqueda</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="actions-section">
                <button class="btn btn-primary" id="addClientBtn">
                    <i class="fas fa-plus"></i>
                    <span class="btn-text">Añadir Cliente</span>
                </button>
                
                <div class="dropdown" id="optionsDropdown">
                    <button class="btn btn-secondary dropdown-toggle" id="dropdownBtn">
                        <span>Opciones</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <?php if (count($negocios_usuario) > 1): ?>
                            <div class="dropdown-section">
                                <span class="dropdown-title">Seleccionar Negocio</span>
                                <form action="" method="GET" id="businessForm">
                                    <select name="negocio_id" class="dropdown-select" onchange="this.form.submit()">
                                        <?php foreach ($negocios_usuario as $negocio): ?>
                                        <option value="<?php echo $negocio['negocio_id']; ?>" 
                                                <?php echo ($negocio['negocio_id'] == $negocio_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($negocio['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <a href="?exportar_clientes=1&negocio_id=<?php echo $negocio_id; ?>" class="dropdown-item">
                            <i class="fas fa-download"></i>
                            <span>Exportar Clientes</span>
                        </a>
                        <?php if (!empty($duplicados)): ?>
                            <button class="dropdown-item" id="mergeBtn">
                                <i class="fas fa-compress-arrows-alt"></i>
                                <span>Fusionar Duplicados (<?php echo count($duplicados); ?>)</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="clients-section">
                <?php if (!empty($clientes)): ?>
                    <div class="table-container">
                        <table class="clients-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th class="hide-mobile">Reservas</th>
                                    <th class="hide-mobile">Ventas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php
                                    $numReservas = $cliente['numero_reservas'] ?? 0;
                                    $totalVentas = $cliente['importe_gastado'] ?? 0;
                                    ?>
                                    <tr class="client-row" onclick="showClientDetails(<?php echo $cliente['cliente_id']; ?>, <?php echo (int)$negocio_id; ?>)">
                                        <td class="client-name-cell">
                                            <div class="client-info">
                                                <div class="client-avatar">
                                                    <?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?>
                                                </div>
                                                <div class="client-details">
                                                    <div class="client-name"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></div>
                                                    <div class="client-email"><?php echo htmlspecialchars($cliente['email'] ?: 'Sin email'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="client-phone"><?php echo htmlspecialchars($cliente['telefono'] ?: '-'); ?></td>
                                        <td class="client-reservations hide-mobile"><?php echo $numReservas; ?></td>
                                        <td class="client-sales hide-mobile"><?php echo number_format($totalVentas, 2); ?> €</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No hay clientes registrados</h3>
                        <p>Comienza añadiendo tu primer cliente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
        </div>
</div>

<!-- Modal Añadir Cliente -->
<div class="modal-overlay" id="addClientModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Añadir Nuevo Cliente</h2>
            <button class="modal-close" onclick="closeModal('addClientModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addClientForm" onsubmit="return validateClientForm(event)">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" class="form-input" 
                               pattern="[0-9+\-\s()]{6,20}">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="notas">Notas</label>
                        <textarea id="notas" name="notas" class="form-input" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClientModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalles Cliente -->
<div class="modal-overlay" id="clientDetailsModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Detalles del Cliente</h2>
            <button class="modal-close" onclick="closeModal('clientDetailsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="clientDetailsContent">
            <!-- El contenido se carga dinámicamente -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="editClientBtn">Editar</button>
            <button type="button" class="btn btn-danger" id="deleteClientBtn">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div class="modal-overlay" id="editClientModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Editar Cliente</h2>
            <button class="modal-close" onclick="closeModal('editClientModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editClientForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" id="edit_cliente_id" name="cliente_id">
            <input type="hidden" name="editar_cliente" value="1">
            <input type="hidden" name="negocio_id" value="<?php echo (int)$negocio_id; ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre *</label>
                        <input type="text" id="edit_nombre" name="nombre" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_apellidos">Apellidos</label>
                        <input type="text" id="edit_apellidos" name="apellidos" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_telefono">Teléfono</label>
                        <input type="text" id="edit_telefono" name="telefono" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_fecha_nacimiento">Fecha de Nacimiento</label>
                        <input type="date" id="edit_fecha_nacimiento" name="fecha_nacimiento" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="edit_notas">Notas</label>
                        <textarea id="edit_notas" name="notas" class="form-input" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Fusionar Duplicados -->
<div class="modal-overlay" id="mergeModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <h2>Fusionar Clientes Duplicados</h2>
            <button class="modal-close" onclick="closeModal('mergeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Los siguientes clientes tienen el mismo email. Selecciona cuáles deseas fusionar:</p>
            
            <?php if (!empty($duplicados)): ?>
                <?php foreach ($duplicados as $duplicado): ?>
                    <?php 
                    $idsArray = explode(',', $duplicado['ids']);
                    if (count($idsArray) >= 2):
                        $stmtDup = $pdo6->prepare("SELECT * FROM crm WHERE cliente_id IN (" . str_repeat('?,', count($idsArray) - 1) . "?) AND negocio_id = ?");
                        $stmtDup->execute(array_merge($idsArray, [$negocio_id]));
                        $clientesDuplicados = $stmtDup->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="merge-group">
                        <h4><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($duplicado['email']); ?></h4>
                        <form method="POST" class="merge-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="fusionar_clientes" value="1">
                            <input type="hidden" name="negocio_id" value="<?php echo $negocio_id; ?>">
                            
                            <div class="duplicate-clients">
                                <?php foreach ($clientesDuplicados as $index => $cliente): ?>
                                    <div class="duplicate-client">
                                        <input type="radio" name="cliente_principal" value="<?php echo $cliente['cliente_id']; ?>" 
                                               id="principal_<?php echo $cliente['cliente_id']; ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        <label for="principal_<?php echo $cliente['cliente_id']; ?>">
                                            <strong><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></strong><br>
                                            <small>Tel: <?php echo htmlspecialchars($cliente['telefono'] ?: 'N/A'); ?> | 
                                            Fecha: <?php echo htmlspecialchars($cliente['fecha_nacimiento'] ?: 'N/A'); ?></small>
                                            <?php if (!empty($cliente['notas'])): ?>
                                                <br><small>Notas: <?php echo htmlspecialchars($cliente['notas']); ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="merge-actions">
                                <select name="cliente_secundario" required>
                                    <option value="">Selecciona el cliente a fusionar</option>
                                    <?php foreach ($clientesDuplicados as $cliente): ?>
                                        <option value="<?php echo $cliente['cliente_id']; ?>">
                                            <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">Fusionar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No se encontraron clientes duplicados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php echo procesarNuevoCliente(); ?>

<script src="../../assets/js/sidebar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentClient = {};

// Configuración global de SweetAlert
if (typeof Swal !== 'undefined') {
    Swal.mixin({
        heightAuto: false,
        allowOutsideClick: false
    });
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
});

function initializeEventListeners() {
    // Botón añadir cliente
    document.getElementById('addClientBtn').addEventListener('click', function() {
        openModal('addClientModal');
    });

    // Dropdown de opciones
    const dropdownBtn = document.getElementById('dropdownBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');
    
    dropdownBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });

    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('show');
    });

    dropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Botón fusionar duplicados
    const mergeBtn = document.getElementById('mergeBtn');
    if (mergeBtn) {
        mergeBtn.addEventListener('click', function() {
            openModal('mergeModal');
            dropdownMenu.classList.remove('show');
        });
    }

    // Formularios de fusión
    document.querySelectorAll('.merge-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const principal = form.querySelector('input[name="cliente_principal"]:checked');
            const secundario = form.querySelector('select[name="cliente_secundario"]');
            
            if (!principal || !secundario.value) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Debes seleccionar ambos clientes para fusionar',
                    zIndex: 99999
                });
                return;
            }
            
            if (principal.value === secundario.value) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No puedes fusionar un cliente consigo mismo',
                    zIndex: 99999
                });
                return;
            }
            
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Esta acción fusionará los dos clientes seleccionados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, fusionar',
                cancelButtonText: 'Cancelar',
                zIndex: 99999
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = 'auto';
}

function validateClientForm(event) {
    const form = event.target;
    const telefono = form.querySelector('#telefono').value.trim();
    const email = form.querySelector('#email').value.trim();
    const nombre = form.querySelector('#nombre').value.trim();

    if (!nombre) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El nombre es obligatorio',
            zIndex: 99999
        });
        return false;
    }

    if (!telefono && !email) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Debes proporcionar al menos un teléfono o un email',
            zIndex: 99999
        });
        return false;
    }

    return true;
}

function showClientDetails(clientId, businessId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    document.getElementById('clientDetailsContent').innerHTML = '<div class="loading">Cargando...</div>';
    openModal('clientDetailsModal');

    fetch('functions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `accion=obtener_detalles_cliente&cliente_id=${clientId}&negocio_id=${businessId}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.cliente) {
            const cliente = data.cliente;
            currentClient = {
                id: clientId,
                negocio_id: businessId,
                nombre: cliente.nombre || '',
                apellidos: cliente.apellidos || '',
                telefono: cliente.telefono || '',
                email: cliente.email || '',
                fecha_nacimiento: cliente.fecha_nacimiento || '',
                notas: cliente.notas || ''
            };

            document.getElementById('clientDetailsContent').innerHTML = `
                <div class="client-details">
                    <div class="detail-row">
                        <div class="detail-group">
                            <p><strong>${cliente.nombre || 'Sin nombre'} ${cliente.apellidos || ''}</strong></p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-group">
                            <p><i class="fas fa-phone"></i> ${cliente.telefono || 'Sin teléfono'}</p>
                        </div>
                        <div class="detail-group">
                            <p><i class="fas fa-envelope"></i> ${cliente.email || 'Sin email'}</p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-group">
                            <p><i class="fas fa-birthday-cake"></i> ${cliente.fecha_nacimiento || 'Sin fecha'}</p>
                        </div>
                        <div class="detail-group">
                            <p><i class="fas fa-calendar-check"></i> ${cliente.numero_reservas || '0'} reservas</p>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-group">
                            <p><i class="fas fa-euro-sign"></i> ${cliente.importe_gastado ? parseFloat(cliente.importe_gastado).toFixed(2) + '€ gastados' : '0.00€ gastados'}</p>
                        </div>
                        <div class="detail-group">
                            <p><i class="fas fa-plus-circle"></i> Añadido: ${cliente.fecha_creacion ? new Date(cliente.fecha_creacion).toLocaleDateString('es-ES') : 'Fecha no disponible'}</p>
                        </div>
                    </div>
                    ${cliente.notas ? `<div class="detail-row"><div class="detail-group"><p><i class="fas fa-sticky-note"></i> ${cliente.notas}</p></div></div>` : ''}
                </div>
            `;

            // Configurar botones
            document.getElementById('editClientBtn').onclick = function() {
                closeModal('clientDetailsModal');
                editClient(currentClient);
            };

            document.getElementById('deleteClientBtn').onclick = function() {
                deleteClient(currentClient.id, currentClient.negocio_id);
            };

        } else {
            document.getElementById('clientDetailsContent').innerHTML = `
                <div class="error">Error al cargar los detalles: ${data.error || 'Error desconocido'}</div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('clientDetailsContent').innerHTML = `
            <div class="error">Error de conexión al cargar los detalles</div>
        `;
    });
}

function editClient(client) {
    document.getElementById('edit_cliente_id').value = client.id;
    document.getElementById('edit_nombre').value = client.nombre;
    document.getElementById('edit_apellidos').value = client.apellidos;
    document.getElementById('edit_telefono').value = client.telefono;
    document.getElementById('edit_email').value = client.email;
    document.getElementById('edit_fecha_nacimiento').value = client.fecha_nacimiento;
    document.getElementById('edit_notas').value = client.notas;

    openModal('editClientModal');
}

function deleteClient(clientId, businessId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Realmente deseas eliminar este cliente? Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        zIndex: 99999
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const inputs = [
                { name: 'cliente_id', value: clientId },
                { name: 'negocio_id', value: businessId },
                { name: 'csrf_token', value: csrfToken },
                { name: 'eliminar_cliente', value: '1' }
            ];

            inputs.forEach(input => {
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = input.name;
                hiddenField.value = input.value;
                form.appendChild(hiddenField);
            });

            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
</body>
</html>