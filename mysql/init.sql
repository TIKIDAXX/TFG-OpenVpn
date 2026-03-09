-- =============================================================
-- TFG-OpenVPN — MySQL Init
-- Autor: Said Rais
-- Descripcion: Tablas del portal web
-- =============================================================

CREATE DATABASE IF NOT EXISTS panelvpn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE panelvpn;

-- ── Usuarios del portal ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS portal_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'SUBADMIN', 'SOPORTE') NOT NULL DEFAULT 'SOPORTE',
    active TINYINT(1) NOT NULL DEFAULT 1,
    first_login TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(50) NULL,
    last_login DATETIME NULL
);

-- ── Log de accesos al portal ─────────────────────────────────
CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    role VARCHAR(20) NULL,
    action VARCHAR(100) NOT NULL,
    result ENUM('SUCCESS', 'FAILED', 'BLOCKED') NOT NULL,
    mfa_used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── Codigos OTP para MFA ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── Intentos fallidos de login ───────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── Cuentas bloqueadas ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS blocked_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_by VARCHAR(50) NULL,
    reason VARCHAR(255) NULL
);

-- ── Usuario ADMIN por defecto ────────────────────────────────
-- Password: admin1234 (cambiar en produccion)
INSERT INTO portal_users (username, password, role, active, first_login)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'ADMIN',
    1,
    0
) ON DUPLICATE KEY UPDATE username=username;
