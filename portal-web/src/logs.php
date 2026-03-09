<?php
// =============================================================
// TFG-OpenVPN — Visor de logs
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth();

$filter    = $_GET['filter']    ?? '';
$container = $_GET['container'] ?? 'all';
$lines     = min((int)($_GET['lines'] ?? 100), 500);

// Contenedores disponibles
$containers = ['openvpn', 'mysql', 'portal-web', 'nginx', 'prometheus', 'grafana', 'loki', 'promtail'];

// Obtener logs via docker logs
function getDockerLogs(string $container, int $lines, string $filter): string {
    $cmd = "docker logs --tail={$lines} " . escapeshellarg($container) . " 2>&1";
    $output = shell_exec($cmd) ?? '';
    if ($filter) {
        $filtered = [];
        foreach (explode("\n", $output) as $line) {
            if (stripos($line, $filter) !== false) {
                $filtered[] = $line;
            }
        }
        return implode("\n", $filtered);
    }
    return $output;
}

// Logs de acceso al portal (solo ADMIN)
function getAccessLogs(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 100");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

$logs        = '';
$access_logs = [];

if ($user['role'] === 'ADMIN') {
    $access_logs = getAccessLogs();
}

if ($container !== 'all' && in_array($container, $containers)) {
    $logs = getDockerLogs($container, $lines, $filter);
} elseif ($container === 'openvpn') {
    $logs = getDockerLogs('openvpn', $lines, $filter);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Logs</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; }
        .nav { background: #1a1d27; border-bottom: 1px solid #2d3149; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 60px; }
        .nav h1 { color: #7c83ff; font-size: 1.2rem; }
        .nav-links a { color: #a0aec0; text-decoration: none; margin-left: 1.5rem; font-size: 0.9rem; }
        .nav-links a:hover { color: #7c83ff; }
        .logout { color: #fc8181 !important; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .card { background: #1a1d27; border: 1px solid #2d3149; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1rem; font-weight: 600; color: #a0aec0; margin-bottom: 1rem; }
        .filters { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        select, input[type=text], input[type=number] {
            background: #0f1117; border: 1px solid #2d3149; color: #e2e8f0;
            padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.9rem;
        }
        button[type=submit] {
            background: #7c83ff; color: white; border: none;
            padding: 0.5rem 1.25rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem;
        }
        .log-box {
            background: #0a0c10;
            border: 1px solid #2d3149;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.78rem;
            line-height: 1.6;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            color: #a0aec0;
        }
        .log-box .error   { color: #fc8181; }
        .log-box .warning { color: #f6e05e; }
        .log-box .info    { color: #63b3ed; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { text-align: left; padding: 0.6rem 0.75rem; color: #718096; font-size: 0.78rem; text-transform: uppercase; border-bottom: 1px solid #2d3149; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #1a1d27; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-green  { background: #1a3a2a; color: #48bb78; }
        .badge-red    { background: #2d1515; color: #fc8181; }
        .badge-yellow { background: #2d2215; color: #f6e05e; }
    </style>
</head>
<body>
<nav class="nav">
    <h1>🔐 TFG-OpenVPN</h1>
    <div class="nav-links">
        <a href="/dashboard.php">Dashboard</a>
        <a href="/vpn-status.php">VPN</a>
        <a href="/logs.php">Logs</a>
        <a href="/grafana-embed.php">Grafana</a>
        <?php if ($user['role'] === 'ADMIN'): ?>
        <a href="/users.php">Usuarios</a>
        <?php endif; ?>
        <a href="/logout.php" class="logout">Salir</a>
    </div>
</nav>

<div class="container">

    <!-- Logs de contenedores -->
    <div class="card">
        <div class="card-title">📋 Logs de contenedores</div>
        <form method="GET" class="filters">
            <select name="container">
                <?php foreach ($containers as $c): ?>
                <option value="<?= $c ?>" <?= $container === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Filtrar por texto...">
            <input type="number" name="lines" value="<?= $lines ?>" min="10" max="500" style="width:80px">
            <button type="submit">Ver logs</button>
        </form>
        <?php if ($logs): ?>
        <div class="log-box"><?= htmlspecialchars($logs) ?></div>
        <?php else: ?>
        <p style="color:#718096; font-size:0.9rem">Selecciona un contenedor y pulsa "Ver logs".</p>
        <?php endif; ?>
    </div>

    <!-- Log de accesos al portal (solo ADMIN) -->
    <?php if ($user['role'] === 'ADMIN' && !empty($access_logs)): ?>
    <div class="card">
        <div class="card-title">🔐 Log de accesos al portal</div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>IP</th>
                    <th>Rol</th>
                    <th>Accion</th>
                    <th>Resultado</th>
                    <th>MFA</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($access_logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['ip']) ?></td>
                    <td><?= htmlspecialchars($log['role'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td>
                        <span class="badge <?= $log['result'] === 'SUCCESS' ? 'badge-green' : ($log['result'] === 'BLOCKED' ? 'badge-yellow' : 'badge-red') ?>">
                            <?= $log['result'] ?>
                        </span>
                    </td>
                    <td><?= $log['mfa_used'] ? '✅' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
