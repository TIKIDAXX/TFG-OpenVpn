# Fase 0 — Preparacion del Entorno

> TFG-OpenVPN · Said Rais · CFGS ASIR 2º · IES Isabel de Villena · 2024/2025

---

## Objetivo

Tener la Raspberry Pi 5 lista con Docker, acceso remoto seguro y el repositorio clonado.
Esta fase no levanta ningun contenedor, solo prepara la base sobre la que construiremos todo.

---

## Checklist

- [ ] Raspberry Pi OS Lite 64-bit instalado
- [ ] Sistema actualizado
- [ ] Docker + Docker Compose instalados
- [ ] Clave SSH copiada a la Pi
- [ ] Login por contrasena SSH deshabilitado
- [ ] Tailscale instalado y vinculado a tu cuenta
- [ ] 2FA activado en Tailscale
- [ ] IP Forwarding habilitado
- [ ] Repositorio clonado en la Pi
- [ ] Archivo .env configurado
- [ ] Firewall basico aplicado

---

## Pasos

### PASO 1 — Clonar el repositorio en la Pi

Conectate a la Pi por SSH con tu contrasena (aun funciona):

```bash
ssh pi@<IP_DE_TU_PI>
```

Clona el repositorio:

```bash
git clone https://github.com/tu-usuario/TFG-OpenVPN.git /opt/tfg-openvpn
cd /opt/tfg-openvpn
```

---

### PASO 2 — Ejecutar el script de setup

```bash
chmod +x scripts/setup.sh
sudo bash scripts/setup.sh
```

El script hace automaticamente:
- Actualiza el sistema
- Instala Docker + Docker Compose
- Instala Tailscale
- Configura SSH para solo clave publica
- Habilita IP Forwarding

Al terminar te muestra un resumen de verificacion.

---

### PASO 3 — Copiar tu clave SSH a la Pi

**Esto lo haces en tu PC, no en la Pi.**

Si usas Windows abre PowerShell. Si usas Mac/Linux abre Terminal.

```bash
# Generar clave SSH (solo si no tienes una ya)
ssh-keygen -t ed25519 -C "tfg-openvpn"
# Pulsa Enter en todo, deja los valores por defecto

# Copiar la clave publica a la Pi
ssh-copy-id pi@<IP_DE_TU_PI>
# Te pedira la contrasena de la Pi por ULTIMA vez

# Verificar que funciona sin contrasena
ssh pi@<IP_DE_TU_PI>
# Debe entrar directamente sin pedir contrasena
```

> IMPORTANTE: Verifica que entras sin contrasena ANTES de continuar.
> El setup.sh ya desactivo el login por contrasena.
> Si no copiaste la clave antes, tendras que acceder fisicamente a la Pi.

---

### PASO 4 — Conectar Tailscale

En la Pi ejecuta:

```bash
sudo tailscale up --ssh
```

La Pi te devuelve una URL como esta:
```
https://login.tailscale.com/a/xxxxxxxx
```

1. Copia esa URL y abela en tu navegador
2. Inicia sesion con tu cuenta de Tailscale
3. Autoriza el dispositivo
4. La Pi queda vinculada a tu cuenta

Verificar que esta conectado:
```bash
tailscale status
# Debe mostrar la Pi con una IP tipo 100.x.x.x
```

Desde ese momento puedes conectarte a la Pi desde cualquier sitio:
```bash
ssh pi@100.x.x.x  # La IP que te asigna Tailscale
```

---

### PASO 5 — Activar 2FA en Tailscale

1. Ve a https://login.tailscale.com
2. Settings → Two-factor authentication
3. Activa con tu app de autenticacion (Google Authenticator, Authy, etc.)

---

### PASO 6 — Configurar el .env

En la Pi:
```bash
cd /opt/tfg-openvpn
cp .env.example .env
nano .env
```

Rellena con tus valores reales:
```
AD_HOST=<IP de tu maquina con Active Directory>
AD_PORT=389
AD_BASE_DN=dc=empresa,dc=local
AD_BIND_USER=cn=vpnbind,ou=serviceaccounts,dc=empresa,dc=local
AD_BIND_PASS=<tu password>

OPENVPN_HOSTNAME=<IP o dominio de la Pi>
OPENVPN_PORT=1194

MYSQL_ROOT_PASSWORD=<password seguro>
MYSQL_DATABASE=panelvpn
MYSQL_USER=paneluser
MYSQL_PASSWORD=<password seguro>

TELEGRAM_BOT_TOKEN=<token de BotFather>
TELEGRAM_CHAT_MAIN=<ID del chat principal>
TELEGRAM_CHAT_MFA=<ID del chat MFA>

GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=<password seguro>
```

Guarda con Ctrl+X → Y → Enter

---

### PASO 7 — Aplicar firewall basico

```bash
chmod +x scripts/firewall.sh
sudo bash scripts/firewall.sh
```

---

### PASO 8 — Verificacion final

```bash
# Docker funcionando
docker ps

# Docker Compose disponible
docker compose version

# Tailscale conectado
tailscale status

# IP Forwarding activo (debe devolver 1)
cat /proc/sys/net/ipv4/ip_forward

# Firewall activo
sudo iptables -L -n
```

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| - | - | - |

> Esta tabla se rellena durante la implementacion real

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
- Verificar conectividad LDAP desde la Pi con ldapsearch
- Documentar OUs, grupos y usuarios de prueba

---

*Fase 0 — TFG-OpenVPN · Said Rais · 2024/2025*
