<?php
// =============================================================
// TFG-OpenVPN — Dashboard principal
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth();

// ── Estado de contenedores via Docker socket ─────────────────
function getContainerStatus(): array {
    $containers = ['openvpn', 'mysql', 'portal-web', 'nginx', 'prometheus', 'grafana', 'loki', 'promtail', 'bot-telegram', 'fail2ban'];
    $status = [];
    foreach ($containers as $name) {
        $output = shell_exec("docker inspect --format='{{.State.Status}}' $name 2>/dev/null");
        $status[$name] = trim($output ?: 'stopped');
    }
    return $status;
}

// ── Metricas del sistema via Prometheus ──────────────────────
function getSystemMetrics(): array {
    $metrics = ['cpu' => 0, 'ram' => 0, 'disk' => 0];
    try {
        // CPU
        $cpu = @file_get_contents('http://prometheus:9090/api/v1/query?query=100-(avg(rate(node_cpu_seconds_total{mode="idle"}[1m]))*100)');
        if ($cpu) {
            $data = json_decode($cpu, true);
            $metrics['cpu'] = round($data['data']['result'][0]['value'][1] ?? 0, 1);
        }
        // RAM
        $ram = @file_get_contents('http://prometheus:9090/api/v1/query?query=100-(node_memory_MemAvailable_bytes/node_memory_MemTotal_bytes*100)');
        if ($ram) {
            $data = json_decode($ram, true);
            $metrics['ram'] = round($data['data']['result'][0]['value'][1] ?? 0, 1);
        }
        // Disco
        $disk = @file_get_contents('http://prometheus:9090/api/v1/query?query=100-(node_filesystem_avail_bytes{mountpoint="/"}/node_filesystem_size_bytes{mountpoint="/"} *100)');
        if ($disk) {
            $data = json_decode($disk, true);
            $metrics['disk'] = round($data['data']['result'][0]['value'][1] ?? 0, 1);
        }
    } catch (Exception $e) {}
    return $metrics;
}

// ── Conexiones VPN activas ───────────────────────────────────
function getVPNConnections(): int {
    try {
        $sock = @fsockopen('openvpn', OPENVPN_MGMT_PORT, $errno, $errstr, 2);
        if (!$sock) return 0;
        fgets($sock);
        fwrite($sock, "status\n");
        $output = '';
        while (!feof($sock)) {
            $line = fgets($sock);
            $output .= $line;
            if (strpos($line, 'END') === 0) break;
        }
        fwrite($sock, "quit\n");
        fclose($sock);
        preg_match_all('/^CLIENT_LIST/m', $output, $matches);
        return count($matches[0]);
    } catch (Exception $e) {
        return 0;
    }
}

$containers = getContainerStatus();
$metrics    = getSystemMetrics();
$vpn_conns  = getVPNConnections();
$up_count   = count(array_filter($containers, fn($s) => $s === 'running'));
$total      = count($containers);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Dashboard</title>
    <meta http-equiv="refresh" content="30">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e2e8f0; }
        .nav {
            background: #1a1d27;
            border-bottom: 1px solid #2d3149;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .nav h1 { color: #7c83ff; font-size: 1.2rem; }
        .nav-links a {
            color: #a0aec0;
            text-decoration: none;
            margin-left: 1.5rem;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: #7c83ff; }
        .nav-user { font-size: 0.85rem; color: #718096; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .card {
            background: #1a1d27;
            border: 1px solid #2d3149;
            border-radius: 12px;
            padding: 1.5rem;
        }
        .card h2 { font-size: 0.85rem; color: #718096; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric { font-size: 2.5rem; font-weight: 700; }
        .metric.green { color: #48bb78; }
        .metric.yellow { color: #ecc94b; }
        .metric.red { color: #fc8181; }
        .metric.blue { color: #63b3ed; }
        .progress-bar {
            background: #2d3149;
            border-radius: 4px;
            height: 8px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        .fill-green  { background: #48bb78; }
        .fill-yellow { background: #ecc94b; }
        .fill-red    { background: #fc8181; }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .service {
            background: #0f1117;
            border: 1px solid #2d3149;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .dot.green  { background: #48bb78; box-shadow: 0 0 6px #48bb78; }
        .dot.red    { background: #fc8181; }
        .dot.grey   { background: #718096; }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #a0aec0;
            margin-bottom: 1rem;
        }
        .logout {
            color: #fc8181;
            text-decoration: none;
            font-size: 0.85rem;
            margin-left: 1.5rem;
        }
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
    <div class="nav-user">👤 <?= htmlspecialchars($user['username']) ?> (<?= $user['role'] ?>)</div>
</nav>

<div class="container">

    <!-- Métricas principales -->
    <div class="grid-4" style="margin-bottom:2rem">
        <div class="card">
            <h2>Servicios activos</h2>
            <div class="metric <?= $up_count === $total ? 'green' : ($up_count > $total/2 ? 'yellow' : 'red') ?>">
                <?= $up_count ?>/<?= $total ?>
            </div>
        </div>
        <div class="card">
            <h2>CPU</h2>
            <div class="metric <?= $metrics['cpu'] < 70 ? 'green' : ($metrics['cpu'] < 85 ? 'yellow' : 'red') ?>">
                <?= $metrics['cpu'] ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?= $metrics['cpu'] < 70 ? 'fill-green' : ($metrics['cpu'] < 85 ? 'fill-yellow' : 'fill-red') ?>"
                     style="width:<?= $metrics['cpu'] ?>%"></div>
            </div>
        </div>
        <div class="card">
            <h2>RAM</h2>
            <div class="metric <?= $metrics['ram'] < 70 ? 'green' : ($metrics['ram'] < 85 ? 'yellow' : 'red') ?>">
                <?= $metrics['ram'] ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?= $metrics['ram'] < 70 ? 'fill-green' : ($metrics['ram'] < 85 ? 'fill-yellow' : 'fill-red') ?>"
                     style="width:<?= $metrics['ram'] ?>%"></div>
            </div>
        </div>
        <div class="card">
            <h2>Clientes VPN</h2>
            <div class="metric blue"><?= $vpn_conns ?></div>
        </div>
    </div>

    <!-- Estado de servicios -->
    <div class="card" style="margin-bottom:2rem">
        <div class="section-title">Estado de servicios</div>
        <div class="services-grid">
            <?php foreach ($containers as $name => $status): ?>
            <div class="service">
                <div class="dot <?= $status === 'running' ? 'green' : ($status === 'exited' ? 'red' : 'grey') ?>"></div>
                <?= htmlspecialchars($name) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Disco -->
    <div class="card">
        <h2>Disco</h2>
        <div class="metric <?= $metrics['disk'] < 70 ? 'green' : ($metrics['disk'] < 80 ? 'yellow' : 'red') ?>">
            <?= $metrics['disk'] ?>%
        </div>
        <div class="progress-bar">
            <div class="progress-fill <?= $metrics['disk'] < 70 ? 'fill-green' : ($metrics['disk'] < 80 ? 'fill-yellow' : 'fill-red') ?>"
                 style="width:<?= $metrics['disk'] ?>%"></div>
        </div>
    </div>

</div>
</body>
</html>
