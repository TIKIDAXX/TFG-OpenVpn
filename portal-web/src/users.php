<?php
// =============================================================
// TFG-OpenVPN — Gestion de usuarios (solo ADMIN)
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth(['ADMIN']);

$msg   = '';
$error = '';

// ── Crear usuario portal ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_portal_user') {
    $new_user  = trim($_POST['username']   ?? '');
    $new_role  = $_POST['role']            ?? 'SOPORTE';
    $new_pass  = $_POST['password']        ?? '';
    $days      = (int)($_POST['days']      ?? 7);

    if (empty($new_user) || empty($new_pass)) {
        $error = 'Usuario y contraseña son obligatorios.';
    } elseif (!in_array($new_role, ['SUBADMIN', 'SOPORTE'])) {
        $error = 'Rol no válido.';
    } elseif ($new_role === 'SUBADMIN' && ($days < 1 || $days > SUBADMIN_MAX_DAYS)) {
        $error = "Los dias de caducidad deben estar entre 1 y " . SUBADMIN_MAX_DAYS . ".";
    } else {
        try {
            $db      = getDB();
            $hash    = password_hash($new_pass, PASSWORD_BCRYPT);
            $expires = $new_role === 'SUBADMIN' ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;

            $db->prepare("INSERT INTO portal_users (username, password, role, expires_at, created_by) VALUES (?, ?, ?, ?, ?)")
               ->execute([$new_user, $hash, $new_role, $expires, $user['username']]);

            logAccess($user['username'], "create_user:{$new_user}:{$new_role}", 'SUCCESS');
            $msg = "Usuario '{$new_user}' creado correctamente.";
        } catch (Exception $e) {
            $error = 'Error al crear el usuario. Puede que ya exista.';
        }
    }
}

// ── Desbloquear cuenta ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unblock') {
    $target = $_POST['username'] ?? '';
    try {
        $db = getDB();
        $db->prepare("DELETE FROM blocked_accounts WHERE username = ?")->execute([$target]);
        $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$target]);
        logAccess($user['username'], "unblock:{$target}", 'SUCCESS');
        $msg = "Cuenta '{$target}' desbloqueada.";
    } catch (Exception $e) {
        $error = 'Error al desbloquear la cuenta.';
    }
}

// ── Desactivar cuenta ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    $target = $_POST['username'] ?? '';
    if ($target === $user['username']) {
        $error = 'No puedes desactivar tu propia cuenta.';
    } else {
        try {
            $db = getDB();
            $db->prepare("UPDATE portal_users SET active = 0 WHERE username = ?")->execute([$target]);
            logAccess($user['username'], "deactivate:{$target}", 'SUCCESS');
            $msg = "Cuenta '{$target}' desactivada.";
        } catch (Exception $e) {
            $error = 'Error al desactivar la cuenta.';
        }
    }
}

