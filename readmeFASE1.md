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

> ⚠️ Durante esta fase el AD real no estaba disponible. Se uso un contenedor
> OpenLDAP temporal para verificar la conectividad y estructura LDAP.
> Cuando el AD real este disponible solo hay que actualizar el .env.

---

## Checklist

- [x] Grupos creados en el AD (vpnusers, vpnadmins)
- [x] OU de grupos creada
- [x] OU de cuentas de servicio creada
- [x] Usuario de servicio vpnbind creado
- [x] Usuarios de prueba añadidos a vpnusers (aula1, aula2)
- [x] Puerto 389 accesible desde la Pi
- [x] ldapsearch devuelve usuarios correctamente
- [x] ldapsearch verifica grupo vpnusers con miembros
- [x] ldapsearch verifica grupo vpnadmins con miembros
- [ ] .env actualizado con datos reales del AD ← pendiente con AD real

---

## Estructura del AD

```
dc=domainsaid,dc=internal
├── OU=Groups
│   ├── cn=vpnusers       ← aula1, aula2 tienen acceso VPN
│   └── cn=vpnadmins      ← aula1 es administrador VPN
├── OU=ServiceAccounts
│   └── cn=vpnbind        ← Usuario de servicio para LDAP bind
└── OU=Users
    ├── cn=aula1          ← Usuario de prueba 1
    └── cn=aula2          ← Usuario de prueba 2
```

---

## Pasos de implementacion

### PASO 1 — OpenLDAP temporal en Docker

Como el AD real no estaba disponible, levantamos un contenedor OpenLDAP
para simular el entorno y verificar la conectividad LDAP.

```bash
# Crear docker-compose temporal
cat > /tmp/ldap-test.yml << 'EOF2'
version: '3'
services:
  openldap:
    image: osixia/openldap:latest
    container_name: openldap-test
    environment:
      LDAP_ORGANISATION: "domainsaid"
      LDAP_DOMAIN: "domainsaid.internal"
      LDAP_ADMIN_PASSWORD: "Cambiame123"
      LDAP_BASE_DN: "dc=domainsaid,dc=internal"
    ports:
      - "389:389"
    networks:
      - ldap-test
networks:
  ldap-test:
    driver: bridge
EOF2

# Levantar contenedor
docker compose -f /tmp/ldap-test.yml up -d
sleep 10
docker ps | grep openldap
```

---

### PASO 2 — Crear estructura LDAP

```bash
# Crear archivo con OUs, grupos y usuarios
cat > /tmp/estructura.ldif << 'EOF2'
dn: ou=Groups,dc=domainsaid,dc=internal
objectClass: organizationalUnit
ou: Groups

dn: ou=ServiceAccounts,dc=domainsaid,dc=internal
objectClass: organizationalUnit
ou: ServiceAccounts

dn: ou=Users,dc=domainsaid,dc=internal
objectClass: organizationalUnit
ou: Users

dn: cn=vpnusers,ou=Groups,dc=domainsaid,dc=internal
objectClass: groupOfNames
cn: vpnusers
description: Usuarios con acceso a VPN
member: cn=aula1,ou=Users,dc=domainsaid,dc=internal
member: cn=aula2,ou=Users,dc=domainsaid,dc=internal

dn: cn=vpnadmins,ou=Groups,dc=domainsaid,dc=internal
objectClass: groupOfNames
cn: vpnadmins
description: Administradores de la VPN
member: cn=aula1,ou=Users,dc=domainsaid,dc=internal

dn: cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal
objectClass: inetOrgPerson
cn: vpnbind
sn: vpnbind
userPassword: Cambiame123
description: Usuario de servicio para autenticacion LDAP

dn: cn=aula1,ou=Users,dc=domainsaid,dc=internal
objectClass: inetOrgPerson
cn: aula1
sn: aula1
userPassword: Aula1pass123
mail: aula1@domainsaid.internal

dn: cn=aula2,ou=Users,dc=domainsaid,dc=internal
objectClass: inetOrgPerson
cn: aula2
sn: aula2
userPassword: Aula2pass123
mail: aula2@domainsaid.internal
EOF2

# Copiar e importar dentro del contenedor
docker cp /tmp/estructura.ldif openldap-test:/tmp/estructura.ldif
docker exec openldap-test ldapadd \
    -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -f /tmp/estructura.ldif
```

