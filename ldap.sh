# Borrar vpnbind actual
docker exec openldap-test ldapdelete \
    -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal"

# Crear nuevo archivo con el hash real
cat > /tmp/vpnbind2.ldif << 'EOF'
dn: cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal
objectClass: inetOrgPerson
objectClass: simpleSecurityObject
cn: vpnbind
sn: vpnbind
description: Usuario de servicio para autenticacion LDAP
userPassword: +pLMYTaNEgkRvn/HIIweAjeBNBFKx9RA
EOF

# Copiar e importar
docker cp /tmp/vpnbind2.ldif openldap-test:/tmp/vpnbind2.ldif
docker exec openldap-test ldapadd \
    -x \
    -H ldap://localhost \
    -D "cn=admin,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -f /tmp/vpnbind2.ldif

# Probar bind
docker exec openldap-test ldapsearch \
    -x \
    -H ldap://localhost \
    -D "cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal" \
    -w "Cambiame123" \
    -b "dc=domainsaid,dc=internal" \
    "(objectClass=inetOrgPerson)" \
    cn
