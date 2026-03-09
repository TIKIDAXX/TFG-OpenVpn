# 📋 Lista de Pendientes — TFG-OpenVPN

> Actualizado tras Fase 2 — Said Rais · 2024/2025

---

## 🔴 Critico — Necesita hacerse antes de continuar

| Tarea | Fase | Motivo |
|-------|------|--------|
| Eliminar `version: '3.8'` del docker-compose.yml | 2 | Deprecado en Docker Compose v2 |
| Generar archivo .ovpn para cliente | 2 | Necesario para probar VPN |

---

## 🟡 Pendiente — Cuando estes en LAN

| Tarea | Fase | Motivo |
|-------|------|--------|
| Probar conexion cliente OpenVPN real | 2 | Necesita estar en LAN + puertos router |
| Verificar autenticacion con aula1/aula2 | 2 | Necesita cliente conectado |
| Abrir puerto 1194 UDP en el router | 2 | Para acceso externo |

---

## 🟡 Pendiente — Cuando tengas el AD real

| Tarea | Fase | Motivo |
|-------|------|--------|
| Parar contenedor OpenLDAP temporal | 1 | Ya no necesario |
| Crear OUs y grupos en Samba AD/DC | 1 | Estructura real del AD |
| Actualizar .env con IP real del AD | 1 | AD_HOST=10.0.0.10 |
| Verificar ldapsearch con AD real | 1 | Confirmar conectividad |
| Verificar autenticacion VPN con AD real | 2 | Plugin LDAP apuntando al AD |

---

## 🟡 Pendiente — Variables del .env

| Variable | Cuando rellenar |
|---------|----------------|
| `TELEGRAM_BOT_TOKEN` | Fase 5 |
| `TELEGRAM_CHAT_MAIN` | Fase 5 |
| `TELEGRAM_CHAT_MFA` | Fase 5 |
| `TELEGRAM_CHAT_SOPORTE` | Fase 5 |
| `TELEGRAM_ADMIN_IDS` | Fase 5 |
| `TELEGRAM_SOPORTE_IDS` | Fase 5 |
| `OPENVPN_HOSTNAME` | Cuando abras puertos router |
| `PORTAL_SESSION_SECRET` | Fase 4 — cambiar el temporal |
| `AD_VPN_GROUP` | Cuando tengas AD real |
| `AD_ADMIN_GROUP` | Cuando tengas AD real |

---

## 🟢 Completado

| Tarea | Fase |
|-------|------|
| Raspberry Pi OS instalado | 0 |
| Docker + Docker Compose | 0 |
| SSH con clave publica | 0 |
| Firewall basico | 0 |
| IP Forwarding | 0 |
| Repositorio clonado | 0 |
| OpenLDAP temporal funcionando | 1 |
| Estructura LDAP creada | 1 |
| Grupos vpnusers y vpnadmins | 1 |
| Usuarios aula1 y aula2 | 1 |
| ldapsearch funcionando | 1 |
| docker-compose.yml | 2 |
| OpenVPN CE en Docker | 2 |
| Certificados SSL generados | 2 |
| Plugin LDAP cargado | 2 |
| Red VPN 10.8.0.0/24 activa | 2 |
| Puerto 1194 UDP escuchando | 2 |

---

## 📅 Proximas fases

| Fase | Contenido | Estado |
|------|-----------|--------|
| Fase 3 | Prometheus + Grafana + Loki | 🔜 Siguiente |
| Fase 4 | Portal Web PHP + MySQL + Nginx | ⏳ Pendiente |
| Fase 5 | Bot Telegram + MFA | ⏳ Pendiente |
| Fase 6 | Seguridad + Fail2Ban + Hardening | ⏳ Pendiente |
| Fase 7 | Pruebas + Memoria | ⏳ Pendiente |

---

*Actualizado: Fase 2 completada — TFG-OpenVPN · Said Rais · 2024/2025*
