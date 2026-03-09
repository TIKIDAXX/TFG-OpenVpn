<?php
// =============================================================
// TFG-OpenVPN — Estado conexiones VPN
// Autor: Said Rais
// =============================================================

require_once 'config.php';
$user = requireAuth();

// ── Obtener conexiones via Management Interface ──────────────
function getVPNStatus(): array {
    $result = ['clients' => [], 'error' => null];
    try {
        $sock = @fsockopen('openvpn', OPENVPN_MGMT_PORT, $errno, $errstr, 2);
        if (!$sock) {
            $result['error'] = "No se puede conectar al Management Interface ($errstr)";
            return $result;
        }
        fgets($sock); // Banner
        fwrite($sock, "status 2\n");
        $output = '';
        $timeout = time() + 3;
        while (!feof($sock) && time() < $timeout) {
            $line = fgets($sock);
            $output .= $line;
            if (strpos($line, 'END') === 0) break;
        }
        fwrite($sock, "quit\n");
        fclose($sock);

        // Parsear CLIENT_LIST
        foreach (explode("\n", $output) as $line) {
            if (strpos($line, 'CLIENT_LIST') === 0) {
                $parts = explode(',', $line);
                if (count($parts) >= 8) {
                    $result['clients'][] = [
                        'common_name' => $parts[1],
                        'real_ip'     => $parts[2],
                        'vpn_ip'      => $parts[3],
                        'bytes_rx'    => $parts[5],
                        'bytes_tx'    => $parts[6],
                        'connected'   => $parts[7],
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    return $result;
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
    return $bytes . ' B';
}

$vpn = getVPNStatus();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFG-OpenVPN — Estado VPN</title>
    <meta http-equiv="refresh" content="15">
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
        .card-title { font-size: 1rem; font-weight: 600; color: #a0aec0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.8rem; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #2d3149; }
        td { padding: 0.75rem 1rem; font-size: 0.9rem; border-bottom: 1px solid #1a1d27; }
        tr:hover td { background: #0f1117; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #1a3a2a; color: #48bb78; }
        .error-box { background: #2d1515; border: 1px solid #e53e3e; color: #fc8181; padding: 1rem; border-radius: 8px; }
        .empty { text-align: center; color: #718096; padding: 2rem; }
        .stat { font-size: 2rem; font-weight: 700; color: #63b3ed; }
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
    <div class="card">
        <div class="card-title">📊 Resumen VPN</div>
        <div class="stat"><?= count($vpn['clients']) ?></div>
        <div style="color:#718096; font-size:0.85rem; margin-top:0.25rem">clientes conectados ahora mismo</div>
    </div>

    <?php if ($vpn['error']): ?>
    <div class="error-box">⚠️ <?= htmlspecialchars($vpn['error']) ?></div>
    <?php elseif (empty($vpn['clients'])): ?>
    <div class="card"><div class="empty">🔌 No hay clientes VPN conectados en este momento.</div></div>
    <?php else: ?>
    <div class="card">
        <div class="card-title">🌐 Clientes conectados</div>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>IP Real</th>
                    <th>IP VPN</th>
                    <th>↓ Recibido</th>
                    <th>↑ Enviado</th>
                    <th>Conectado desde</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vpn['clients'] as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['common_name']) ?></td>
                    <td><?= htmlspecialchars($c['real_ip']) ?></td>
                    <td><?= htmlspecialchars($c['vpn_ip']) ?></td>
                    <td><?= formatBytes((int)$c['bytes_rx']) ?></td>
                    <td><?= formatBytes((int)$c['bytes_tx']) ?></td>
                    <td><?= htmlspecialchars($c['connected']) ?></td>
                    <td><span class="badge badge-green">● Activo</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
