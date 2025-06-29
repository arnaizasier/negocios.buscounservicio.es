<?php
require_once 'conexion.php';

$auth = new \Delight\Auth\Auth($pdo);

$usuario_id = verificarUsuarioAutenticado($auth);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$negocio_id = intval($_GET['id']);

$stmt = $pdoNegocios->prepare("SELECT * FROM negocios WHERE negocio_id = :negocio_id AND usuario_id = :usuario_id");
$stmt->execute([':negocio_id' => $negocio_id, ':usuario_id' => $usuario_id]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header('Location: index.php');
    exit();
}

if ($negocio) {
    $negocio['ubicacion'] = html_entity_decode($negocio['ubicacion'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $negocio['ciudad'] = html_entity_decode($negocio['ciudad'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $negocio['calle'] = html_entity_decode($negocio['calle'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function limpiarInput($data) {
        if (is_string($data)) {
            return trim(strip_tags($data));
        }
        return $data;
    }
    
    $ubicacion = limpiarInput($_POST['ubicacion'] ?? '');
    $ciudad = limpiarInput($_POST['ciudad'] ?? '');
    $calle = limpiarInput($_POST['calle'] ?? '');
    $lat = limpiarInput($_POST['lat'] ?? '');
    $log = limpiarInput($_POST['log'] ?? '');
    $ubicacion_adicional = limpiarInput($_POST['ubicacion_adicional'] ?? '');
    
    try {
        $stmt = $pdoNegocios->prepare("UPDATE negocios SET ubicacion = :ubicacion, ciudad = :ciudad, calle = :calle, lat = :lat, log = :log, ubicacion_adicional = :ubicacion_adicional 
                                      WHERE negocio_id = :negocio_id");
        $stmt->execute([
            ':ubicacion' => $ubicacion,
            ':ciudad' => $ciudad,
            ':calle' => $calle,
            ':lat' => $lat,
            ':log' => $log,
            ':ubicacion_adicional' => $ubicacion_adicional,
            ':negocio_id' => $negocio_id
        ]);
        
        header("Location: paso3?id=$negocio_id");
        exit();
    } catch (PDOException $e) {
        $error = "Error al guardar los datos: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Negocio - Ubicación</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/paso2.css">
    <link rel="stylesheet" href="/assets/css/marca.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Ubicación</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="ubicacion">Dirección</label>
                    <div class="input-search-container">
                        <input type="text" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($negocio['ubicacion'] ?? ''); ?>" placeholder="Ej: Calle Gran Vía 28, Madrid">
                        <button type="button" id="buscarDireccion">Buscar</button>
                    </div>
                    <div class="direccion-ayuda">Escribe la dirección completa de tu negocio</div>
                </div>

                <div class="form-group">
                    <label for="ubicacion_adicional">Datos Adicionales</label>
                    <input type="text" id="ubicacion_adicional" name="ubicacion_adicional" value="<?php echo htmlspecialchars($negocio['ubicacion_adicional'] ?? ''); ?>" placeholder="Piso, Puerta...">
                </div>
                
                <input type="hidden" id="ciudad" name="ciudad" value="<?php echo htmlspecialchars($negocio['ciudad'] ?? ''); ?>">
                
                <input type="hidden" id="calle" name="calle" value="<?php echo htmlspecialchars($negocio['calle'] ?? ''); ?>">
                
                <div class="mapa-instrucciones">Confirma la ubicación en el mapa</div>
                <div id="map"></div>
                <div class="direccion-ayuda">Puedes arrastrar el marcador para ajustar la ubicación exacta</div>
                
                <input type="hidden" id="lat" name="lat" value="<?php echo htmlspecialchars($negocio['lat'] ?? ''); ?>">
                <input type="hidden" id="log" name="log" value="<?php echo htmlspecialchars($negocio['log'] ?? ''); ?>">
                
                <div class="btn-nav">
                    <a href="index.php?id=<?php echo $negocio_id; ?>" class="button-secondary" style="display: inline-block; padding: 10px 20px; text-decoration: none;">Anterior</a>
                    <button type="submit">Siguiente</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var map = L.map('map').setView([40.416775, -3.703790], 6);
            var marker;
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            var latInput = document.getElementById('lat');
            var logInput = document.getElementById('log');
            
            if (latInput.value && logInput.value) {
                var lat = parseFloat(latInput.value);
                var lng = parseFloat(logInput.value);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 15);
                    marker = L.marker([lat, lng], {draggable: true}).addTo(map);
                    actualizarCoordenadas(marker);
                }
            }
            
            document.getElementById('buscarDireccion').addEventListener('click', function() {
                buscarDireccion();
            });
            
            document.getElementById('ubicacion').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarDireccion();
                }
            });
            
            function buscarDireccion() {
                var direccion = document.getElementById('ubicacion').value;
                
                if (!direccion) {
                    alert('Por favor, introduce una dirección para buscar');
                    return;
                }
                
                fetch('https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&q=' + encodeURIComponent(direccion))
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            var result = data[0];
                            var latlng = [result.lat, result.lon];
                            
                            map.setView(latlng, 16);
                            
                            if (marker) {
                                marker.setLatLng(latlng);
                            } else {
                                marker = L.marker(latlng, {draggable: true}).addTo(map);
                            }
                            
                            marker.on('dragend', function() {
                                actualizarCoordenadas(marker);
                                obtenerDatosDesdeCoordenadas(marker.getLatLng().lat, marker.getLatLng().lng);
                            });
                            
                            actualizarCoordenadas(marker);
                            
                            extraerDatosDeRespuesta(result);
                            
                            if (result.display_name) {
                                document.getElementById('ubicacion').value = decodeHTMLEntities(result.display_name);
                            }
                        } else {
                            alert('No se encontró la dirección. Por favor intenta con otra más específica.');
                        }
                    })
                    .catch(error => {
                        console.error('Error al buscar dirección:', error);
                        alert('Error al buscar la dirección. Por favor intenta de nuevo.');
                    });
            }
            
            function extraerDatosDeRespuesta(result) {
                if (result && result.address) {
                    console.log("Datos recibidos:", result.address);
                    
                    let ciudad = null;
                    let calle = null;
                    
                    if (result.address.city) {
                        ciudad = result.address.city;
                    } else if (result.address.town) {
                        ciudad = result.address.town;
                    } else if (result.address.municipality) {
                        ciudad = result.address.municipality;
                    } else if (result.address.village) {
                        ciudad = result.address.village;
                    } else if (result.address.county) {
                        ciudad = result.address.county;
                    } else if (result.address.state) {
                        ciudad = result.address.state;
                    }
                    
                    if (result.address.road || result.address.pedestrian || result.address.street) {
                        if (result.address.road) {
                            calle = result.address.road;
                        } else if (result.address.pedestrian) {
                            calle = result.address.pedestrian;
                        } else if (result.address.street) {
                            calle = result.address.street;
                        }
                        
                        if (result.address.house_number) {
                            calle += ' ' + result.address.house_number;
                        }
                    }
                    
                    if (ciudad) {
                        ciudad = decodeHTMLEntities(ciudad);
                        document.getElementById('ciudad').value = ciudad;
                        console.log("Ciudad actualizada:", ciudad);
                    }
                    
                    if (calle) {
                        calle = decodeHTMLEntities(calle);
                        document.getElementById('calle').value = calle;
                        console.log("Calle actualizada:", calle);
                    }
                }
            }
            
            function obtenerDatosDesdeCoordenadas(lat, lng) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&addressdetails=1&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.address) {
                            console.log("Datos de reverse:", data.address);
                            
                            let ciudad = null;
                            let calle = null;
                            
                            if (data.address.city) {
                                ciudad = data.address.city;
                            } else if (data.address.town) {
                                ciudad = data.address.town;
                            } else if (data.address.municipality) {
                                ciudad = data.address.municipality;
                            } else if (data.address.village) {
                                ciudad = data.address.village;
                            } else if (data.address.county) {
                                ciudad = data.address.county;
                            } else if (data.address.state) {
                                ciudad = data.address.state;
                            }
                            
                            if (data.address.road || data.address.pedestrian || data.address.street) {
                                if (data.address.road) {
                                    calle = data.address.road;
                                } else if (data.address.pedestrian) {
                                    calle = data.address.pedestrian;
                                } else if (data.address.street) {
                                    calle = data.address.street;
                                }
                                
                                if (data.address.house_number) {
                                    calle += ' ' + data.address.house_number;
                                }
                            }
                            
                            if (ciudad) {
                                ciudad = decodeHTMLEntities(ciudad);
                                document.getElementById('ciudad').value = ciudad;
                                console.log("Ciudad actualizada (reverse):", ciudad);
                            }
                            
                            if (calle) {
                                calle = decodeHTMLEntities(calle);
                                document.getElementById('calle').value = calle;
                                console.log("Calle actualizada (reverse):", calle);
                            }
                            
                            if (data.display_name) {
                                document.getElementById('ubicacion').value = decodeHTMLEntities(data.display_name);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener dirección:', error);
                    });
            }
            
            function decodeHTMLEntities(text) {
                if (!text) return '';
                
                var textarea = document.createElement('textarea');
                textarea.innerHTML = text;
                return textarea.value;
            }
            
            map.on('click', function(e) {
                if (marker) {
                    map.removeLayer(marker);
                }
                
                marker = L.marker(e.latlng, {draggable: true}).addTo(map);
                
                marker.on('dragend', function() {
                    actualizarCoordenadas(marker);
                    obtenerDatosDesdeCoordenadas(marker.getLatLng().lat, marker.getLatLng().lng);
                });
                
                actualizarCoordenadas(marker);
                
                obtenerDatosDesdeCoordenadas(e.latlng.lat, e.latlng.lng);
            });
            
            function actualizarCoordenadas(marker) {
                var position = marker.getLatLng();
                document.getElementById('lat').value = position.lat;
                document.getElementById('log').value = position.lng;
            }
            
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
            
            var ubicacionInput = document.getElementById('ubicacion');
            var ciudadInput = document.getElementById('ciudad');
            var calleInput = document.getElementById('calle');
            
            if (ubicacionInput.value) {
                ubicacionInput.value = decodeHTMLEntities(ubicacionInput.value);
            }
            
            if (ciudadInput.value) {
                ciudadInput.value = decodeHTMLEntities(ciudadInput.value);
            }
            
            if (calleInput.value) {
                calleInput.value = decodeHTMLEntities(calleInput.value);
            }
        });
    </script>
</body>
</html>