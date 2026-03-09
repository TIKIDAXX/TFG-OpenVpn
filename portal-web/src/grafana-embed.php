<?php
// =============================================================
// TFG-OpenVPN — Grafana embebido
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth();

$dashboards = [
    'sistema'      => ['name' => 'Sistema',      'uid' => 'rYdddlPWk',  'title' => 'Node Exporter Full'],
    'contenedores' => ['name' => 'Contenedores', 'uid' => 'cadvisor1',  'title' => 'Docker cAdvisor'],
    'logs'         => ['name' => 'Logs',         'uid' => 'loki-logs1', 'title' => 'TFG-OpenVPN Logs'],
];

$selected = $_GET['dashboard'] ?? 'sistema';
if (!isset($dashboards[$selected])) $selected = 'sistema';

$dash    = $dashboards[$selected];
$grafana = rtrim(getenv('GRAFANA_URL') ?: 'http://grafana:3000', '/');

// Construir URL del iframe con parametros de embedding
$iframe_url = "http://192.168.1.140:3000/d/{$dash['uid']}?orgId=1&refresh=30s&kiosk=tv&theme=dark";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Grafana</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; display: flex; flex-direction: column; height: 100vh; }
        .nav { background: #1a1d27; border-bottom: 1px solid #2d3149; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 60px; flex-shrink: 0; }
        .nav h1 { color: #7c83ff; font-size: 1.2rem; }
        .nav-links a { color: #a0aec0; text-decoration: none; margin-left: 1.5rem; font-size: 0.9rem; }
        .nav-links a:hover { color: #7c83ff; }
        .logout { color: #fc8181 !important; }
        .tabs { background: #1a1d27; border-bottom: 1px solid #2d3149; padding: 0 2rem; display: flex; gap: 0.5rem; flex-shrink: 0; }
        .tab { padding: 0.75rem 1.25rem; font-size: 0.9rem; color: #718096; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab:hover { color: #e2e8f0; }
        .tab.active { color: #7c83ff; border-bottom-color: #7c83ff; }
        .iframe-container { flex: 1; overflow: hidden; }
        iframe { width: 100%; height: 100%; border: none; }
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

<div class="tabs">
    <?php foreach ($dashboards as $key => $d): ?>
    <a href="?dashboard=<?= $key ?>" class="tab <?= $selected === $key ? 'active' : '' ?>">
        <?= $d['name'] ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="iframe-container">
    <iframe src="<?= htmlspecialchars($iframe_url) ?>"
            allowfullscreen
            sandbox="allow-same-origin allow-scripts allow-popups allow-forms">
    </iframe>
</div>
</body>
</html>
