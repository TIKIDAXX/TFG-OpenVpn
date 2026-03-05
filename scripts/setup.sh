#!/bin/bash
# =============================================================
# TFG-OpenVPN — Fase 0: Setup inicial Raspberry Pi 5
# Autor: Said Rais
# Descripcion: Prepara la Pi con Docker, dependencias y Tailscale
# =============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()    { echo -e "${GREEN}[OK] $1${NC}"; }
warn()   { echo -e "${YELLOW}[AV] $1${NC}"; }
error()  { echo -e "${RED}[ER] $1${NC}"; exit 1; }
info()   { echo -e "${BLUE}[->] $1${NC}"; }

echo -e "${BLUE}"
echo "================================================"
echo "  TFG-OpenVPN - Setup Fase 0"
echo "  Raspberry Pi 5 - ASIR 2 Curso"
echo "================================================"
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    error "Ejecuta como root: sudo bash setup.sh"
fi

info "Actualizando sistema..."
apt-get update -qq && apt-get upgrade -y -qq
log "Sistema actualizado"

info "Instalando dependencias..."
apt-get install -y -qq \
    curl wget git vim htop net-tools ldap-utils \
    ca-certificates gnupg lsb-release \
    apt-transport-https software-properties-common \
    openssl nmap iptables iptables-persistent
log "Dependencias instaladas"

info "Instalando Docker..."
if command -v docker &> /dev/null; then
    warn "Docker ya instalado: $(docker --version)"
else
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh && rm get-docker.sh
    usermod -aG docker $SUDO_USER
    systemctl enable docker && systemctl start docker
    log "Docker instalado"
fi

info "Instalando Docker Compose..."
if command -v docker-compose &> /dev/null; then
    warn "Docker Compose ya instalado"
else
    COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep '"tag_name"' | cut -d'"' -f4)
    curl -SL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-aarch64" \
        -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    ln -sf /usr/local/bin/docker-compose /usr/bin/docker-compose
    log "Docker Compose instalado"
fi

info "Instalando Tailscale..."
if command -v tailscale &> /dev/null; then
    warn "Tailscale ya instalado"
else
    curl -fsSL https://tailscale.com/install.sh | sh
    systemctl enable tailscaled && systemctl start tailscaled
    log "Tailscale instalado"
fi

info "Configurando SSH seguro..."
SSHD_CONFIG="/etc/ssh/sshd_config"
cp $SSHD_CONFIG ${SSHD_CONFIG}.bak
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' $SSHD_CONFIG
sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' $SSHD_CONFIG
sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin no/' $SSHD_CONFIG
sed -i 's/PermitRootLogin yes/PermitRootLogin no/' $SSHD_CONFIG
systemctl restart ssh
log "SSH configurado - solo clave publica"

info "Configurando hostname..."
hostnamectl set-hostname tfg-openvpn-pi
log "Hostname: tfg-openvpn-pi"

info "Habilitando IP Forwarding..."
grep -q "net.ipv4.ip_forward=1" /etc/sysctl.conf || echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
grep -q "net.ipv6.conf.all.forwarding=1" /etc/sysctl.conf || echo "net.ipv6.conf.all.forwarding=1" >> /etc/sysctl.conf
sysctl -p -q
log "IP Forwarding habilitado"

echo ""
echo "================================================"
echo "  Verificacion del entorno"
echo "================================================"
command -v docker &> /dev/null && echo "  Docker:         OK - $(docker --version)" || echo "  Docker:         NO INSTALADO"
command -v docker-compose &> /dev/null && echo "  Docker Compose: OK" || echo "  Docker Compose: NO INSTALADO"
command -v tailscale &> /dev/null && echo "  Tailscale:      OK - Instalado" || echo "  Tailscale:      NO INSTALADO"
[ "$(cat /proc/sys/net/ipv4/ip_forward)" = "1" ] && echo "  IP Forwarding:  OK - Habilitado" || echo "  IP Forwarding:  DESHABILITADO"
echo "================================================"
echo ""
echo "[OK] Setup completado. Pasos manuales que quedan:"
echo "  1. En tu PC: ssh-copy-id pi@<IP_PI>"
echo "  2. Verifica sin contrasena: ssh pi@<IP_PI>"
echo "  3. En la Pi: sudo tailscale up --ssh"
echo "  4. Abre la URL que aparece en el navegador y vincula la Pi"
echo "  5. Activa 2FA en https://login.tailscale.com"
echo "  6. Copia el .env: cp .env.example .env && nano .env"
echo "  7. Aplica firewall: sudo bash scripts/firewall.sh"
echo ""
