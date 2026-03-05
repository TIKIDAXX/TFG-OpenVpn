# 🔐 TFG-OpenVPN — Infraestructura VPN Empresarial con Raspberry Pi 5

> **Trabajo de Fin de Grado** · Ciclo Formativo de Grado Superior en Administración de Sistemas Informáticos en Red (ASIR) · 2º Curso

---

## 📋 Descripción del Proyecto

Este TFG consiste en el diseño, implementación y documentación de una **infraestructura VPN empresarial completa** desplegada sobre una **Raspberry Pi 5**, utilizando exclusivamente **software libre y gratuito**.

El sistema simula un entorno real de teletrabajo corporativo con:
- Autenticación centralizada vía **Active Directory externo (Samba AD/DC)** con control de acceso a recursos de red por grupos
- **Portal web de administración** propio con Grafana embebido, gestión de usuarios y logs
- **Bot de Telegram** como agente autónomo de seguridad y alertas críticas, con MFA de doble chat para comandos administrativos
- Monitorización avanzada con **Prometheus + Grafana**
- Todo orquestado con **Docker y Docker Compose**

> ⚠️ Se eligió **OpenVPN** frente a WireGuard por su integración nativa con LDAP/Active Directory, permitiendo herencia de políticas de grupos AD: control de acceso a carpetas compartidas, recursos de red y GPOs. WireGuard no ofrece esta integración de forma nativa.

---

## 🎯 Objetivos

- Implementar una solución VPN segura con **OpenVPN Community Edition** integrada con AD/LDAP
- Centralizar autenticación y control de acceso mediante **Active Directory externo**
- Desarrollar un **portal web de administración** (PHP + MySQL) con Grafana embebido
- Monitorizar el sistema con **Prometheus + Grafana**
- Desplegar un **bot de Telegram** como agente autónomo de alertas críticas con comandos protegidos por **MFA de doble chat**
- Aplicar **seguridad en capas**: iptables, Fail2Ban, HTTPS y segmentación de red
- Demostrar la **viabilidad energética** de Raspberry Pi frente a servidores x86

---

## 🏗️ Arquitectura del Sistema

```
         🌍 Clientes Remotos
       (PC / Laptop / Móvil)
                 │
                 ▼
       ┌───────────────────────┐
       │     OpenVPN CE        │  ← Puerto 1194/UDP + 443/TCP
       │      (Pi5, Docker)    │
       └─────────┬─────────────┘
                 │  Autenticación LDAP
                 ▼
       ┌─────────────────────┐
       │    AD/DC Externo    │  ← Máquina separada
       │    Samba AD/DC      │     Grupos, recursos, GPOs
       │    Puerto 389/636   │
       └─────────────────────┘
                 │
       ┌─────────┴──────────────────┐
       ▼                            ▼
  Portal Web Admin            Prometheus + Grafana
  (PHP + MySQL)                (Docker, Pi5)
  - Grafana embebido            Puerto 9090 / 3000
  - Crear/revocar usuarios
  - Logs centralizados
  - Estado servicios
       │
       ▼
  Bot Telegram (Python)
  ┌─────────────────────────────────┐
  │  CHAT 1 — MFA / Códigos OTP    │ ← Canal privado admin
  │  CHAT 2 — Alertas + Comandos   │ ← Canal operacional
  └─────────────────────────────────┘
       │
       ▼
  Fail2Ban (Docker)
  - Bloqueo automático de IPs
  - Notificación al bot
```

---

## 🔐 Flujo MFA del Bot de Telegram

```
Admin escribe /revocar usuario123  →  Chat 2 (principal)
                    │
                    ▼
     Bot genera código OTP (6 dígitos, expira en 90s)
                    │
                    ▼
     Bot envía código  →  Chat 1 (MFA privado)
                    │
                    ▼
     Admin responde con el código  →  Chat 2
                    │
              ┌─────┴──────┐
           válido        inválido / expirado
              │                │
              ▼                ▼
        Ejecuta acción    Cancela + registra
        Confirma en       en logs de seguridad
        Chat 2
```

---

## 🛠️ Stack Tecnológico

| Categoría | Tecnología | Notas |
|-----------|-----------|-------|
| **SO Base** | Raspberry Pi OS Lite 64-bit (Bookworm) | Sin entorno gráfico |
| **Contenedores** | Docker + Docker Compose | Orquestación completa |
| **VPN** | OpenVPN Community Edition | Sin límite de conexiones |
| **Autenticación** | Samba AD/DC + LDAP/LDAPS | Servidor externo, grupos y recursos |
| **Portal Web** | PHP 8 + Apache + MySQL | Administración centralizada |
| **Dashboards** | Grafana embebido en portal | Integrado, no duplicado |
| **Métricas** | Prometheus + Node Exporter + cAdvisor | Sistema y contenedores |
| **Bot** | Python + python-telegram-bot | Agente autónomo + MFA doble chat |
| **Seguridad** | Fail2Ban + iptables + HTTPS | Capas de protección |
| **Certificados** | Let's Encrypt / Autofirmado | HTTPS en todos los servicios |
| **Acceso Remoto** | Tailscale + ACLs + clave SSH | Mantenimiento remoto seguro |

