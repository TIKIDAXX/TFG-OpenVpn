#!/bin/bash
# =============================================================
# TFG-OpenVPN — Fase 0: Firewall basico
# Autor: Said Rais
# Descripcion: Reglas iptables basicas para la Pi
# =============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${GREEN}[OK] $1${NC}"; }
info() { echo -e "${BLUE}[->] $1${NC}"; }

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Ejecuta como root: sudo bash firewall.sh${NC}"
    exit 1
fi

info "Limpiando reglas existentes..."
iptables -F
iptables -X
iptables -t nat -F
iptables -t nat -X

info "Aplicando politica DROP por defecto..."
iptables -P INPUT DROP
iptables -P FORWARD DROP
iptables -P OUTPUT ACCEPT

info "Configurando reglas de acceso..."

# Loopback
iptables -A INPUT -i lo -j ACCEPT

# Conexiones ya establecidas
iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# SSH
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
log "SSH permitido (puerto 22)"

# OpenVPN UDP
iptables -A INPUT -p udp --dport 1194 -j ACCEPT
log "OpenVPN UDP permitido (puerto 1194)"

# HTTPS y OpenVPN TCP
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
log "HTTPS permitido (puerto 443)"

# HTTP (redirige a HTTPS)
iptables -A INPUT -p tcp --dport 80 -j ACCEPT

# Tailscale
iptables -A INPUT -i tailscale0 -j ACCEPT
log "Tailscale permitido"

# Red interna Docker
iptables -A INPUT -i docker0 -j ACCEPT
iptables -A FORWARD -i docker0 -j ACCEPT
iptables -A FORWARD -o docker0 -j ACCEPT

# NAT para clientes VPN
iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -o eth0 -j MASQUERADE
log "NAT para clientes VPN configurado"

# ICMP limitado
iptables -A INPUT -p icmp --icmp-type echo-request -m limit --limit 1/s -j ACCEPT

# Guardar reglas permanentes
netfilter-persistent save
log "Reglas guardadas (se mantienen tras reinicio)"

echo ""
echo "================================================"
info "Reglas activas:"
iptables -L -n --line-numbers
echo "================================================"
log "Firewall configurado correctamente"
