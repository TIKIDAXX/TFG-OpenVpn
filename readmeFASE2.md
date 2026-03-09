# Fase 2 — OpenVPN + LDAP

> TFG-OpenVPN · Said Rais · CFGS ASIR 2º · IES Isabel de Villena · 2024/2025

---

## Objetivo

Desplegar OpenVPN Community Edition en Docker autenticando contra
el AD/LDAP externo. Cliente externo conectando con credenciales del AD.

---

## Infraestructura de esta fase

```
Cliente VPN          Raspberry Pi 5
(PC/Movil)    →     (192.168.1.140)
OpenVPN             OpenVPN CE Docker
Connect             Puerto 1194/UDP
                    Puerto 443/TCP
                         │
                         ▼
                    LDAP (localhost:389)
                    OpenLDAP temporal
                    (AD real pendiente)
```

---

## Checklist

- [x] docker-compose.yml creado
- [x] openvpn/Dockerfile creado
- [x] openvpn/config/server.conf creado
- [x] openvpn/config/ldap.conf creado
- [x] openvpn/scripts/init.sh creado
- [x] Certificados SSL generados automaticamente
- [x] Plugin openvpn-auth-ldap cargado
- [x] Interfaz tun0 creada
- [x] Red VPN 10.8.0.0/24 activa
- [x] Puerto 1194 UDP escuchando
- [x] Puerto 443 TCP abierto
- [x] Initialization Sequence Completed
- [ ] Archivo .ovpn de cliente generado
- [ ] Prueba de conexion cliente real ← pendiente (necesita LAN)
- [ ] Verificar autenticacion LDAP con usuario aula1/aula2
- [ ] Verificar con AD real cuando este disponible

---

## Archivos generados en esta fase

```
docker-compose.yml              — Orquestacion completa del stack
openvpn/
├── Dockerfile                  — Imagen OpenVPN CE
├── config/
│   ├── server.conf             — Configuracion OpenVPN
│   └── ldap.conf               — Autenticacion LDAP
└── scripts/
    └── init.sh                 — Inicializacion y certificados
```

---

## Pasos de implementacion

### PASO 1 — Subir archivos al repo y actualizar la Pi

```bash
cd /opt/tfg-openvpn
git pull
sed -i 's/\r//' openvpn/scripts/init.sh
```

---

### PASO 2 — Construir y arrancar OpenVPN

```bash
# Construir imagen
docker compose build openvpn

# Arrancar OpenVPN y MySQL
docker compose up -d openvpn mysql

# Ver logs
docker compose logs -f openvpn
```

---

### PASO 3 — Verificar que OpenVPN esta corriendo

```bash
# Ver logs completos
docker exec openvpn cat /var/log/openvpn/openvpn.log

# Ver puerto 1194 escuchando
docker exec openvpn netstat -tulnp | grep 1194

# Ver estado del contenedor
docker ps | grep openvpn
```

---

### PASO 4 — Generar archivo .ovpn para cliente

```bash
# Generar certificado de cliente
docker exec openvpn openssl req -new -nodes \
    -keyout /etc/openvpn/certs/client.key \
    -out /etc/openvpn/certs/client.csr \
    -subj "/CN=cliente-prueba"

docker exec openvpn openssl x509 -req -days 365 \
    -in /etc/openvpn/certs/client.csr \
    -CA /etc/openvpn/certs/ca.crt \
    -CAkey /etc/openvpn/certs/ca.key \
    -CAcreateserial \
    -out /etc/openvpn/certs/client.crt

# Extraer certificados para el .ovpn
CA=$(docker exec openvpn cat /etc/openvpn/certs/ca.crt)
CERT=$(docker exec openvpn cat /etc/openvpn/certs/client.crt)
KEY=$(docker exec openvpn cat /etc/openvpn/certs/client.key)
TA=$(docker exec openvpn cat /etc/openvpn/certs/ta.key)

# Generar archivo .ovpn
cat > /tmp/cliente.ovpn << OVPN
client
dev tun
proto udp
remote 192.168.1.140 1194
resolv-retry infinite
nobind
persist-key
persist-tun
auth-user-pass
cipher AES-256-CBC
auth SHA256
verb 3
key-direction 1
<ca>
${CA}
</ca>
<cert>
${CERT}
</cert>
<key>
${KEY}
</key>
<tls-auth>
${TA}
</tls-auth>
OVPN

# Copiar a directorio accesible
cp /tmp/cliente.ovpn /opt/tfg-openvpn/cliente.ovpn
```

