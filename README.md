# 🔐 TFG-OpenVPN — Infraestructura VPN Empresarial con Raspberry Pi 5

> **Trabajo de Fin de Grado** · Ciclo Formativo de Grado Superior en Administración de Sistemas Informáticos en Red (ASIR)

---

## 📋 Descripción del Proyecto

Este TFG consiste en el diseño, implementación y documentación de una **infraestructura VPN empresarial completa** desplegada sobre una **Raspberry Pi 5**, utilizando exclusivamente **software libre y gratuito**.

El objetivo es simular un entorno real de teletrabajo corporativo, con gestión centralizada de usuarios a través de un **Active Directory externo (Samba AD/DC)**, monitorización avanzada, automatización mediante un bot de Telegram y un panel web de administración propio.

Todo el stack se orquesta con **Docker y Docker Compose**, garantizando modularidad, portabilidad y facilidad de mantenimiento.

---

## 🎯 Objetivos

- Implementar una solución VPN segura y funcional con **OpenVPN Access Server**
- Integrar autenticación de usuarios con **Active Directory / LDAP**
- Desarrollar un **panel web de administración** propio (PHP + MySQL)
- Monitorizar el sistema con **Prometheus + Grafana**
- Automatizar tareas administrativas mediante un **bot de Telegram** en Python
- Aplicar medidas de **seguridad** con iptables, HTTPS y segmentación de red
- Demostrar la **viabilidad energética** y económica de Raspberry Pi frente a servidores x86

---

## 🏗️ Arquitectura del Sistema

```
         🌍 Clientes Remotos
       (PC / Laptop / Móvil)
                 │
                 ▼
       ┌───────────────────────┐
       │ OpenVPN Access Server │  ← Puerto 1194/UDP + 443/TCP
       │      (Pi5, Docker)    │
       └─────────┬─────────────┘
                 │
       ┌─────────┴──────────────┐
       ▼                        ▼
  Panel Web               Prometheus + Grafana
(PHP + MySQL)              (Docker, Pi5)
  Puerto 80/443             Puerto 9090/3000
       │
       ▼
  Bot Telegram (Python)
  (Docker, Pi5)
       │
       ▼
┌─────────────────────┐
│   AD/DC Externo     │  ← Máquina separada
│  Samba / Active     │     Puerto 389/636 (LDAP/S)
│  Directory          │
└─────────────────────┘
```

**Principios de diseño:**
- AD/DC **fuera de la Pi** → simula entorno empresarial real
- Un contenedor por servicio → máxima modularidad
- Red interna Docker separada → segmentación y seguridad
- Todos los servicios con HTTPS → comunicaciones cifradas

---

## 🛠️ Stack Tecnológico

| Categoría | Tecnología | Versión | Notas |
|-----------|-----------|---------|-------|
| **SO Base** | Raspberry Pi OS Lite (64-bit) | Bookworm | Sin entorno gráfico |
| **Contenedores** | Docker + Docker Compose | Latest | Orquestación completa |
| **VPN** | OpenVPN Access Server | Community | Máx. 2 conexiones gratis |
| **Autenticación** | Samba AD/DC + LDAP | 4.x | Servidor externo |
| **Panel Web** | PHP + Apache + MySQL | PHP 8.x | Contenedor personalizado |
| **Monitorización** | Prometheus + Grafana | Latest | Métricas y dashboards |
| **Automatización** | Python + python-telegram-bot | 3.11 | Bot administrativo |
| **Certificados SSL** | Let's Encrypt / Autofirmado | — | HTTPS en todos los servicios |
| **Firewall** | iptables | — | Segmentación de red |

---

## 📁 Estructura del Repositorio

