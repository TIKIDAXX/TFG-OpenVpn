# Fase 0 — Preparacion del Entorno

> TFG-OpenVPN · Said Rais · CFGS ASIR 2º · IES Isabel de Villena · 2024/2025

---

## Objetivo

Tener la Raspberry Pi 5 lista con Docker, acceso remoto seguro y el repositorio clonado.
Esta fase no levanta ningun contenedor, solo prepara la base sobre la que construiremos todo.

---

## Infraestructura de esta fase

```
PC Windows          Raspberry Pi 5
(192.168.1.x)  →   (192.168.1.140)
SSH con clave       tfg-openvpn-pi
publica             Docker + Firewall
```

---

## Checklist

- [x] Raspberry Pi OS Lite 64-bit instalado
- [x] Sistema actualizado
- [x] Docker + Docker Compose instalados
- [x] Clave SSH copiada a la Pi
- [x] Login por contrasena SSH deshabilitado
- [x] IP Forwarding habilitado
- [x] Repositorio clonado en la Pi
- [x] Archivo .env configurado
- [x] Firewall basico aplicado

---

## Pasos de implementacion

### PASO 1 — Clonar el repositorio en la Pi

Conectarse a la Pi por SSH con contrasena (aun funciona en este punto):

```bash
ssh alumno@192.168.1.140
```

Clonar el repositorio:

```bash
git clone https://github.com/tu-usuario/TFG-OpenVPN.git /opt/tfg-openvpn
cd /opt/tfg-openvpn
```

---

### PASO 2 — Convertir saltos de linea (CRLF → LF)

Los archivos editados en Windows tienen saltos de linea CRLF que Linux no entiende.
Antes de ejecutar cualquier script hay que convertirlos:

```bash
sudo apt-get install -y dos2unix
dos2unix scripts/setup.sh
dos2unix scripts/firewall.sh
```

O alternativamente sin instalar nada:

```bash
sed -i 's/\r//' scripts/setup.sh
sed -i 's/\r//' scripts/firewall.sh
```

---

### PASO 3 — Ejecutar el script de setup

```bash
chmod +x scripts/setup.sh
sudo bash scripts/setup.sh
```

El script hace automaticamente:
- Actualiza el sistema
- Instala Docker + Docker Compose
- Configura SSH para solo clave publica
- Configura hostname: tfg-openvpn-pi
- Habilita IP Forwarding

---

### PASO 4 — Arreglar /etc/hosts tras cambio de hostname

El script cambia el hostname a tfg-openvpn-pi pero no actualiza /etc/hosts.
Hay que hacerlo manualmente o sudo no funciona correctamente:

```bash
sudo nano /etc/hosts
```

Busca la linea:
```
127.0.1.1    OpenVpn-1
```

Cambiala a:
```
127.0.1.1    tfg-openvpn-pi
```

Guarda con Ctrl+X → Y → Enter

---

### PASO 5 — Copiar clave SSH desde Windows

En Windows no existe el comando ssh-copy-id. Hay que hacerlo manualmente.

**En la Pi — reactivar login por contrasena temporalmente:**
```bash
sudo nano /etc/ssh/sshd_config
# Cambiar: PasswordAuthentication no → PasswordAuthentication yes
sudo systemctl restart ssh
```

**En tu PC Windows — generar clave SSH:**
```powershell
ssh-keygen -t ed25519 -C "tfg-openvpn"
# Pulsar Enter en todo — no escribir nombre de archivo
# Pulsar Enter dos veces para dejar passphrase vacia
```

**En tu PC Windows — copiar clave a la Pi:**
```powershell
type C:\Users\hapme\.ssh\id_ed25519.pub | ssh alumno@192.168.1.140 "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"
```

**Verificar que funciona sin contrasena:**
```powershell
ssh alumno@192.168.1.140
# Debe entrar directamente sin pedir contrasena
```

**En la Pi — volver a deshabilitar login por contrasena:**
```bash
sudo nano /etc/ssh/sshd_config
# Cambiar: PasswordAuthentication yes → PasswordAuthentication no
sudo systemctl restart ssh
```

---

### PASO 6 — Aplicar firewall

```bash
chmod +x scripts/firewall.sh
sudo bash scripts/firewall.sh
```

---

### PASO 7 — Configurar el .env

```bash
cd /opt/tfg-openvpn
cp .env.example .env
nano .env
```

