#!/bin/bash
# =============================================================
# TFG-OpenVPN — OpenVPN init script
# Autor: Said Rais
# Descripcion: Inicializa certificados y arranca OpenVPN
# =============================================================

set -e

CERTS_DIR="/etc/openvpn/certs"
LOG_DIR="/var/log/openvpn"

log()  { echo "[OK] $1"; }
info() { echo "[->] $1"; }
warn() { echo "[AV] $1"; }

# ── Crear directorios necesarios ─────────────────────────────
mkdir -p $CERTS_DIR $LOG_DIR

# ── Generar certificados si no existen ───────────────────────
if [ ! -f "$CERTS_DIR/ca.crt" ]; then
    info "Generando certificados..."

    # CA
    openssl req -new -x509 -days 3650 -nodes \
        -keyout $CERTS_DIR/ca.key \
        -out $CERTS_DIR/ca.crt \
        -subj "/CN=TFG-OpenVPN-CA"

    # Servidor
    openssl req -new -nodes \
        -keyout $CERTS_DIR/server.key \
        -out $CERTS_DIR/server.csr \
        -subj "/CN=TFG-OpenVPN-Server"

    openssl x509 -req -days 3650 \
        -in $CERTS_DIR/server.csr \
        -CA $CERTS_DIR/ca.crt \
        -CAkey $CERTS_DIR/ca.key \
        -CAcreateserial \
        -out $CERTS_DIR/server.crt

    # Diffie-Hellman
    openssl dhparam -out $CERTS_DIR/dh.pem 2048

    # TLS Auth key
    openvpn --genkey secret $CERTS_DIR/ta.key

    log "Certificados generados"
else
    warn "Certificados ya existen, usando los existentes"
fi


# ── Configurar NAT ───────────────────────────────────────────
iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -o eth0 -j MASQUERADE

# ── Reemplazar variables del entorno en ldap.conf ────────────
sed -i "s|ldap://localhost:389|ldap://${AD_HOST}:${AD_PORT}|g" /etc/openvpn/config/ldap.conf
sed -i "s|cn=vpnbind,ou=ServiceAccounts,dc=domainsaid,dc=internal|${AD_BIND_USER}|g" /etc/openvpn/config/ldap.conf
sed -i "s|Cambiame123|${AD_BIND_PASS}|g" /etc/openvpn/config/ldap.conf
sed -i "s|ou=Users,dc=domainsaid,dc=internal|${AD_BASE_DN}|g" /etc/openvpn/config/ldap.conf
sed -i "s|ou=Groups,dc=domainsaid,dc=internal|${AD_BASE_DN}|g" /etc/openvpn/config/ldap.conf

# ── Arrancar OpenVPN ─────────────────────────────────────────
log "Arrancando OpenVPN..."
exec openvpn --config /etc/openvpn/config/server.conf