// ── Obtener datos ────────────────────────────────────────────
$db            = getDB();
$portal_users  = $db->query("SELECT * FROM portal_users ORDER BY role, username")->fetchAll();
$blocked       = $db->query("SELECT * FROM blocked_accounts ORDER BY blocked_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Usuarios</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; }
        .nav { background: #1a1d27; border-bottom: 1px solid #2d3149; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 60px; }
        .nav h1 { color: #7c83ff; font-size: 1.2rem; }
        .nav-links a { color: #a0aec0; text-decoration: none; margin-left: 1.5rem; font-size: 0.9rem; }
        .nav-links a:hover { color: #7c83ff; }
        .logout { color: #fc8181 !important; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .card { background: #1a1d27; border: 1px solid #2d3149; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1rem; font-weight: 600; color: #a0aec0; margin-bottom: 1rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; align-items: end; }
        label { display: block; font-size: 0.8rem; color: #718096; margin-bottom: 0.3rem; }
        input, select { width: 100%; background: #0f1117; border: 1px solid #2d3149; color: #e2e8f0; padding: 0.6rem 0.75rem; border-radius: 6px; font-size: 0.9rem; }
        button[type=submit] { background: #7c83ff; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; width: 100%; }
        button.danger { background: #c53030; }
        button.warning { background: #b7791f; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { text-align: left; padding: 0.6rem 0.75rem; color: #718096; font-size: 0.78rem; text-transform: uppercase; border-bottom: 1px solid #2d3149; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #1a1d27; vertical-align: middle; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-admin    { background: #2d1a5a; color: #b794f4; }
        .badge-subadmin { background: #1a2d5a; color: #90cdf4; }
        .badge-soporte  { background: #1a3a2a; color: #48bb78; }
        .badge-inactive { background: #2d2215; color: #718096; }
        .msg   { background: #1a3a2a; border: 1px solid #48bb78; color: #48bb78; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .error { background: #2d1515; border: 1px solid #e53e3e; color: #fc8181; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .days-field { display: none; }
    </style>
    <script>
        function toggleDays(select) {
            document.querySelector('.days-field').style.display =
                select.value === 'SUBADMIN' ? 'block' : 'none';
        }
    </script>
</head>
<body>
<nav class="nav">
    <h1>🔐 TFG-OpenVPN</h1>
    <div class="nav-links">
        <a href="/dashboard.php">Dashboard</a>
        <a href="/vpn-status.php">VPN</a>
        <a href="/logs.php">Logs</a>
        <a href="/grafana-embed.php">Grafana</a>
        <a href="/users.php">Usuarios</a>
        <a href="/logout.php" class="logout">Salir</a>
    </div>
</nav>

<div class="container">
    <?php if ($msg):   ?><div class="msg">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Crear usuario portal -->
    <div class="card">
        <div class="card-title">➕ Crear usuario portal</div>
        <form method="POST">
            <input type="hidden" name="action" value="create_portal_user">
            <div class="form-grid">
                <div>
                    <label>Usuario</label>
                    <input type="text" name="username" required>
                </div>
                <div>
                    <label>Contraseña temporal</label>
                    <input type="text" name="password" required>
                </div>
                <div>
                    <label>Rol</label>
                    <select name="role" onchange="toggleDays(this)">
                        <option value="SOPORTE">SOPORTE</option>
                        <option value="SUBADMIN">SUBADMIN</option>
                    </select>
                </div>
                <div class="days-field">
                    <label>Dias de caducidad (max <?= SUBADMIN_MAX_DAYS ?>)</label>
                    <input type="number" name="days" value="7" min="1" max="<?= SUBADMIN_MAX_DAYS ?>">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit">Crear usuario</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Lista de usuarios -->
    <div class="card">
        <div class="card-title">👥 Usuarios del portal</div>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Expira</th>
                    <th>Ultimo login</th>
                    <th>Creado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($portal_users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($u['role']) ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $u['active'] ? 'badge-soporte' : 'badge-inactive' ?>">
                            <?= $u['active'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td><?= $u['expires_at'] ? htmlspecialchars($u['expires_at']) : '—' ?></td>
                    <td><?= $u['last_login'] ? htmlspecialchars($u['last_login']) : 'Nunca' ?></td>
                    <td><?= htmlspecialchars($u['created_by'] ?? '—') ?></td>
                    <td>
                        <?php if ($u['username'] !== $user['username'] && $u['active']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Desactivar usuario?')">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
                            <button type="submit" class="danger" style="width:auto;padding:0.3rem 0.6rem;font-size:0.8rem">Desactivar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Cuentas bloqueadas -->
    <?php if (!empty($blocked)): ?>
    <div class="card">
        <div class="card-title">🔒 Cuentas bloqueadas</div>
        <table>
            <thead>
                <tr><th>Usuario</th><th>Bloqueado</th><th>Motivo</th><th>Accion</th></tr>
            </thead>
            <tbody>
                <?php foreach ($blocked as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['username']) ?></td>
                    <td><?= htmlspecialchars($b['blocked_at']) ?></td>
                    <td><?= htmlspecialchars($b['reason'] ?? '—') ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="unblock">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($b['username']) ?>">
                            <button type="submit" class="warning" style="width:auto;padding:0.3rem 0.6rem;font-size:0.8rem">Desbloquear</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
