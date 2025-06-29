<?php
// Estas configuraciones deben ir ANTES de session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); 
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax'); 
?>