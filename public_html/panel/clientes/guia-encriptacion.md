# Guía de Encriptación y Desencriptación

## Descripción General

Este sistema utiliza **AES-256-GCM** para encriptar datos sensibles de clientes como nombres, teléfonos y notas. Es un método de encriptación simétrica muy seguro que proporciona tanto confidencialidad como autenticidad de los datos.

## Configuración Requerida

### Variables de Entorno
Necesitas definir estas constantes en tu archivo de configuración:

```php
define('ENCRYPT_KEY', 'tu_clave_secreta_muy_larga_y_segura');
define('ENCRYPT_SALT', 'tu_salt_adicional_para_mayor_seguridad');
```

**Importante:** 
- La clave debe ser única y secreta
- Nunca cambies estas claves una vez que tengas datos encriptados
- Guárdalas de forma segura (variables de entorno recomendadas)

## Función de Encriptación

```php
function encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    
    $cipher = 'AES-256-GCM';
    $key = hash('sha256', ENCRYPT_KEY . ENCRYPT_SALT);
    $iv = random_bytes(12); // IV aleatorio de 12 bytes para GCM
    $tag = '';
    
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($encrypted === false) {
        return '';
    }
    
    // Combinar IV + tag + datos encriptados y codificar en base64
    return base64_encode($iv . $tag . $encrypted);
}
```

### Cómo Funciona:
1. **Validación**: Verifica que hay datos para encriptar
2. **Clave derivada**: Combina ENCRYPT_KEY + ENCRYPT_SALT y aplica SHA-256
3. **IV aleatorio**: Genera un vector de inicialización único de 12 bytes
4. **Encriptación**: Usa AES-256-GCM para encriptar los datos
5. **Empaquetado**: Combina IV + tag de autenticación + datos encriptados
6. **Codificación**: Convierte todo a Base64 para almacenamiento seguro

## Función de Desencriptación

```php
function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    
    $data = base64_decode($encrypted_data);
    if ($data === false || strlen($data) < 28) { // 12 (IV) + 16 (tag) mínimo
        return '';
    }
    
    $cipher = 'AES-256-GCM';
    $key = hash('sha256', ENCRYPT_KEY . ENCRYPT_SALT);
    
    // Extraer IV (12 bytes), tag (16 bytes) y datos encriptados
    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $encrypted = substr($data, 28);
    
    $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    return $decrypted !== false ? $decrypted : '';
}
```

### Cómo Funciona:
1. **Validación**: Verifica que hay datos para desencriptar
2. **Decodificación**: Convierte de Base64 a datos binarios
3. **Verificación de tamaño**: Asegura que hay suficientes bytes (mínimo 28)
4. **Clave derivada**: Regenera la misma clave usada para encriptar
5. **Extracción**: Separa IV, tag de autenticación y datos encriptados
6. **Desencriptación**: Usa AES-256-GCM para recuperar los datos originales
7. **Verificación**: El tag GCM verifica automáticamente la integridad

## Uso Práctico

### Encriptar datos antes de guardar en BD:
```php
$nombre_encriptado = encrypt_data($nombre);
$telefono_encriptado = encrypt_data($telefono);
$notas_encriptadas = encrypt_data($notas);

$stmt = $pdo->prepare("INSERT INTO crm (nombre, telefono, notas) VALUES (?, ?, ?)");
$stmt->execute([$nombre_encriptado, $telefono_encriptado, $notas_encriptadas]);
```

### Desencriptar datos al recuperar de BD:
```php
$stmt = $pdo->prepare("SELECT * FROM crm WHERE cliente_id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_real = decrypt_data($cliente['nombre']);
$telefono_real = decrypt_data($cliente['telefono']);
$notas_reales = decrypt_data($cliente['notas']);
```

### En tu código actual se usa así:
```php
// Al procesar formularios
foreach ($todos_clientes as &$cliente) {
    $cliente['nombre'] = decrypt_data($cliente['nombre']);
    $cliente['telefono'] = decrypt_data($cliente['telefono']);
    $cliente['notas'] = decrypt_data($cliente['notas']);
}

// Al guardar nuevos clientes
$stmt->execute([
    $user_id, 
    $negocio_id, 
    encrypt_data($nombre), 
    $apellidos, 
    encrypt_data($telefono), 
    $email, 
    $fecha_nacimiento, 
    encrypt_data($notas)
]);
```

## Campos que se Encriptan vs No Encriptan

### ✅ Campos Encriptados (PII sensible):
- `nombre` - Nombre del cliente
- `telefono` - Número de teléfono
- `notas` - Notas privadas sobre el cliente

### ❌ Campos NO Encriptados (necesarios para búsquedas):
- `apellidos` - Se mantiene sin encriptar para búsquedas
- `email` - Se mantiene sin encriptar para validaciones y búsquedas
- `fecha_nacimiento` - Información menos sensible
- `cliente_id`, `usuario_id`, `negocio_id` - IDs del sistema

## Ventajas del Sistema AES-256-GCM

1. **Confidencialidad**: Los datos están protegidos contra lectura no autorizada
2. **Integridad**: El tag GCM detecta cualquier modificación de los datos
3. **Autenticidad**: Garantiza que los datos no han sido alterados
4. **IV único**: Cada encriptación usa un vector diferente
5. **Resistente a ataques**: AES-256 es estándar militar

## Consideraciones de Seguridad

### ⚠️ Importante:
- **Nunca cambies las claves** una vez que tengas datos encriptados
- **Haz backups seguros** de las claves de encriptación
- **Usa HTTPS** siempre para transmitir datos
- **Valida y sanitiza** todos los inputs antes de encriptar
- **Limita el acceso** a las funciones de encriptación

### 🔒 Mejores Prácticas:
- Almacena las claves en variables de entorno
- Usa diferentes claves para diferentes entornos (desarrollo/producción)
- Implementa rotación de claves si es posible
- Monitorea intentos de acceso no autorizado
- Considera encriptar a nivel de base de datos también

## Troubleshooting

### Error: "Datos encriptados inválidos"
- Verifica que las claves ENCRYPT_KEY y ENCRYPT_SALT sean correctas
- Asegúrate de que los datos no se han corrompido en la base de datos

### Error: "Función openssl no disponible"
- Verifica que PHP tenga la extensión OpenSSL habilitada
- En Ubuntu/Debian: `sudo apt-get install php-openssl`

### Datos se muestran como cadenas raras
- Los datos encriptados siempre deben pasarse por `decrypt_data()` antes de mostrar
- Nunca muestres directamente datos encriptados al usuario 