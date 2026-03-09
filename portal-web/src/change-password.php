<?php
// =============================================================
// TFG-OpenVPN — Cambio de contrasena obligatorio (primer acceso)
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth();

// Si no es primer acceso redirigir
if (!$user['first_login']) {
    header('Location: /dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass     = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $db   = getDB();
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE portal_users SET password = ?, first_login = 0 WHERE username = ?")
               ->execute([$hash, $user['username']]);
            $_SESSION['user']['first_login'] = 0;
            header('Location: /dashboard.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error al cambiar la contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TFG-OpenVPN — Cambiar contraseña</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #1a1d27; border: 1px solid #2d3149; border-radius: 12px; padding: 2.5rem; width: 100%; max-width: 420px; }
        h1 { color: #7c83ff; margin-bottom: 0.5rem; }
        p  { color: #718096; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.85rem; color: #a0aec0; margin-bottom: 0.4rem; }
        input { width: 100%; padding: 0.75rem 1rem; background: #0f1117; border: 1px solid #2d3149; border-radius: 8px; color: #e2e8f0; font-size: 0.95rem; }
        input:focus { outline: none; border-color: #7c83ff; }
        button { width: 100%; padding: 0.85rem; background: #7c83ff; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 0.5rem; }
        .error { background: #2d1515; border: 1px solid #e53e3e; color: #fc8181; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .warning { background: #2d2215; border: 1px solid #d69e2e; color: #f6e05e; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>🔑 Cambio de contraseña</h1>
    <p>Es tu primer acceso. Debes cambiar la contraseña antes de continuar.</p>

    <div class="warning">⚠️ Elige una contraseña segura de al menos 8 caracteres.</div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nueva contraseña</label>
            <input type="password" name="new_password" required minlength="8">
        </div>
        <div class="form-group">
            <label>Confirmar contraseña</label>
            <input type="password" name="confirm_password" required minlength="8">
        </div>
        <button type="submit">Cambiar contraseña</button>
    </form>
</div>
</body>
</html>