---

## 📁 Estructura del Repositorio

```
TFG-OpenVPN/
├── README.md
├── docker-compose.yml
├── .env.example
├── openvpn/
│   ├── Dockerfile
│   ├── config/
│   │   ├── server.conf
│   │   └── ldap.conf
│   └── scripts/
│       └── init.sh
├── portal-web/
│   ├── Dockerfile
│   └── src/
│       ├── index.php
│       ├── vpn-status.php
│       ├── users.php
│       ├── logs.php
│       └── grafana-embed.php
├── monitoring/
│   ├── prometheus/
│   │   └── prometheus.yml
│   └── grafana/
│       ├── provisioning/
│       └── dashboards/
│           ├── system.json
│           ├── containers.json
│           └── vpn.json
├── bot-telegram/
│   ├── Dockerfile
│   ├── requirements.txt
│   ├── bot.py
│   ├── config.py
│   ├── mfa/
│   │   └── otp.py
│   ├── alerts/
│   │   ├── services.py
│   │   ├── vpn.py
│   │   └── ldap.py
│   └── commands/
│       ├── estado.py
│       ├── usuarios.py
│       └── backup.py
├── fail2ban/
│   ├── Dockerfile
│   └── jail.local
├── mysql/
│   └── init.sql
├── nginx/
│   ├── nginx.conf
│   └── certs/
├── scripts/
│   ├── setup.sh
│   ├── backup.sh
│   └── firewall.sh
└── docs/
    ├── diagramas/
    └── capturas/
```

---

## 🚀 Fases de Implementación

> 💡 Regla de oro: cada fase debe funcionar completamente antes de pasar a la siguiente.

---

### FASE 0 — Preparación del entorno
**Objetivo:** Raspberry Pi lista con Docker funcionando.

- [ ] Instalar Raspberry Pi OS Lite 64-bit
- [ ] Actualizar el sistema
- [ ] Instalar Docker + Docker Compose
- [ ] Configurar acceso SSH
- [ ] Clonar repositorio y crear estructura de carpetas
- [ ] Configurar archivo `.env`

**Entregable:** `docker compose ps` funciona sin errores.

---

### FASE 1 — Active Directory externo
**Objetivo:** Verificar conectividad y autenticación LDAP desde la Pi.

- [ ] Configurar red y DNS apuntando al AD/DC externo
- [ ] Probar consulta LDAP con `ldapsearch`
- [ ] Documentar OUs, grupos y usuarios de prueba
- [ ] Verificar control de acceso por grupos AD

**Entregable:** `ldapsearch` devuelve usuarios del AD correctamente.

---

### FASE 2 — VPN con OpenVPN CE + LDAP
**Objetivo:** VPN funcional autenticando contra el AD externo.

- [ ] Contenedor OpenVPN Community Edition
- [ ] Configuración server.conf y ldap.conf
- [ ] Plugin openvpn-auth-ldap
- [ ] Certificados SSL
- [ ] Prueba de conexión con OpenVPN Connect
- [ ] Verificar herencia de permisos AD

**Entregable:** Cliente externo se conecta con credenciales del AD.

---

### FASE 3 — Monitorización (Prometheus + Grafana)
**Objetivo:** Métricas del sistema visibles en Grafana.

- [ ] Contenedor Prometheus
- [ ] Node Exporter (métricas Pi)
- [ ] cAdvisor (métricas contenedores)
- [ ] Grafana con datasource Prometheus
- [ ] Dashboards: sistema, contenedores, VPN
- [ ] Alertas para umbrales críticos

**Entregable:** Grafana muestra métricas en tiempo real.

---

### FASE 4 — Portal Web de Administración
**Objetivo:** Portal unificado con Grafana embebido y gestión de usuarios.

- [ ] Contenedor PHP + Apache + MySQL
- [ ] Nginx proxy inverso con HTTPS
- [ ] Login administrador con sesión segura
- [ ] Dashboard con estado de servicios
- [ ] Grafana embebido (iframe)
- [ ] Crear/revocar usuarios AD desde web
- [ ] Visor de logs
- [ ] Estado de conexiones VPN

**Entregable:** Portal accesible en https://pi con todas las secciones.

---

### FASE 5 — Bot de Telegram (Alertas + MFA + Comandos)
**Objetivo:** Agente autónomo de seguridad con MFA doble chat.