```
TFG-OpenVPN/
├── 📄 README.md
├── 📄 docker-compose.yml          # Orquestación de todos los servicios
├── 📄 .env.example                # Variables de entorno (plantilla)
│
├── 📂 openvpn/
│   ├── Dockerfile
│   ├── config/
│   │   ├── server.conf            # Configuración OpenVPN
│   │   └── ldap.conf              # Integración LDAP/AD
│   └── scripts/
│       └── init.sh
│
├── 📂 panel-web/
│   ├── Dockerfile
│   ├── src/
│   │   ├── index.php              # Dashboard principal
│   │   ├── vpn-status.php         # Estado de la VPN
│   │   ├── users.php              # Gestión de usuarios
│   │   ├── logs.php               # Visor de logs
│   │   └── grafana-embed.php      # Embebido de dashboards
│   ├── css/
│   └── js/
│
├── 📂 monitoring/
│   ├── prometheus/
│   │   └── prometheus.yml         # Configuración Prometheus
│   └── grafana/
│       ├── provisioning/
│       └── dashboards/
│           ├── system.json        # Métricas del sistema
│           ├── containers.json    # Métricas de contenedores
│           └── vpn.json           # Métricas de VPN
│
├── 📂 bot-telegram/
│   ├── Dockerfile
│   ├── requirements.txt
│   ├── bot.py                     # Bot principal
│   ├── commands/
│   │   ├── usuarios.py            # /crearusuario, /revocar
│   │   ├── estado.py              # /estado, /conexiones
│   │   └── backup.py              # /backup_now
│   └── alerts/
│       └── monitor.py             # Alertas automáticas
│
├── 📂 mysql/
│   ├── init.sql                   # Schema de la base de datos
│   └── backups/
│
├── 📂 nginx/
│   ├── nginx.conf                 # Proxy inverso + SSL
│   └── certs/
│
├── 📂 scripts/
│   ├── setup.sh                   # Script de instalación inicial
│   ├── backup.sh                  # Backup automático
│   └── firewall.sh                # Configuración iptables
│
└── 📂 docs/
    ├── memoria-tfg.pdf
    ├── diagramas/
    └── capturas/
```

---

## 🚀 Fases de Implementación

### Fase 0 — Preparación del entorno
- [ ] Instalar Raspberry Pi OS Lite 64-bit
- [ ] Actualizar el sistema (`apt update && apt upgrade`)
- [ ] Instalar Docker y Docker Compose
- [ ] Clonar este repositorio
- [ ] Configurar variables de entorno (`.env`)

### Fase 1 — Integración con AD externo
- [ ] Verificar conectividad de red Pi ↔ AD/DC
- [ ] Configurar DNS apuntando al AD/DC
- [ ] Probar consulta LDAP con `ldapsearch`
- [ ] Documentar estructura OU del directorio

### Fase 2 — VPN (OpenVPN Access Server)
- [ ] Levantar contenedor OpenVPN AS
- [ ] Configurar autenticación LDAP/AD
- [ ] Generar/instalar certificados SSL
- [ ] Probar conexión con OpenVPN Connect (cliente)
- [ ] Validar split tunneling y rutas

### Fase 3 — Panel Web
- [ ] Levantar contenedor PHP + MySQL
- [ ] Diseñar e implementar dashboard
- [ ] Integrar estado de OpenVPN (API/logs)
- [ ] Integrar iframe de Grafana

### Fase 4 — Monitorización
- [ ] Configurar Prometheus + Node Exporter
- [ ] Añadir cAdvisor para métricas de contenedores
- [ ] Crear dashboards en Grafana
- [ ] Alertas en Grafana para umbrales críticos

