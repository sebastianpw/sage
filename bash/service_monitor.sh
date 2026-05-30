#!/data/data/com.termux/files/usr/bin/bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

restart_all() {
  echo "[ACTION] restarting servers"
  "$SCRIPT_DIR/restart_servers.sh"
  exit 1
}

check_port () {
  PORT=$1
  NAME=$2
  ip=127.0.0.1

  if timeout 2 bash -c "cat < /dev/null > /dev/tcp/$ip/$PORT" 2>/dev/null; then
    echo "[OK] $NAME running on port $PORT"
  else
    echo "[FAIL] $NAME NOT running on port $PORT - restarting servers"
    restart_all
  fi
}

check_process () {
  PROC=$1

  if pgrep -f "$PROC" > /dev/null; then
    echo "[OK] process $PROC running"
  else
    echo "[FAIL] process $PROC not running - restarting servers"
    restart_all
  fi
}

echo "---- $(date) ----"

check_process nginx
check_port 8080 "nginx"

check_process mariadbd
check_port 3306 "MariaDB"

check_port 8009 "PyAPI"

echo "[OK] all services healthy"