**5.1 — Infraestructura:**
- [ ] Bot en @BotFather + token
- [ ] Chat 1 (MFA privado) y Chat 2 (principal)
- [ ] Contenedor Python

**5.2 — Alertas autónomas:**
- [ ] Contenedor caído → Chat 2
- [ ] IP desconocida en VPN → Chat 2
- [ ] Errores LDAP repetidos → Chat 2
- [ ] Umbrales CPU/RAM/disco → Chat 2

**5.3 — Sistema MFA:**
- [ ] OTP 6 dígitos con expiración 90s
- [ ] Envío automático a Chat 1
- [ ] Validación y registro de intentos fallidos

**5.4 — Comandos:**
- [ ] /estado — Estado de servicios
- [ ] /conexiones — Clientes VPN activos
- [ ] /crearusuario (requiere MFA)
- [ ] /revocar (requiere MFA)
- [ ] /backup_now (requiere MFA)

**Entregable:** Bot alerta automáticamente y ejecuta comandos solo tras MFA.

---

### FASE 6 — Seguridad
**Objetivo:** Hardening completo del sistema.

- [ ] Fail2Ban en Docker + notificación al bot
- [ ] iptables con política DROP por defecto
- [ ] Acceso LDAP solo desde contenedores autorizados
- [ ] HTTPS en portal y OpenVPN
- [ ] Verificar con nmap desde exterior

**Entregable:** Solo puertos 443 y 1194 visibles. Fail2Ban activo.

---

### FASE 7 — Pruebas y documentación
**Objetivo:** Validar sistema completo y preparar memoria del TFG.

- [ ] Conectar 2-3 clientes simulando teletrabajo
- [ ] Simular caída de contenedor → verificar alerta
- [ ] Simular IP desconocida → verificar bloqueo
- [ ] Probar flujo MFA completo
- [ ] Medir consumo eléctrico
- [ ] Diagrama de flujo de autenticación
- [ ] Capturas de pantalla
- [ ] Redactar memoria

**Entregable:** Sistema documentado y listo para defensa.

---
## 🛠️ Acceso Remoto de Mantenimiento (Tailscale)

En caso de incidencia, el administrador puede conectarse remotamente a la Raspberry Pi
de forma segura mediante **Tailscale**, sin necesidad de abrir puertos en el router del cliente.
```
[Administrador]
  │  clave privada SSH + 2FA Tailscale
  ▼
[Tailscale ACL]  ←── solo dispositivo autorizado
  ▼
[Raspberry Pi]
  │  SSH solo por clave pública
  ▼
[Acceso concedido ✅]
```

### 🔐 Capas de seguridad

| Capa | Mecanismo | Protege contra |
|------|-----------|----------------|
| **1** | ACLs Tailscale estrictas | Acceso desde otros dispositivos de la red |
| **2** | SSH solo con clave pública | Acceso por contraseña comprometida |
| **3** | 2FA en cuenta Tailscale | Robo de credenciales Tailscale |
| **4** | Logs de auditoría Tailscale SSH | Trazabilidad de cada sesión |

> 🔒 Aunque alguien comprometa la cuenta Tailscale, sin la clave privada SSH no puede acceder a ninguna Raspberry Pi.

---

## 📋 Líneas Futuras

### Corto plazo
- Backups automáticos en la nube (Google Drive / S3)
- Renovación automática de certificados SSL con Certbot
- Actualizaciones automáticas de seguridad con `unattended-upgrades`

### Medio plazo
- 2FA propio en el portal web de administración
- Alertas por email como canal secundario (respaldo del bot)
- Dashboard de auditoría de accesos AD en Grafana

### Largo plazo
- Migración a clúster **K3s** con múltiples nodos Raspberry Pi
- VPN **Site-to-Site** entre sedes físicas
- Implementación de modelo **Zero Trust (ZTNA)**
- IDS/IPS con **Suricata** para análisis de tráfico VPN
- SIEM básico con **Wazuh** para correlación de eventos

## ⚡ Eficiencia Energética

| Dispositivo | Consumo aprox. | Coste anual* |
|-------------|----------------|-------------|
| Raspberry Pi 5 | ~8-12W | ~8-10 € |
| Servidor x86 mini | ~30-60W | ~30-60 € |
| Servidor rack 1U | ~150-300W | ~130-260 € |

*Estimación a 0,12 €/kWh funcionando 24/7

---

## 👤 Autor

**Nombre:** Said Rais
**Ciclo:** CFGS — ASIR · 2º Curso
**Centro:** Nombre del Centro
**Tutor/a:** Nombre del Tutor/a
**Año:** 2024/2025

---

## 📄 Licencia

MIT — Todos los componentes son software libre.

---

*Desarrollado con ❤️ sobre una Raspberry Pi 5*