### Fase 5 — Bot de Telegram
- [ ] Crear bot en [@BotFather](https://t.me/BotFather)
- [ ] Implementar comandos administrativos
- [ ] Configurar alertas automáticas
- [ ] Probar informes diarios automáticos

### Fase 6 — Seguridad
- [ ] Configurar iptables (script `firewall.sh`)
- [ ] Habilitar HTTPS en panel web y OpenVPN
- [ ] Restringir acceso LDAP solo desde Pi
- [ ] Revisar puertos expuestos (`nmap` desde exterior)

### Fase 7 — Pruebas y documentación
- [ ] Conectar 2-3 clientes reales (teletrabajo simulado)
- [ ] Probar escenarios de fallo y recuperación
- [ ] Medir consumo eléctrico (vatímetro)
- [ ] Redactar memoria del TFG con capturas y diagramas

---

## ⚙️ Instalación Rápida

### Prerrequisitos

```bash
# Actualizar el sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Instalar Docker Compose
sudo apt install docker-compose-plugin -y

# Verificar instalación
docker --version
docker compose version
```

### Despliegue

```bash
# Clonar el repositorio
git clone https://github.com/TU_USUARIO/TFG-OpenVPN.git
cd TFG-OpenVPN

# Copiar y configurar variables de entorno
cp .env.example .env
nano .env  # Editar con tus valores

# Levantar todos los servicios
docker compose up -d

# Verificar estado
docker compose ps
```

### Variables de entorno (`.env`)

```env
# Active Directory
AD_HOST=192.168.1.10
AD_PORT=389
AD_BASE_DN=dc=empresa,dc=local
AD_BIND_USER=cn=vpnbind,ou=serviceaccounts,dc=empresa,dc=local
AD_BIND_PASS=TuPasswordSegura

# OpenVPN
OPENVPN_HOSTNAME=vpn.tudominio.com
OPENVPN_PORT=1194

# Panel Web
MYSQL_ROOT_PASSWORD=rootpass
MYSQL_DATABASE=panelvpn
MYSQL_USER=paneluser
MYSQL_PASSWORD=panelpass

# Bot Telegram
TELEGRAM_BOT_TOKEN=tu_token_de_botfather
TELEGRAM_ADMIN_CHAT_ID=tu_chat_id

# Grafana
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=grafanapass
```

---

## 🤖 Comandos del Bot de Telegram

| Comando | Descripción |
|---------|-------------|
| `/estado` | Estado general del servidor y servicios |
| `/conexiones` | Lista de clientes VPN activos |
| `/crearusuario <usuario>` | Crear nuevo usuario en AD y VPN |
| `/revocar <usuario>` | Revocar acceso VPN a un usuario |
| `/backup_now` | Ejecutar backup manual inmediato |
| `/logs [servicio]` | Ver últimas líneas de logs |
| `/metricas` | Resumen de CPU, RAM y disco |
| `/ayuda` | Mostrar todos los comandos disponibles |

---

## 📊 Métricas Monitorizadas

- **Sistema:** CPU, RAM, temperatura, disco, uptime
- **Red:** tráfico entrante/saliente, latencia
- **Docker:** estado de contenedores, uso de recursos por servicio
- **VPN:** conexiones activas, usuarios conectados, bytes transferidos
- **AD/LDAP:** intentos de autenticación, errores, latencia de respuesta

---

## 🔒 Seguridad

- **Autenticación:** Centralizada vía Active Directory (LDAP/S)
- **Cifrado VPN:** TLS 1.2+ con certificados x509
- **HTTPS:** Panel web y API con certificado válido (Let's Encrypt)
- **Firewall:** iptables con política DROP por defecto, solo puertos necesarios
- **Segmentación:** Red Docker interna aislada, AD solo accesible desde servicios autorizados
- **Secrets:** Variables sensibles en `.env` (nunca en el repositorio)

---

## ⚡ Eficiencia Energética

| Dispositivo | Consumo aprox. | Coste anual* |
|-------------|----------------|-------------|
| Raspberry Pi 5 | ~8-12W | ~8-10 € |
| Servidor x86 mini | ~30-60W | ~30-60 € |
| Servidor rack 1U | ~150-300W | ~130-260 € |

*Estimación a 0,12 €/kWh funcionando 24/7

> La Raspberry Pi 5 supone un **ahorro del 80-95%** en consumo eléctrico frente a soluciones x86 equivalentes, manteniendo un rendimiento suficiente para un entorno empresarial de pequeña y mediana empresa.

---

## 📚 Documentación Adicional

- 📖 [Memoria del TFG](docs/memoria-tfg.pdf)
- 🗺️ [Diagrama de red detallado](docs/diagramas/)
- 📸 [Capturas del proyecto](docs/capturas/)
- 🔧 [Guía de configuración avanzada](docs/configuracion-avanzada.md)

---

## 🧰 Tecnologías y Herramientas de Referencia

- [OpenVPN Access Server](https://openvpn.net/access-server/) — Servidor VPN
- [Samba AD/DC](https://wiki.samba.org/index.php/Setting_up_Samba_as_an_Active_Directory_Domain_Controller) — Active Directory libre
- [Grafana](https://grafana.com/) — Dashboards de monitorización
- [Prometheus](https://prometheus.io/) — Recolección de métricas
- [python-telegram-bot](https://python-telegram-bot.org/) — Librería para el bot
- [Docker Docs](https://docs.docker.com/) — Documentación de Docker
- [Let's Encrypt](https://letsencrypt.org/) — Certificados SSL gratuitos

---

## 👤 Autor

**Nombre:** Tu Nombre Aquí  
**Ciclo:** CFGS — Administración de Sistemas Informáticos en Red (ASIR) · 2º Curso  
**Centro:** Nombre de tu Centro Educativo  
**Tutor/a:** Nombre del Tutor/a  
**Año:** 2024/2025  

---

## 📄 Licencia

Este proyecto está bajo la licencia **MIT** — consulta el archivo [LICENSE](LICENSE) para más detalles.

Todos los componentes utilizados son software libre con sus respectivas licencias (GPL, Apache 2.0, MIT).

---

<div align="center">

**⭐ Si este proyecto te ha sido útil, ¡dale una estrella!**

*Desarrollado con ❤️ sobre una Raspberry Pi 5*

</div>
