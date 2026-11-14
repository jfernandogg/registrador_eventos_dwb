#!/usr/bin/env bash
# Levanta los reenviadores de prueba del entorno local:
#   1) Forward de la app   : localhost:8080 -> 172.29.128.92:8080 (WSL/podman)
#   2) Túnel cloudflared   : expone http://localhost:8080 como https pública
#                            para recibir el Webhook de Bold.
#
# NO se levanta el forward de Mailpit: los correos se envían por SMTP directo
# (smtp.gmail.com), así que Mailpit no interviene en el envío real.
#
# Uso:  bash scripts/levantar-tunel-prueba.sh
# Idempotente: si el forward o el túnel ya están activos, no hace nada.
set -uo pipefail
cd "$(dirname "$0")/.."

LOG_DIR="writable/logs/pruebas"
mkdir -p "$LOG_DIR"

echo "== Reenviadores de prueba =="

# --- 1) Forward de la app (Windows -> WSL/podman) ---------------------------
if curl -s -o /dev/null --max-time 8 http://localhost:8080/index.php \
   || { netstat -ano 2>/dev/null | grep -E ':(8080)\s' | grep -qi listen; }; then
  echo "  [8080]   app ya accesible en http://localhost:8080"
else
  echo "  [8080]   levantando forward (127.0.0.1:8080 -> 172.29.128.92:8080)..."
  nohup python scripts/forward-8080.py > "$LOG_DIR/forward-8080.log" 2>&1 &
  sleep 3
  if curl -s -o /dev/null --max-time 8 http://localhost:8080/index.php; then
    echo "  [8080]   OK -> http://localhost:8080"
  else
    echo "  [8080]   ERROR: no responde. Revisá $LOG_DIR/forward-8080.log"
  fi
fi

# --- 2) Túnel cloudflared (Webhook Bold) ------------------------------------
if tasklist 2>/dev/null | grep -qi 'cloudflared'; then
  echo "  [tunel]  cloudflared ya está corriendo (no se reinicia para no cambiar la URL)."
  echo "  [tunel]  Usá la URL pública que ya configuraste en el panel de Bold."
else
  CLOUD="$(command -v cloudflared.exe 2>/dev/null || command -v cloudflared 2>/dev/null || echo cloudflared.exe)"
  echo "  [tunel]  levantando cloudflared -> http://localhost:8080 ..."
  nohup "$CLOUD" tunnel --url http://localhost:8080 > "$LOG_DIR/cloudflared.log" 2>&1 &
  URL=""
  for i in $(seq 1 25); do
    URL=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$LOG_DIR/cloudflared.log" | head -1 || true)
    [[ -n "$URL" ]] && break
    sleep 1
  done
  if [[ -n "$URL" ]]; then
    echo "  [tunel]  Webhook público: ${URL}/index.php/webhook/bold"
    echo "  [tunel]  Copiá esa URL completa en el panel de Bold (Integraciones → Webhook)."
  else
    echo "  [tunel]  ERROR: no se detectó la URL; revisá $LOG_DIR/cloudflared.log"
  fi
fi

echo "== Listo =="