Rellena con tus valores reales. Variables que puedes rellenar ahora:
```env
AD_HOST=10.0.0.10
AD_PORT=389
AD_BASE_DN=dc=domainsaid,dc=internal
MYSQL_ROOT_PASSWORD=<password seguro>
MYSQL_DATABASE=panelvpn
MYSQL_USER=paneluser
MYSQL_PASSWORD=<password seguro>
GRAFANA_ADMIN_PASSWORD=<password seguro>
```

Variables pendientes para fases posteriores — ver tabla al final.

---

### PASO 8 — Verificacion final

```bash
sudo bash scripts/verificacion.sh
```

O manualmente:

```bash
# Docker funcionando
docker ps

# Docker Compose disponible
docker compose version

# IP Forwarding activo (debe devolver 1)
cat /proc/sys/net/ipv4/ip_forward

# Firewall activo
sudo iptables -L -n

# SSH solo con clave publica
sudo grep "PasswordAuthentication" /etc/ssh/sshd_config

# Hostname correcto
hostname

# Repositorio clonado
ls /opt/tfg-openvpn
```

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| `$'\r': command not found` | Archivos editados en Windows tienen saltos de linea CRLF | `sed -i 's/\r//' scripts/setup.sh` |
| `Unable to locate package software-properties-common` | El paquete no existe en Raspberry Pi OS Bookworm | Eliminar esa linea del setup.sh — no es necesaria |
| `sudo: unable to resolve host tfg-openvpn-pi` | El script cambia el hostname pero no actualiza /etc/hosts | Editar /etc/hosts y cambiar el hostname antiguo por tfg-openvpn-pi |
| `ssh-copy-id no se reconoce` | El comando no existe en Windows | Usar `type` de PowerShell para copiar la clave manualmente |
| `Permission denied (publickey)` | El setup.sh deshabilito el login por contrasena antes de copiar la clave | Reactivar temporalmente PasswordAuthentication en sshd_config |
| `authorized_keys: Permission denied` | La carpeta .ssh fue creada como root al usar sudo mkdir | `sudo chown -R alumno:alumno ~/.ssh` para devolver el propietario correcto |

---

## Resultado de la verificacion

```
CONTAINER ID   IMAGE   COMMAND   CREATED   STATUS   PORTS   NAMES
Docker Compose version v5.1.0
1
Chain INPUT (policy DROP)
  ACCEPT  tcp  dpt:22
  ACCEPT  udp  dpt:1194
  ACCEPT  tcp  dpt:443
  ACCEPT  tcp  dpt:80
Chain FORWARD (policy DROP)
Chain OUTPUT (policy ACCEPT)
PasswordAuthentication no
tfg-openvpn-pi
```

---

## Estado al finalizar la fase

| Componente | Estado |
|-----------|--------|
| Raspberry Pi OS Lite 64-bit | ✅ |
| Sistema actualizado | ✅ |
| Docker + Docker Compose v5.1.0 | ✅ |
| SSH con clave publica | ✅ |
| Login por contrasena deshabilitado | ✅ |
| IP Forwarding habilitado | ✅ |
| Hostname: tfg-openvpn-pi | ✅ |
| Repositorio clonado en /opt/tfg-openvpn | ✅ |
| Firewall basico aplicado | ✅ |

---

## Variables del .env pendientes para fases posteriores

| Variable | Pendiente para |
|---------|---------------|
| `TELEGRAM_BOT_TOKEN` | Fase 5 |
| `TELEGRAM_CHAT_MAIN` | Fase 5 |
| `TELEGRAM_CHAT_MFA` | Fase 5 |
| `TELEGRAM_CHAT_SOPORTE` | Fase 5 |
| `TELEGRAM_ADMIN_IDS` | Fase 5 |
| `TELEGRAM_SOPORTE_IDS` | Fase 5 |
| `OPENVPN_HOSTNAME` | Fase 2 |
| `PORTAL_SESSION_SECRET` | Fase 4 |
| `AD_VPN_GROUP` | Fase 1 |
| `AD_ADMIN_GROUP` | Fase 1 |

---

## Archivos de esta fase

```
scripts/
├── setup.sh      — Instalacion automatica de dependencias
└── firewall.sh   — Reglas iptables basicas
readme-fase0.md   — Este archivo
```

---

## Siguiente fase

**Fase 1 — Active Directory externo**
- Crear grupos vpnusers y vpnadmins en el AD
- Verificar conectividad LDAP desde la Pi con ldapsearch
- Actualizar .env con datos reales del AD

---

*Fase 0 completada — TFG-OpenVPN · Said Rais · 2024/2025*
