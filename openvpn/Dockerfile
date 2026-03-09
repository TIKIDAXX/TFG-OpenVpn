# =============================================================
# TFG-OpenVPN — OpenVPN Dockerfile
# Autor: Said Rais
# =============================================================

FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y \
    openvpn \
    openvpn-auth-ldap \
    easy-rsa \
    openssl \
    iptables \
    net-tools \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Crear directorios necesarios
RUN mkdir -p /etc/openvpn/certs \
    && mkdir -p /etc/openvpn/ccd \
    && mkdir -p /var/log/openvpn

# Copiar scripts
COPY scripts/init.sh /etc/openvpn/init.sh
RUN chmod +x /etc/openvpn/init.sh

EXPOSE 1194/udp
EXPOSE 443/tcp

CMD ["/etc/openvpn/init.sh"]
