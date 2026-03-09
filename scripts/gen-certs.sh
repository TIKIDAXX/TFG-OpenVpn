#!/bin/bash
# =============================================================
# TFG-OpenVPN — Generar certificados autofirmados para Nginx
# Autor: Said Rais
# =============================================================

CERTS_DIR="./nginx/certs"
mkdir -p $CERTS_DIR

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout $CERTS_DIR/server.key \
    -out    $CERTS_DIR/server.crt \
    -subj "/C=ES/ST=Valencia/L=Valencia/O=TFG-OpenVPN/CN=192.168.1.140"

echo "[OK] Certificados generados en $CERTS_DIR"
ls -la $CERTS_DIR
