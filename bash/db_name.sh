#!/bin/bash
# db_name.sh – outputs DB names, MySQL CLI args, or datadir from ../.env.local
# Backwards compatible: default (no args) returns project DB name
# Usage:
#   ./db_name.sh           -> prints project DB name (from DATABASE_URL)
#   ./db_name.sh sys       -> prints system DB name (from DATABASE_SYS_URL)
#   ./db_name.sh sys-conn  -> prints mysql CLI args for system DB
#   ./db_name.sh both      -> prints both DATABASE_URL and DATABASE_SYS_URL db names (one per line)
#   ./db_name.sh datadir   -> prints DATABASE_DATADIR

ENV_FILE="$(dirname "$0")/../.env.local"

if [ ! -f "$ENV_FILE" ]; then
  echo "Error: .env.local not found at $ENV_FILE" >&2
  exit 1
fi

get_dbname_from_url() {
  local key="$1"
  local line
  line=$(grep -E "^${key}=" "$ENV_FILE" || true)
  if [ -z "$line" ]; then
    return 1
  fi
  echo "$line" | sed -E 's/.*\/([^\/\?]+).*/\1/' | tr -d '"'
}

get_mysql_args_from_url() {
  local key="$1"
  local line
  line=$(grep -E "^${key}=" "$ENV_FILE" || true)
  if [ -z "$line" ]; then
    return 1
  fi
  line=${line#*=}
  line=${line#\"}
  line=${line%\"}
  line=${line#mysql://}

  local userpass hostport db_and_more user pass host port db
  userpass=${line%@*}
  hostport_and_rest=${line#*@}

  if [[ "$hostport_and_rest" == "$line" ]]; then
    hostport_and_rest="$line"
    userpass=""
  fi

  hostport=${hostport_and_rest%%/*}
  db_and_more=${hostport_and_rest#*/}
  db="${db_and_more%%\?*}"

  if [ -n "$userpass" ]; then
    user="${userpass%%:*}"
    pass="${userpass#*:}"
    [ "$pass" = "$user" ] && pass=""
  fi

  host="${hostport%%:*}"
  port="${hostport#*:}"
  [ "$port" = "$host" ] && port=""

  local args=""
  [ -n "$user" ] && args="$args -u $user"
  [ -n "$pass" ] && args="$args -p$pass"
  [ -n "$host" ] && args="$args -h $host"
  [ -n "$port" ] && args="$args -P $port"
  [ -n "$db" ] && args="$args $db"

  echo "$args"
}

get_datadir() {
  local line
  line=$(grep -E "^DATABASE_DATADIR=" "$ENV_FILE" || true)
  if [ -z "$line" ]; then
    echo "Error: DATABASE_DATADIR not defined in $ENV_FILE" >&2
    exit 1
  fi
  echo "${line#*=}" | tr -d '"'
}

case "$1" in
  datadir)
    get_datadir
    ;;
  sys)
    get_dbname_from_url "DATABASE_SYS_URL" || exit 1
    ;;
  both)
    get_dbname_from_url "DATABASE_URL" || echo ""
    get_dbname_from_url "DATABASE_SYS_URL" || echo ""
    ;;
  conn)
    get_mysql_args_from_url "DATABASE_URL" || exit 1
    ;;
  main-conn)
    get_mysql_args_from_url "DATABASE_URL" || exit 1
    ;;
  sys-conn)
    get_mysql_args_from_url "DATABASE_SYS_URL" || exit 1
    ;;
  "")
    get_dbname_from_url "DATABASE_URL" || exit 1
    ;;
  *)
    get_dbname_from_url "DATABASE_URL" || exit 1
    ;;
esac
