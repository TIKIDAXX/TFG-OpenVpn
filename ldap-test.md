📋 Comandos ejecutados en Fase 1 (OpenLDAP temporal)
1. Crear el docker-compose temporal
bashcat > /tmp/ldap-test.yml << 'EOF'
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
EOF
Por qué: Levantamos un LDAP temporal en Docker para simular el AD real mientras no tenemos acceso a la máquina Samba AD/DC.

2. Levantar el contenedor OpenLDAP
bashdocker compose -f /tmp/ldap-test.yml up -d
Por qué: Arranca el contenedor en segundo plano. La imagen osixia/openldap es la más usada para simular LDAP en Docker.

3. Verificar que está corriendo
bashdocker ps | grep openldap
Por qué: Confirmamos que el contenedor está activo y escuchando en el puerto 389.

4. Crear el archivo con la estructura LDAP
bashcat > /tmp/estructura.ldif << 'EOF'
# OUs, grupos y usuarios
...
EOF
Por qué: El formato LDIF es el estándar para importar datos en cualquier servidor LDAP. Creamos:

ou=Groups → donde van los grupos vpnusers y vpnadmins
ou=ServiceAccounts → donde va el usuario vpnbind
ou=Users → donde van los usuarios aula1 y aula2
cn=vpnusers → grupo con acceso VPN
cn=vpnadmins → grupo de administradores VPN
cn=vpnbind → usuario de servicio para hacer el bind LDAP
cn=aula1 y cn=aula2 → usuarios de prueba


5. Copiar el archivo dentro del contenedor
bashdocker cp /tmp/estructura.ldif openldap-test:/tmp/estructura.ldif
Por qué: El contenedor no tiene acceso directo a /tmp del host. Hay que copiar el archivo dentro para que el comando ldapadd pueda leerlo.

6. Importar la estructura en OpenLDAP
bashdocker exec openldap-test ldapadd \
    -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -f /tmp/estructura.ldif
```
**Por qué:** `ldapadd` importa el archivo LDIF en el servidor. Los parámetros:
- `-x` → autenticación simple
- `-H` → URL del servidor LDAP
- `-D` → usuario admin para hacer el bind
- `-w` → password del admin
- `-f` → archivo LDIF a importar

---

## 🐛 Errores encontrados y soluciones

| Error | Causa | Solución |
|-------|-------|---------|
| `Permission denied` en `/tmp/estructura.ldif` | Ejecutar con `sudo` cuando el archivo fue creado por otro usuario | Usar `docker cp` para copiar el archivo dentro del contenedor |
| `No such file or directory` | Ruta incorrecta del archivo dentro del contenedor | Copiar primero con `docker cp` y luego referenciar `/tmp/estructura.ldif` |

---

## ✅ Resultado final
```
Contenedor openldap-test corriendo en puerto 389
Estructura LDAP creada:
  - ou=Groups
  - ou=ServiceAccounts  
  - ou=Users
  - cn=vpnusers (miembros: aula1, aula2)
  - cn=vpnadmins (miembros: aula1)
  - cn=vpnbind (usuario de servicio)
  - cn=aula1
  - cn=aula2

⚠️ Importante recordar
Este OpenLDAP es temporal solo para verificar conectividad. Cuando tengas el Samba AD/DC real:

Para el contenedor: docker compose -f /tmp/ldap-test.yml down
Actualiza el .env con los datos reales del AD
Verifica de nuevo con ldapsearch
## 📋 Pendientes para cuando tengas el AD real

### 1. Parar OpenLDAP temporal
docker compose -f /tmp/ldap-test.yml down

### 2. Ejecutar en la máquina Samba AD/DC
samba-tool ou create "OU=Groups,DC=domainsaid,DC=internal"
samba-tool ou create "OU=ServiceAccounts,DC=domainsaid,DC=internal"
samba-tool group add vpnusers --groupou="OU=Groups,DC=domainsaid,DC=internal"
samba-tool group add vpnadmins --groupou="OU=Groups,DC=domainsaid,DC=internal"
samba-tool user create vpnbind Cambiame123 --userou="OU=ServiceAccounts,DC=domainsaid,DC=internal"
samba-tool group addmembers vpnusers aula1
samba-tool group addmembers vpnusers aula2
samba-tool group addmembers vpnadmins aula1

### 3. Verificar desde la Pi con AD real
ping -c 4 10.0.0.10
nc -zv 10.0.0.10 389
ldapsearch -x -H ldap://10.0.0.10 \
    -D "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(objectClass=user)" cn

### 4. Actualizar .env
AD_HOST=10.0.0.10
AD_BIND_USER=cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal
AD_BIND_PASS=Cambiame123
AD_VPN_GROUP=cn=vpnusers,ou=Groups,dc=domainsaid,dc=internal
AD_ADMIN_GROUP=cn=vpnadmins,ou=Groups,dc=domainsaid,dc=internal

## ⏭️ Siguiente
Fase 2 — OpenVPN + LDAP
