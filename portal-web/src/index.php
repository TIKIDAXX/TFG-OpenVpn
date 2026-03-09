<?php
// =============================================================
// TFG-OpenVPN — Login con MFA
// Autor: Said Rais
// =============================================================

require_once 'config.php';
startSecureSession();

// Si ya esta autenticado redirigir al dashboard
if (!empty($_SESSION['user']) && !empty($_SESSION['mfa_ok'])) {
    header('Location: /dashboard.php');
    exit;
}

$error   = '';
$mfa_step = $_SESSION['mfa_pending'] ?? false;

// ── PASO 1: Login usuario + password ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son obligatorios.';
    } else {
        try {
            $db = getDB();

            // Verificar si la cuenta esta bloqueada
            $stmt = $db->prepare("SELECT id FROM blocked_accounts WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Cuenta bloqueada. Contacta con el administrador.';
                logAccess($username, 'login', 'BLOCKED');
            } else {
                // Buscar usuario en BD
                $stmt = $db->prepare("SELECT * FROM portal_users WHERE username = ? AND active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                // Verificar caducidad SUBADMIN
                if ($user && $user['role'] === 'SUBADMIN' && $user['expires_at']) {
                    if (new DateTime() > new DateTime($user['expires_at'])) {
                        $db->prepare("UPDATE portal_users SET active = 0 WHERE username = ?")->execute([$username]);
                        $error = 'Cuenta expirada. Contacta con el administrador.';
                        $user  = null;
                    }
                }

                if ($user && password_verify($password, $user['password'])) {
                    // Login correcto — limpiar intentos fallidos
                    $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);

                    // Guardar datos de sesion pendiente
                    $_SESSION['mfa_pending_user'] = $user;

                    // SOPORTE no necesita MFA
                    if ($user['role'] === 'SOPORTE') {
                        $_SESSION['user']   = $user;
                        $_SESSION['mfa_ok'] = true;
                        logAccess($username, 'login', 'SUCCESS', false);
                        notifyLogin($username, $user['role'], $ip, true);
                        // Primer acceso SUBADMIN (no aplica a SOPORTE pero por si acaso)
                        if ($user['first_login']) {
                            header('Location: /change-password.php');
                        } else {
                            header('Location: /dashboard.php');
                        }
                        exit;
                    }

                    // ADMIN y SUBADMIN necesitan MFA
                    $otp     = str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

                    // Eliminar OTPs anteriores del usuario
                    $db->prepare("DELETE FROM otp_codes WHERE username = ?")->execute([$username]);

                    // Guardar nuevo OTP
                    $db->prepare("INSERT INTO otp_codes (username, code, expires_at) VALUES (?, ?, ?)")
                       ->execute([$username, $otp, $expires]);

                    // Enviar OTP por Telegram
                    sendOTPTelegram($username, $otp);

                    $_SESSION['mfa_pending'] = true;
                    $mfa_step = true;

                } else {
                    // Login fallido
                    $db->prepare("INSERT INTO login_attempts (username, ip) VALUES (?, ?)")
                       ->execute([$username, $ip]);

                    // Contar intentos
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM login_attempts WHERE username = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                    $stmt->execute([$username]);
                    $attempts = $stmt->fetch()['total'];

                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $db->prepare("INSERT IGNORE INTO blocked_accounts (username, reason) VALUES (?, ?)")
                           ->execute([$username, "Demasiados intentos fallidos ({$attempts})."]);
                        logAccess($username, 'login', 'BLOCKED');
                        $error = 'Cuenta bloqueada tras demasiados intentos. Contacta con el administrador.';
                    } else {
                        logAccess($username, 'login', 'FAILED');
                        $error = "Usuario o contraseña incorrectos. Intento {$attempts}/" . MAX_LOGIN_ATTEMPTS . ".";
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error del sistema. Inténtalo de nuevo.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// ── PASO 2: Validar OTP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp_input = trim($_POST['otp'] ?? '');
    $user      = $_SESSION['mfa_pending_user'] ?? null;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!$user) {
        $error = 'Sesion expirada. Vuelve a iniciar sesion.';
        $mfa_step = false;
        unset($_SESSION['mfa_pending'], $_SESSION['mfa_pending_user']);
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM otp_codes WHERE username = ? AND code = ? AND used = 0 AND expires_at > NOW()");
            $stmt->execute([$user['username'], $otp_input]);
            $otp_row = $stmt->fetch();

            if ($otp_row) {
                // OTP valido
                $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$otp_row['id']]);
                $db->prepare("UPDATE portal_users SET last_login = NOW() WHERE username = ?")->execute([$user['username']]);

                $_SESSION['user']   = $user;
                $_SESSION['mfa_ok'] = true;
                unset($_SESSION['mfa_pending'], $_SESSION['mfa_pending_user']);

                logAccess($user['username'], 'login', 'SUCCESS', true);
                notifyLogin($user['username'], $user['role'], $ip, true);

                if ($user['first_login']) {
                    header('Location: /change-password.php');
                } else {
                    header('Location: /dashboard.php');
                }
                exit;
            } else {
                $error    = 'Codigo OTP invalido o expirado.';
                $mfa_step = true;
                logAccess($user['username'], 'otp_verify', 'FAILED');
            }
        } catch (Exception $e) {
            $error = 'Error del sistema. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Login</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1a1d27;
            border: 1px solid #2d3149;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 { font-size: 1.5rem; color: #7c83ff; }
        .logo p  { font-size: 0.85rem; color: #718096; margin-top: 0.3rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.85rem; color: #a0aec0; margin-bottom: 0.4rem; }
        input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #0f1117;
            border: 1px solid #2d3149;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #7c83ff; }
        button {
            width: 100%;
            padding: 0.85rem;
            background: #7c83ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 0.5rem;
        }
        button:hover { background: #6366f1; }
        .error {
            background: #2d1515;
            border: 1px solid #e53e3e;
            color: #fc8181;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
        }
        .mfa-info {
            background: #1a2744;
            border: 1px solid #2d4a8a;
            color: #90cdf4;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #718096;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>🔐 TFG-OpenVPN</h1>
        <p>Portal de Administración</p>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$mfa_step): ?>
    <!-- PASO 1: Login -->
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Usuario</label>
            <input type="text" name="username" placeholder="usuario" required autofocus>
        </div>
        <div class="form-group">
            <label>Contraseña</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit">Iniciar sesión</button>
    </form>

    <?php else: ?>
    <!-- PASO 2: OTP -->
    <div class="mfa-info">
        📱 Se ha enviado un código de 6 dígitos al canal privado de Telegram.<br>
        <small>Expira en <?= OTP_EXPIRY ?> segundos.</small>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="verify_otp">
        <div class="form-group">
            <label>Código OTP</label>
            <input type="text" name="otp" placeholder="000000" maxlength="6"
                   pattern="[0-9]{6}" inputmode="numeric" required autofocus
                   style="text-align:center; font-size:1.5rem; letter-spacing:0.5rem;">
        </div>
        <button type="submit">Verificar código</button>
    </form>
    <a class="back-link" href="/index.php">← Volver al login</a>
    <?php endif; ?>
</div>
</body>
</html>
