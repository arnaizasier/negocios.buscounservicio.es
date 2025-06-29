<?php
function requireLogin($redirectIfNotLogged = '/auth/login.php') {
    global $auth;

    if (!$auth->isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirectIfNotLogged");
        exit;
    }
}

function requireRole($requiredRole) {
    global $auth, $pdo;

    $user_id = $auth->getUserId();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== $requiredRole) {
        echo "<div class='alert alert-danger'>Acceso denegado. Este contenido es solo para usuarios con rol <strong>" . htmlspecialchars($requiredRole, ENT_QUOTES, 'UTF-8') . "</strong>.</div>";
        exit;
    }
}
?>