# Fase 1 — Active Directory externo

> TFG-OpenVPN · Said Rais · CFGS ASIR 2º · IES Isabel de Villena · 2024/2025

---

## Objetivo

Verificar que la Raspberry Pi puede comunicarse con el Active Directory externo
y autenticar usuarios via LDAP. Sin esto, la VPN no puede funcionar.

---

## Infraestructura de esta fase

```
Raspberry Pi 5          Maquina AD externa
(10.x.x.x)      →      (10.0.0.10)
                        Samba AD/DC
                        domainsaid.internal
```

---

## Checklist

- [ ] Grupos creados en el AD (vpnusers, vpnadmins)
- [ ] OU de grupos creada en el AD
- [ ] OU de cuentas de servicio creada en el AD
- [ ] Usuario de servicio vpnbind creado
- [ ] Usuarios de prueba añadidos a vpnusers
- [ ] Ping desde Pi al AD funcionando
- [ ] Puerto 389 accesible desde la Pi
- [ ] ldapsearch devuelve usuarios correctamente
- [ ] ldapsearch verifica grupo vpnusers con miembros
- [ ] ldapsearch verifica grupo vpnadmins con miembros
- [ ] .env actualizado con datos reales del AD

---

## Estructura del AD

```
dc=domainsaid,dc=internal
├── OU=Groups
│   ├── cn=vpnusers       ← Usuarios con acceso a VPN
│   └── cn=vpnadmins      ← Administradores de la VPN
└── OU=ServiceAccounts
    └── cn=vpnbind        ← Usuario de servicio para LDAP bind
```

---

## Pasos de implementacion

### PASO 1 — Crear estructura en el AD

Ejecutar en la maquina con Samba AD/DC:

```bash
# Crear OU para grupos
samba-tool ou create "OU=Groups,DC=domainsaid,DC=internal"

# Crear OU para cuentas de servicio
samba-tool ou create "OU=ServiceAccounts,DC=domainsaid,DC=internal"

# Crear grupo de usuarios VPN
samba-tool group add vpnusers \
    --groupou="OU=Groups,DC=domainsaid,DC=internal" \
    --description="Usuarios con acceso a VPN"

# Crear grupo de administradores VPN
samba-tool group add vpnadmins \
    --groupou="OU=Groups,DC=domainsaid,DC=internal" \
    --description="Administradores de la VPN"

# Crear usuario de servicio para LDAP bind
samba-tool user create vpnbind Cambiame123 \
    --userou="OU=ServiceAccounts,DC=domainsaid,DC=internal" \
    --description="Usuario de servicio para autenticacion LDAP"

# Añadir usuarios de prueba al grupo vpnusers
samba-tool group addmembers vpnusers <nombre_usuario>

# Añadir administrador al grupo vpnadmins
samba-tool group addmembers vpnadmins <nombre_usuario>
```

---

### PASO 2 — Verificar conectividad desde la Pi

Ejecutar en la Raspberry Pi:

```bash
# Verificar que la Pi llega al AD
ping -c 4 10.0.0.10

# Verificar puerto LDAP abierto
nc -zv 10.0.0.10 389
```

---

### PASO 3 — Verificar LDAP con ldapsearch

```bash
# Buscar todos los usuarios del dominio
ldapsearch -x \
    -H ldap://10.0.0.10 \
    -D "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(objectClass=user)" \
    cn sAMAccountName memberOf

# Verificar grupo vpnusers y sus miembros
ldapsearch -x \
    -H ldap://10.0.0.10 \
    -D "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(cn=vpnusers)" \
    member

# Verificar grupo vpnadmins y sus miembros
ldapsearch -x \
    -H ldap://10.0.0.10 \
    -D "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(cn=vpnadmins)" \
    member
```

Resultado esperado — debe devolver entradas como:
```
dn: CN=usuario1,OU=Users,DC=domainsaid,DC=internal
cn: usuario1
sAMAccountName: usuario1
```

---

### PASO 4 — Actualizar el .env

```bash
nano /opt/tfg-openvpn/.env
```

```env
AD_HOST=10.0.0.10
AD_PORT=389
AD_BASE_DN=dc=domainsaid,dc=internal
AD_BIND_USER=cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal
AD_BIND_PASS=Cambiame123
AD_VPN_GROUP=cn=vpnusers,ou=Groups,dc=domainsaid,dc=internal
AD_ADMIN_GROUP=cn=vpnadmins,ou=Groups,dc=domainsaid,dc=internal
```

---

## Verificacion final

| Prueba | Comando | Resultado esperado |
|--------|---------|-------------------|
| Conectividad | `ping -c 4 10.0.0.10` | 0% perdida |
| Puerto LDAP | `nc -zv 10.0.0.10 389` | succeeded |
| Usuarios AD | `ldapsearch (objectClass=user)` | Lista de usuarios |
| Grupo vpnusers | `ldapsearch (cn=vpnusers)` | Muestra miembros |
| Grupo vpnadmins | `ldapsearch (cn=vpnadmins)` | Muestra miembros |

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| - | - | - |

> Esta tabla se rellena durante la implementacion real

---

## Variables del .env actualizadas en esta fase

| Variable | Valor |
|---------|-------|
| `AD_HOST` | 10.0.0.10 |
| `AD_PORT` | 389 |
| `AD_BASE_DN` | dc=domainsaid,dc=internal |
| `AD_BIND_USER` | cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal |
| `AD_BIND_PASS` | ⚠️ Cambiar en produccion |
| `AD_VPN_GROUP` | cn=vpnusers,ou=Groups,dc=domainsaid,dc=internal |
| `AD_ADMIN_GROUP` | cn=vpnadmins,ou=Groups,dc=domainsaid,dc=internal |

---

## Archivos de esta fase

```
readme-fase1.md   — Este archivo
```

No se generan archivos nuevos en esta fase.
Todo es configuracion del AD y verificacion de conectividad.

---

## Siguiente fase

**Fase 2 — OpenVPN + LDAP**
- Contenedor OpenVPN con autenticacion contra el AD
- Cliente externo conectando con credenciales del AD

---

*Fase 1 — TFG-OpenVPN · Said Rais · 2024/2025*
