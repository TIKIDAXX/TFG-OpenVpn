# 1. Docker funcionando
docker ps

# 2. Docker Compose disponible
docker compose version

# 3. IP Forwarding activo (debe devolver 1)
cat /proc/sys/net/ipv4/ip_forward

# 4. Firewall activo
sudo iptables -L -n

# 5. SSH solo con clave publica
sudo grep "PasswordAuthentication" /etc/ssh/sshd_config

# 6. Hostname correcto
hostname

# 7. Verificar que el repo esta clonado
ls /opt/tfg-openvpn
