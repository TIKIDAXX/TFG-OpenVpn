# Fase 3 — Prometheus + Grafana + Loki

> TFG-OpenVPN · Said Rais · CFGS ASIR 2º · IES Isabel de Villena · 2024/2025

---

## Objetivo

Desplegar el stack completo de monitorización: métricas del sistema y contenedores
con Prometheus + Grafana, y agregación de logs con Loki + Promtail.

---

## Infraestructura de esta fase

```
Raspberry Pi 5
│
├── Prometheus (9090)     ← Recoge métricas
│   ├── Node Exporter     ← Métricas del sistema (CPU, RAM, disco)
│   └── cAdvisor          ← Métricas de contenedores Docker
│
├── Grafana (3000)        ← Visualización de métricas y logs
│   ├── Datasource Prometheus
│   └── Datasource Loki
│
└── Loki (3100)           ← Agregación de logs
    └── Promtail          ← Recoge logs de todos los contenedores
```

---

## Checklist

- [x] Prometheus arrancado y healthy
- [x] Node Exporter recogiendo métricas del sistema
- [x] cAdvisor recogiendo métricas de contenedores
- [x] Grafana accesible en puerto 3000
- [x] Datasource Prometheus configurado en Grafana
- [x] Datasource Loki configurado en Grafana
- [x] Loki arrancado y ready
- [x] Promtail detectando todos los contenedores
- [x] Loki recibiendo logs de todos los contenedores
- [x] Dashboard sistema (ID 1860) con datos
- [x] Dashboard contenedores (ID 15798) con datos
- [x] Dashboard logs TFG-OpenVPN custom con datos

---

## Archivos generados en esta fase

```
monitoring/
├── prometheus/
│   └── prometheus.yml              — Configuracion scrape jobs
├── loki/
│   └── loki-config.yml             — Configuracion Loki
├── promtail/
│   └── promtail-config.yml         — Configuracion Promtail
└── grafana/
    └── provisioning/
        ├── datasources/
        │   └── datasources.yml     — Prometheus + Loki datasources
        └── dashboards/
            └── dashboards.yml      — Provisioning de dashboards
```

---

## Pasos de implementacion

### PASO 1 — Subir archivos al repo y actualizar la Pi

```bash
cd /opt/tfg-openvpn
git pull
```

---

### PASO 2 — Arrancar el stack de monitorizacion

```bash
docker compose up -d prometheus node-exporter cadvisor grafana loki promtail
```

---

### PASO 3 — Verificar servicios

```bash
# Prometheus healthy
curl -s http://localhost:9090/-/healthy

# Loki ready
curl -s http://localhost:3100/ready

# Targets de Prometheus
curl -s "http://localhost:9090/api/v1/targets" | python3 -m json.tool | grep -E '"health"|"job"'

# Labels en Loki
curl -s "http://localhost:3100/loki/api/v1/label/container/values" | python3 -m json.tool
```

---

### PASO 4 — Verificar Grafana

Acceder en el navegador:
```
http://192.168.1.140:3000
```
Usuario: `admin`
Password: valor de `GRAFANA_ADMIN_PASSWORD` en `.env`

---

### PASO 5 — Importar dashboards

En Grafana → Dashboards → Import:

| Dashboard | ID | Datasource |
|-----------|-----|-----------|
| Node Exporter Full | 1860 | Prometheus |
| Docker cAdvisor | 15798 | Prometheus |
| TFG-OpenVPN Logs | Custom | Loki |

Dashboard custom de Loki con 3 paneles tipo Logs:
- `{container=~".+"}` — Todos los contenedores
- `{container="openvpn"}` — Solo OpenVPN
- `{container=~".+"} |= "error"` — Solo errores

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| Loki `Ingester not ready` al arrancar | Loki tarda 15-30s en inicializarse | Esperar y volver a hacer curl |
| Promtail `Cannot connect to Docker socket` | Faltaba montar `/var/run/docker.sock` en el contenedor | Añadir `- /var/run/docker.sock:/var/run/docker.sock` al volumen de Promtail en docker-compose.yml |
| Dashboard cAdvisor ID 14282 sin datos | Dashboard incompatible con metricas ARM/Raspberry Pi | Usar ID 15798 compatible con arquitectura ARM |
| Dashboard Loki ID 13639 sin datos | Queries del dashboard no coinciden con labels de nuestro setup | Crear dashboard propio con queries `{container=~".+"}` |
| cAdvisor no responde en puerto 8080 | Puerto no mapeado externamente — solo accesible dentro de red Docker | Normal — Prometheus lo scrape internamente, no hace falta exposicion externa |

---

## Resultado de la verificacion

```
# Prometheus
Prometheus Server is Healthy.

# Loki
ready

# Targets Prometheus
"job": "cadvisor"     "health": "up"
"job": "node-exporter" "health": "up"
"job": "openvpn"      "health": "up"
"job": "prometheus"   "health": "up"

# Labels Loki
{
    "status": "success",
    "data": [
        "cadvisor",
        "grafana",
        "loki",
        "mysql",
        "node-exporter",
        "openldap-test",
        "openvpn",
        "prometheus",
        "promtail"
    ]
}
```

---

## Estado al finalizar la fase

| Componente | Estado |
|-----------|--------|
| Prometheus | ✅ |
| Node Exporter | ✅ |
| cAdvisor | ✅ |
| Grafana | ✅ |
| Datasource Prometheus | ✅ |
| Datasource Loki | ✅ |
| Loki | ✅ |
| Promtail | ✅ |
| Dashboard sistema | ✅ |
| Dashboard contenedores | ✅ |
| Dashboard logs | ✅ |

---

## Variables del .env utilizadas en esta fase

| Variable | Valor |
|---------|-------|
| `GRAFANA_ADMIN_USER` | admin |
| `GRAFANA_ADMIN_PASSWORD` | configurado en .env |
| `GRAFANA_PORT` | 3000 |
| `GRAFANA_ALLOW_EMBEDDING` | true |
| `PROMETHEUS_PORT` | 9090 |
| `PROMETHEUS_RETENTION` | 15d |
| `LOKI_PORT` | 3100 |
| `LOKI_RETENTION` | 168h |

---

## Siguiente fase

**Fase 4 — Portal Web PHP + MySQL + Nginx**
- Portal de administracion con login y roles
- Grafana embebido en iframe
- Gestion de usuarios AD desde la web
- MFA via Telegram OTP
- Nginx como proxy inverso con HTTPS

---

*Fase 3 completada — TFG-OpenVPN · Said Rais · 2024/2025*
