<?php
// =============================================================
// TFG-OpenVPN — Configuracion central del portal
// Autor: Said Rais
// =============================================================

// ── Base de datos ────────────────────────────────────────────
define('DB_HOST',     getenv('MYSQL_HOST')     ?: 'mysql');
define('DB_PORT',     getenv('MYSQL_PORT')     ?: '3306');
define('DB_NAME',     getenv('MYSQL_DATABASE') ?: 'panelvpn');
define('DB_USER',     getenv('MYSQL_USER')     ?: 'paneluser');
define('DB_PASS',     getenv('MYSQL_PASSWORD') ?: '');

// ── LDAP / Active Directory ──────────────────────────────────
define('LDAP_HOST',     getenv('AD_HOST')      ?: 'localhost');
define('LDAP_PORT',     getenv('AD_PORT')      ?: '389');
define('LDAP_BASE_DN',  getenv('AD_BASE_DN')   ?: 'dc=domainsaid,dc=internal');
define('LDAP_BIND_USER',getenv('AD_BIND_USER') ?: '');
define('LDAP_BIND_PASS',getenv('AD_BIND_PASS') ?: '');

// ── Sesion ───────────────────────────────────────────────────
define('SESSION_SECRET', getenv('PORTAL_SESSION_SECRET') ?: 'changeme');
define('SESSION_LIFETIME', 3600); // 1 hora

// ── MFA / OTP ────────────────────────────────────────────────
define('OTP_EXPIRY',  getenv('OTP_EXPIRY_SECONDS') ?: 90);
define('OTP_LENGTH',  getenv('OTP_LENGTH')          ?: 6);

// ── Telegram ─────────────────────────────────────────────────
define('TELEGRAM_TOKEN',    getenv('TELEGRAM_BOT_TOKEN')  ?: '');
define('TELEGRAM_CHAT_MFA', getenv('TELEGRAM_CHAT_MFA')   ?: '');

// ── OpenVPN Management ───────────────────────────────────────
define('OPENVPN_MGMT_HOST', 'openvpn');
define('OPENVPN_MGMT_PORT', getenv('OPENVPN_MGMT_PORT') ?: '7505');

// ── Seguridad ────────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('SUBADMIN_MAX_DAYS',  15);

// ── Conexion PDO ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Sesion segura ────────────────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── Verificar autenticacion ──────────────────────────────────
function requireAuth(array $roles = []): array {
    startSecureSession();
    if (empty($_SESSION['user'])) {
        header('Location: /index.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        die('<h1>403 — Acceso denegado</h1>');
    }
    return $_SESSION['user'];
}

// ── Log de acceso ────────────────────────────────────────────
function logAccess(string $username, string $action, string $result, bool $mfa = false): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $role = $_SESSION['user']['role'] ?? null;
        $stmt = $db->prepare("INSERT INTO access_logs (username, ip, role, action, result, mfa_used) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $ip, $role, $action, $result, $mfa ? 1 : 0]);
    } catch (Exception $e) {
        error_log("Error logging access: " . $e->getMessage());
    }
}

// ── Enviar OTP por Telegram ──────────────────────────────────
function sendOTPTelegram(string $username, string $otp): bool {
    $token   = TELEGRAM_TOKEN;
    $chat_id = TELEGRAM_CHAT_MFA;

    if (empty($token) || empty($chat_id) || $chat_id === '0') {
        error_log("Telegram no configurado — OTP: $otp");
        return false;
    }

    $msg = "🔐 *Codigo MFA — Portal TFG-OpenVPN*\n\n"
         . "Usuario: `$username`\n"
         . "Codigo: `$otp`\n"
         . "Expira en: " . OTP_EXPIRY . " segundos\n\n"
         . "⚠️ No compartas este codigo.";

    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'Markdown'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result !== false;
}

// ── Notificar login al bot ───────────────────────────────────
function notifyLogin(string $username, string $role, string $ip, bool $success): void {
    $token   = TELEGRAM_TOKEN;
    $chat_id = TELEGRAM_CHAT_MFA;

    if (empty($token) || empty($chat_id) || $chat_id === '0') return;

    $icon = $success ? '✅' : '❌';
    $msg  = "{$icon} *Login Portal TFG-OpenVPN*\n\n"
          . "Usuario: `$username`\n"
          . "Rol: `$role`\n"
          . "IP: `$ip`\n"
          . "Resultado: " . ($success ? 'Exitoso' : 'Fallido') . "\n"
          . "Hora: " . date('Y-m-d H:i:s');

    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $msg, 'parse_mode' => 'Markdown'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
