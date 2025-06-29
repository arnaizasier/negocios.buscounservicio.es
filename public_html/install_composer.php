<?php
// Script para instalar Composer y actualizar dependencias

// 1. Descargar Composer.phar si no existe
$composerPhar = __DIR__ . '/composer.phar';
if (!file_exists($composerPhar)) {
    echo "Descargando Composer...<br>";
    $composerUrl = 'https://getcomposer.org/composer-stable.phar';
    file_put_contents($composerPhar, file_get_contents($composerUrl));
    if (file_exists($composerPhar)) {
        echo "Composer descargado exitosamente.<br>";
    } else {
        die("Error al descargar Composer.");
    }
} else {
    echo "Composer ya está descargado.<br>";
}

// 2. Crear o sobrescribir composer.json
$composerJson = __DIR__ . '/composer.json';
echo "Creando o actualizando composer.json...<br>";
$jsonContent = [
    'require' => [
        'delight-im/auth' => '^8.3'
    ]
];
file_put_contents($composerJson, json_encode($jsonContent, JSON_PRETTY_PRINT));
echo "composer.json creado/actualizado.<br>";

// 3. Verificar si exec() está habilitado
if (!function_exists('exec')) {
    die("La función exec() está deshabilitada en este servidor. Contacta a tu hosting para habilitarla.");
}

// 4. Obtener la ruta del ejecutable PHP
$phpPath = trim(shell_exec('which php'));
if (empty($phpPath)) {
    $phpPath = '/usr/local/bin/php'; // Usar la ruta detectada previamente
}
echo "Ruta de PHP detectada: " . htmlspecialchars($phpPath) . "<br>";

// 5. Asegurar permisos de ejecución para composer.phar
chmod($composerPhar, 0755);
echo "Permisos de composer.phar establecidos a 0755.<br>";

// 6. Ejecutar Composer con 'update' para sincronizar
echo "Ejecutando Composer para actualizar dependencias...<br>";
$command = "$phpPath " . escapeshellarg($composerPhar) . ' update --no-dev 2>&1';
$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

echo "Resultado del comando:<br>";
echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
if ($returnVar === 0) {
    echo "Dependencias instaladas/actualizadas correctamente.<br>";
} else {
    die("Error al ejecutar Composer. Código de error: $returnVar");
}

// 7. Verificar instalación
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "¡Todo listo! Composer instaló las dependencias en 'vendor/'.<br>";
    echo "Puedes eliminar este script y usar tu aplicación.<br>";
} else {
    die("No se encontró vendor/autoload.php. Algo falló.");
}
?>