---

### PASO 3 — Dar permisos de lectura a vpnbind

```bash
# Crear ACL para vpnbind
cat > /tmp/acl.ldif << 'EOF2'
dn: olcDatabase={1}mdb,cn=config
changetype: modify
add: olcAccess
olcAccess: {0}to * by dn="cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" read by * none
EOF2

# Aplicar ACL
docker cp /tmp/acl.ldif openldap-test:/tmp/acl.ldif
docker exec openldap-test ldapmodify \
    -Y EXTERNAL \
    -H ldapi:/// \
    -f /tmp/acl.ldif
```

---

### PASO 4 — Verificar conectividad

```bash
# Puerto LDAP accesible
nc -zv localhost 389

# Ver toda la estructura
ldapsearch -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(objectClass=*)" dn

# Verificar usuarios
ldapsearch -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(objectClass=inetOrgPerson)" cn mail

# Verificar grupo vpnusers
ldapsearch -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(cn=vpnusers)" member

# Verificar grupo vpnadmins
ldapsearch -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(cn=vpnadmins)" member
```

---

### PASO 5 — Actualizar el .env

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

### PASO 6 — Parar contenedor temporal

```bash
docker compose -f /tmp/ldap-test.yml down
```

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| AD real no disponible | Maquina AD apagada | Usar contenedor OpenLDAP temporal para verificar conectividad |
| `Permission denied` al crear estructura.ldif | Archivo creado sin permisos correctos | `docker cp` para copiar el archivo dentro del contenedor |
| `ldap_bind: Invalid credentials (49)` con vpnbind | Password de vpnbind no hasheado correctamente por OpenLDAP | Usar admin para las busquedas en el entorno de prueba |
| vpnbind no tenia permisos de lectura | OpenLDAP por defecto no da permisos a usuarios no admin | Aplicar ACL con `ldapmodify -Y EXTERNAL` |
| `cn=admin,cn=config` da credenciales invalidas | OpenLDAP no expone config por LDAP simple | Usar `-Y EXTERNAL -H ldapi:///` para modificar configuracion |

---

## Resultado de la verificacion

```
# Puerto 389
Connection to localhost 389 port [tcp/ldap] succeeded!

# Estructura completa
dn: dc=domainsaid,dc=internal
dn: ou=Groups,dc=domainsaid,dc=internal
dn: ou=ServiceAccounts,dc=domainsaid,dc=internal
dn: ou=Users,dc=domainsaid,dc=internal
dn: cn=vpnusers,ou=Groups,dc=domainsaid,dc=internal
dn: cn=vpnadmins,ou=Groups,dc=domainsaid,dc=internal
dn: cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal
dn: cn=aula1,ou=Users,dc=domainsaid,dc=internal
dn: cn=aula2,ou=Users,dc=domainsaid,dc=internal
```

---

## Estado al finalizar la fase

| Componente | Estado |
|-----------|--------|
| Contenedor OpenLDAP temporal | ✅ |
| OUs creadas correctamente | ✅ |
| Grupo vpnusers con aula1 y aula2 | ✅ |
| Grupo vpnadmins con aula1 | ✅ |
| Usuario vpnbind creado | ✅ |
| Puerto 389 accesible | ✅ |
| ldapsearch funciona | ✅ |
| AD real verificado | 🔄 Pendiente |
| .env actualizado con AD real | 🔄 Pendiente |

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
/tmp/ldap-test.yml      — Docker compose OpenLDAP temporal (no subir al repo)
/tmp/estructura.ldif    — Estructura LDAP de prueba (no subir al repo)
/tmp/acl.ldif           — ACL para vpnbind (no subir al repo)
```

---

## Siguiente fase

**Fase 2 — OpenVPN + LDAP**
- Contenedor OpenVPN con autenticacion contra el AD
- Cliente externo conectando con credenciales del AD

---

*Fase 1 — TFG-OpenVPN · Said Rais · 2024/2025*
