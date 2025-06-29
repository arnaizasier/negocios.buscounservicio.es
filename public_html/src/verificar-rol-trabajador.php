<?php
function requireWorkerLogin($redirectIfNotLogged = '/auth/login.php') {
    global $auth;

    if (!$auth->isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectIfNotLogged");
        exit;
    }
}

function getWorkerData($user_id, $pdo2) {
    $stmt = $pdo2->prepare("SELECT id, negocio_id, nombre, apellido, permisos FROM trabajadores WHERE cuenta_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function requireWorkerRole() {
    global $auth, $pdo2;

    $user_id = $auth->getUserId();
    $worker_data = getWorkerData($user_id, $pdo2);

    if (!$worker_data) {
        echo "<div class='alert alert-danger'>Acceso denegado. No tienes permisos como trabajador.</div>";
        exit;
    }

    return $worker_data;
}
?> 