---

### PASO 5 — Probar conexion (pendiente — necesita estar en LAN)

1. Instalar OpenVPN Connect: https://openvpn.net/client/
2. Importar el archivo `cliente.ovpn`
3. Conectar con usuario: `aula1` y password: `Aula1pass123`
4. Verificar que se asigna IP del rango 10.8.0.x

---

## Problemas encontrados y soluciones

| Problema | Causa | Solucion |
|---------|-------|---------|
| `version obsolete` en docker-compose | Atributo `version` deprecado en Docker Compose v2 | Eliminar la linea `version: '3.8'` del compose |
| `/proc/sys/net/ipv4/ip_forward: Read-only file system` | No se puede modificar /proc desde dentro del contenedor | Eliminar esa linea del init.sh — IP Forwarding ya habilitado en la Pi desde Fase 0 |
| `sed: couldn't open temporary file: Read-only file system` | El volumen config estaba montado como `:ro` | Cambiar `:ro` a lectura/escritura en docker-compose.yml |
| Contenedor reiniciandose en bucle | Fallo en init.sh por los errores anteriores | Corregir init.sh y reconstruir imagen |

---

## Warnings conocidos — No criticos

| Warning | Explicacion | Accion |
|---------|------------|--------|
| `AES-256-CBC deprecated` | Cipher antiguo | En produccion usar AES-256-GCM |
| `--topology net30 deprecated` | Topologia antigua | Cambiar a subnet en produccion |
| `Management sin password` | Solo accesible red interna Docker | Aceptable para TFG |
| `--verify-client-cert none` | Necesario para auth LDAP | Correcto para nuestro caso |

---

## Resultado de la verificacion

```
# Estado contenedor
openvpn   Up   0.0.0.0:443->443/tcp, 0.0.0.0:1194->1194/udp

# Log OpenVPN
2026-03-09 09:45:21 PLUGIN_INIT: openvpn-auth-ldap.so intercepted
2026-03-09 09:45:21 Diffie-Hellman initialized with 2048 bit key
2026-03-09 09:45:21 TUN/TAP device tun0 opened
2026-03-09 09:45:21 net_addr_ptp_v4_add: 10.8.0.1 peer 10.8.0.2 dev tun0
2026-03-09 09:45:21 UDPv4 link local (bound): [AF_INET][undef]:1194
2026-03-09 09:45:21 Initialization Sequence Completed

# Puerto 1194
udp 0.0.0.0:1194 0.0.0.0:* 1/openvpn
```

---

## Estado al finalizar la fase

| Componente | Estado |
|-----------|--------|
| docker-compose.yml | ✅ |
| OpenVPN Dockerfile | ✅ |
| server.conf | ✅ |
| ldap.conf | ✅ |
| init.sh | ✅ |
| Certificados SSL | ✅ |
| Plugin LDAP cargado | ✅ |
| Interfaz tun0 | ✅ |
| Red VPN 10.8.0.0/24 | ✅ |
| Puerto 1194 UDP | ✅ |
| Prueba cliente real | 🔄 Pendiente LAN |
| AD real verificado | 🔄 Pendiente |

---

## Variables del .env actualizadas en esta fase

No hay variables nuevas en esta fase.
Todas las variables de OpenVPN ya estaban en el .env desde Fase 0.

---

## Siguiente fase

**Fase 3 — Prometheus + Grafana**
- Levantar stack de monitorización completo
- Dashboards sistema, contenedores y VPN
- Loki + Promtail para logs

---

*Fase 2 completada — TFG-OpenVPN · Said Rais · 2024/2025*
