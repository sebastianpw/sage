#!/bin/bash

# ==============================================================================
# SAGE Cloudflare Tunnel Runner
# Attaches cloudflared to a named tunnel. All deployment-specific settings
# (token, hostname, session name) live in token/.cloudflare_tunnel, which
# is NOT committed. This script is safe to commit.
# Self-locating; runs from anywhere in the project.
#
# Usage: ./cloudtunnel.sh <command> [args...]
#   ./cloudtunnel.sh start [-b]        # foreground (default) or tmux session
#   ./cloudtunnel.sh stop
#   ./cloudtunnel.sh status
#   ./cloudtunnel.sh attach
#   ./cloudtunnel.sh logs [N]
#   ./cloudtunnel.sh token
#   ./cloudtunnel.sh config            # show effective config + sources
#   ./cloudtunnel.sh config-help       # token file format docs
#   ./cloudtunnel.sh doctor
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Load optional project root helper (defines PROJECT_ROOT, FRAMES_ROOT, ...)
if [ -f "$SCRIPT_DIR/load_root.sh" ]; then
    source "$SCRIPT_DIR/load_root.sh"
fi
[ -z "$PROJECT_ROOT" ] && PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# DB connection args — loaded for consistency with pai.sh / genworker_queue.sh.
# cloudtunnel.sh itself does not touch the database.
MYSQL_ARGS=""
if [ -x "$SCRIPT_DIR/db_name.sh" ]; then
    MYSQL_ARGS=$("$SCRIPT_DIR/db_name.sh" main-conn 2>/dev/null || true)
fi

# ------------------------------------------------------------------------------
# Paths and constants (no deployment-specific values here)
# ------------------------------------------------------------------------------
TOKEN_FILE="$SCRIPT_DIR/../token/.cloudflare_tunnel"
CLOUDFLARED_BIN="${CLOUDFLARED_BIN:-cloudflared}"
CURL_TIMEOUT="${CURL_TIMEOUT:-10}"

# Defaults used only when neither env nor file supplies a value
DEFAULT_HOST="app.example.org"
DEFAULT_SESSION="tunnel"

# ------------------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------------------
die()  { echo "cloudtunnel.sh: $*" >&2; exit 1; }
info() { echo "cloudtunnel.sh: $*" >&2; }

have_cmd() { command -v "$1" >/dev/null 2>&1; }

mask_token() {
    local t="$1"
    if [ ${#t} -gt 16 ]; then
        printf '%s…%s (%d chars)' "${t:0:10}" "${t: -6}" "${#t}"
    elif [ -n "$t" ]; then
        printf '(%d chars, too short to mask)' "${#t}"
    else
        printf '(empty)'
    fi
}

decode_token_tunnel_id() {
    # JWT payload field `t` = tunnel UUID. Diagnostic only.
    python3 -c '
import sys, base64, json
p = sys.argv[1].split(".")[1]
p += "=" * (-len(p) % 4)
print(json.loads(base64.urlsafe_b64decode(p)).get("t", ""))
' "$1" 2>/dev/null || true
}

session_active() {
    have_cmd tmux && tmux has-session -t "$SESSION" 2>/dev/null
}
cloudflared_running() {
    pgrep -f "cloudflared.*tunnel run" >/dev/null 2>&1
}

# ------------------------------------------------------------------------------
# Config loader — parses token/.cloudflare_tunnel
# Precedence: env > file > default
# ------------------------------------------------------------------------------
_load_config() {
    local file="$1"

    # Snapshot any env-provided values before we overwrite the variables
    local env_token="${CLOUDFLARE_TOKEN:-${TOKEN:-}}"
    local env_host="${TUNNEL_HOST:-}"
    local env_session="${SESSION:-}"

    local file_token="" file_host="" file_session=""

    if [ -r "$file" ] && [ -s "$file" ]; then
        local line key val
        while IFS= read -r line || [ -n "$line" ]; do
            # Strip CR (Windows newline) and surrounding whitespace
            line="${line%$'\r'}"
            line="${line#"${line%%[![:space:]]*}"}"
            line="${line%"${line##*[![:space:]]}"}"

            [ -z "$line" ] && continue
            [[ "$line" == \#* ]] && continue
            [[ "$line" != *=* ]] && continue

            key="${line%%=*}"
            val="${line#*=}"
            key="${key#"${key%%[![:space:]]*}"}"
            key="${key%"${key##*[![:space:]]}"}"
            val="${val#"${val%%[![:space:]]*}"}"
            val="${val%"${val##*[![:space:]]}"}"

            case "$key" in
                TOKEN|CLOUDFLARE_TOKEN)  file_token="$val" ;;
                HOST|TUNNEL_HOST)        file_host="$val" ;;
                SESSION)                 file_session="$val" ;;
                *) ;;   # unknown keys ignored (forward-compatible)
            esac
        done < "$file"

        # Legacy fallback: bare-token file (no KEY=VALUE lines)
        if [ -z "$file_token" ] && [ -z "$file_host" ] && [ -z "$file_session" ]; then
            file_token=$(tr -d '\r\n' < "$file")
        fi
    fi

    # Resolve with source tracking
    if [ -n "$env_token" ]; then
        TUNNEL_TOKEN="$env_token"; TOKEN_SRC="env"
    elif [ -n "$file_token" ]; then
        TUNNEL_TOKEN="$file_token"; TOKEN_SRC="file"
    else
        TUNNEL_TOKEN=""; TOKEN_SRC="none"
    fi

    if [ -n "$env_host" ]; then
        TUNNEL_HOST="$env_host"; HOST_SRC="env"
    elif [ -n "$file_host" ]; then
        TUNNEL_HOST="$file_host"; HOST_SRC="file"
    else
        TUNNEL_HOST="$DEFAULT_HOST"; HOST_SRC="default"
    fi

    if [ -n "$env_session" ]; then
        SESSION="$env_session"; SESSION_SRC="env"
    elif [ -n "$file_session" ]; then
        SESSION="$file_session"; SESSION_SRC="file"
    else
        SESSION="$DEFAULT_SESSION"; SESSION_SRC="default"
    fi
}

require_cloudflared() {
    have_cmd "$CLOUDFLARED_BIN" \
        || die "cloudflared not found (pkg install cloudflared, or set CLOUDFLARED_BIN)"
}
require_tmux() {
    have_cmd tmux || die "tmux not found (pkg install tmux)"
}
require_token() {
    [ -n "$TUNNEL_TOKEN" ] \
        || die "no token — set TOKEN= in $TOKEN_FILE (run: ./cloudtunnel.sh config-help)"
}

# ------------------------------------------------------------------------------
# Commands
# ------------------------------------------------------------------------------
cmd_start() {
    local mode="foreground"
    while [ $# -gt 0 ]; do
        case "$1" in
            -b|--background) mode="background"; shift;;
            -f|--foreground) mode="foreground"; shift;;
            -h|--help)       echo "usage: cloudtunnel.sh start [-b|--background|-f|--foreground]"; return;;
            *)               die "start: unknown flag $1";;
        esac
    done

    require_cloudflared
    require_token

    if session_active; then
        die "tmux session '$SESSION' already running — use: ./cloudtunnel.sh attach  (or stop first)"
    fi
    if cloudflared_running; then
        die "cloudflared already running outside tmux — stop it first: ./cloudtunnel.sh stop"
    fi

    if [ "$mode" = "foreground" ]; then
        info "starting cloudflared in foreground (host: $TUNNEL_HOST, Ctrl-C to stop)"
        cmd__run
        return
    fi

    require_tmux
    info "starting cloudflared in tmux session '$SESSION' (host: $TUNNEL_HOST)"

    # `_run` reads the token from config inside the tmux child so the token
    # never appears in `ps` args of the launcher. cloudflared replaces the
    # shell via exec.
    tmux new-session -d -s "$SESSION" -c "$PROJECT_ROOT" \
        "exec '$SCRIPT_DIR/cloudtunnel.sh' _run"

    sleep 1.5
    if session_active; then
        echo ""
        echo "session:   $SESSION"
        echo "attach:    ./cloudtunnel.sh attach      (then Ctrl-B D to detach)"
        echo "status:    ./cloudtunnel.sh status"
        echo "logs:      ./cloudtunnel.sh logs"
        echo "stop:      ./cloudtunnel.sh stop"
    else
        die "tmux session died immediately — run: ./cloudtunnel.sh doctor"
    fi
}

cmd__run() {
    # Internal: called inside tmux; config is re-loaded by the child shell.
    require_cloudflared
    require_token
    exec "$CLOUDFLARED_BIN" tunnel run --token "$TUNNEL_TOKEN"
}

cmd_stop() {
    local killed=0
    if session_active; then
        tmux kill-session -t "$SESSION" 2>/dev/null && { info "tmux session '$SESSION' killed"; killed=1; }
    fi
    if cloudflared_running; then
        pkill -f "cloudflared.*tunnel run" 2>/dev/null && { info "cloudflared stopped"; killed=1; }
    fi
    [ "$killed" -eq 0 ] && info "nothing to stop"
    return 0
}

cmd_status() {
    echo "project root:   $PROJECT_ROOT"
    echo "token file:     $TOKEN_FILE"
    echo "session name:   $SESSION"

    if session_active; then
        echo "tmux session:   active"
    else
        echo "tmux session:   not running"
    fi

    if cloudflared_running; then
        local pid
        pid=$(pgrep -f "cloudflared.*tunnel run" | head -1)
        echo "cloudflared:    running (pid $pid)"
    else
        echo "cloudflared:    not running"
    fi

    if have_cmd curl; then
        local http
        http=$(curl -m "$CURL_TIMEOUT" -sS -o /dev/null -w '%{http_code}' "https://$TUNNEL_HOST/" 2>/dev/null || echo "ERR")
        printf 'hostname:       https://%s/ → %s\n' "$TUNNEL_HOST" "$http"
        case "$http" in
            200|204|301|302|304) ;;
            502)                 echo "                (tunnel up, local app not reachable — check localhost port)" ;;
            530|1033)            echo "                (no connector attached — cloudflared not running)" ;;
            1016)                echo "                (DNS points elsewhere)" ;;
        esac
    fi
    return 0
}

cmd_attach() {
    require_tmux
    session_active || die "no tmux session '$SESSION' — run: ./cloudtunnel.sh start -b"
    info "attaching to '$SESSION' (detach: Ctrl-B D)"
    exec tmux attach -t "$SESSION"
}

cmd_logs() {
    local n="40"
    if [ $# -gt 0 ]; then
        case "$1" in
            -h|--help) echo "usage: cloudtunnel.sh logs [N]"; return;;
            ''|*[!0-9]*) die "logs: N must be a positive integer";;
            *) n="$1";;
        esac
    fi
    require_tmux
    session_active || die "no tmux session '$SESSION' — run: ./cloudtunnel.sh start -b"
    tmux capture-pane -p -t "$SESSION" -S "-$n"
}

cmd_token() {
    echo "token:          $(mask_token "$TUNNEL_TOKEN")"
    if [ -n "$TUNNEL_TOKEN" ]; then
        local tid
        tid=$(decode_token_tunnel_id "$TUNNEL_TOKEN")
        echo "tunnel id:      ${tid:-<could not decode — token may be truncated or not a JWT>}"
    fi
}

cmd_config() {
    echo "token file:      $TOKEN_FILE"
    if [ -r "$TOKEN_FILE" ]; then
        local size mode
        size=$(wc -c < "$TOKEN_FILE" | tr -d ' ')
        mode=$(stat -c '%a' "$TOKEN_FILE" 2>/dev/null || echo "?")
        echo "  size:          $size bytes"
        echo "  perms:         $mode"
        case "$mode" in
            600|640|"") ;;
            *) echo "                 (recommend: chmod 600 $TOKEN_FILE)" ;;
        esac
    else
        echo "  status:        MISSING or unreadable"
    fi
    echo ""
    echo "effective configuration (env > file > default):"
    printf '  tunnel token:  %s  [source: %s]\n' "$(mask_token "$TUNNEL_TOKEN")" "$TOKEN_SRC"
    if [ -n "$TUNNEL_TOKEN" ]; then
        local tid
        tid=$(decode_token_tunnel_id "$TUNNEL_TOKEN")
        [ -n "$tid" ] && echo "  tunnel id:     $tid"
    fi
    printf '  tunnel host:   %s  [source: %s]\n' "$TUNNEL_HOST" "$HOST_SRC"
    printf '  tmux session:  %s  [source: %s]\n' "$SESSION" "$SESSION_SRC"
    echo ""
    echo "to edit:      \$EDITOR $TOKEN_FILE"
    echo "format help:  ./cloudtunnel.sh config-help"
}

cmd_config_help() {
    cat <<'EOF'
================================================================
Cloudflare tunnel config file format
================================================================

Location:  <project root>/token/.cloudflare_tunnel

This file holds the tunnel token and any deployment-specific
settings. Because it contains a secret, do NOT commit it to Git.
The shell script (cloudtunnel.sh) is safe to commit.

Permissions
-----------
  chmod 600 token/.cloudflare_tunnel

Syntax
------
  - One KEY=VALUE per line
  - Blank lines and lines starting with # are ignored
  - Leading and trailing whitespace is stripped from keys and values
  - Keys are case-sensitive; unknown keys are ignored
  - The FIRST '=' splits key from value; anything after stays in value

Keys
----
  TOKEN=<jwt>              Required. The cloudflared tunnel token, from
                           Zero Trust -> Networks -> Tunnels -> your
                           tunnel -> Configure tab. It's the long
                           eyJh... string after --token in the install
                           command shown there.
                           (CLOUDFLARE_TOKEN=<jwt> also accepted.)

  HOST=<hostname>          Optional. Public hostname served by this
                           tunnel, e.g. app.example.org. Used by
                           `status` and `doctor` reachability checks.
                           Default: app.example.org
                           (TUNNEL_HOST=<hostname> also accepted.)

  SESSION=<name>           Optional. tmux session name used by
                           `start -b`, `attach`, `logs`.
                           Default: tunnel

Legacy form
-----------
If the file contains only a bare token (no KEY=... lines), it is
treated as TOKEN=<contents>. Existing single-token files keep working.

Environment overrides
---------------------
Any value can be overridden with the same-named environment variable,
useful for CI or quick testing. The file is the primary source.

  CLOUDFLARE_TOKEN=eyJh...        ./cloudtunnel.sh start
  TUNNEL_HOST=staging.example.com ./cloudtunnel.sh status
  SESSION=staging                 ./cloudtunnel.sh start -b

Precedence:  environment  >  token file  >  built-in default

Example
-------
  # SAGE Cloudflare Tunnel
  TOKEN=eyJhIjoi...long_jwt_here...
  HOST=app.example.org
  SESSION=tunnel

Related commands
----------------
  ./cloudtunnel.sh config        show effective config and sources
  ./cloudtunnel.sh token         decode + mask the token
  ./cloudtunnel.sh doctor        full dependency + reachability check
EOF
}

cmd_doctor() {
    echo "script dir:      $SCRIPT_DIR"
    echo "project root:    $PROJECT_ROOT"
    echo "load_root.sh:    $([ -f "$SCRIPT_DIR/load_root.sh" ] && echo "present" || echo "not found (using fallback)")"
    echo "db_name.sh:      $([ -x "$SCRIPT_DIR/db_name.sh" ] && echo "present" || echo "not found")"
    echo "mysql args:      $([ -n "$MYSQL_ARGS" ] && echo "loaded" || echo "not loaded")"
    echo "token file:      $TOKEN_FILE"
    if [ -r "$TOKEN_FILE" ]; then
        local mode
        mode=$(stat -c '%a' "$TOKEN_FILE" 2>/dev/null || echo "?")
        echo "token perms:     $mode$([ "$mode" != "600" ] && [ "$mode" != "640" ] && echo "  (recommend: chmod 600)")"
    fi
    echo "tunnel host:     $TUNNEL_HOST  [source: $HOST_SRC]"
    echo "session name:    $SESSION  [source: $SESSION_SRC]"

    if [ -n "$TUNNEL_TOKEN" ]; then
        echo "token:           $(mask_token "$TUNNEL_TOKEN")  [source: $TOKEN_SRC]"
        local tid
        tid=$(decode_token_tunnel_id "$TUNNEL_TOKEN")
        [ -n "$tid" ] && echo "tunnel id:       $tid"
    else
        echo "token:           MISSING  [source: $TOKEN_SRC]"
    fi

    for d in "$CLOUDFLARED_BIN" tmux python3 curl; do
        if have_cmd "$d"; then
            printf 'dep:             %s ✓\n' "$d"
        else
            case "$d" in
                cloudflared) echo "dep:             $d ✗ (pkg install cloudflared, or set CLOUDFLARED_BIN)";;
                tmux)        echo "dep:             $d ✗ (pkg install tmux) — required for -b mode";;
                python3)     echo "dep:             $d ✗ — only needed for token decoding";;
                curl)        echo "dep:             $d ✗ — only needed for reachability check";;
            esac
        fi
    done

    if have_cmd curl; then
        echo ""
        echo "checking https://$TUNNEL_HOST/ …"
        local http
        http=$(curl -m "$CURL_TIMEOUT" -sS -o /dev/null -w '%{http_code}' "https://$TUNNEL_HOST/" 2>/dev/null || echo "ERR")
        echo "  status:        $http"
    fi
}

cmd_help() {
    cat <<'EOF'
cloudtunnel.sh — SAGE Cloudflare Tunnel Runner

usage: ./cloudtunnel.sh <command> [args...]

commands:
  start [-b|--background]
        Run cloudflared in the foreground (default, Ctrl-C to stop)
        or in a detached tmux session named after SESSION.

  stop
        Kill the tmux session (if any) and any running cloudflared.

  status
        Show tmux session state, cloudflared pid, and the tunnel
        hostname's HTTP status.

  attach
        tmux attach to the running session (detach: Ctrl-B then D).

  logs [N]
        Print the last N lines from the tmux pane (default 40).

  token
        Print the token (masked) and the tunnel id decoded from it.

  config
        Show effective configuration and where each value came from
        (env, file, or built-in default). Recommend `chmod 600` on
        the token file if permissions are too open.

  config-help
        Documentation for the token file format (keys, syntax,
        environment overrides, examples).

  doctor
        Full diagnostic: paths, token, permissions, dependencies,
        and a live reachability check against the hostname.

  help | version

Files
-----
  token/.cloudflare_tunnel    tunnel token + HOST + SESSION
                              (do NOT commit; chmod 600)

Environment (all optional; file is primary)
------
  CLOUDFLARE_TOKEN   override TOKEN from file
  TUNNEL_HOST        override HOST from file
  SESSION            override SESSION from file
  CLOUDFLARED_BIN    path to cloudflared (default: cloudflared)
  CURL_TIMEOUT       reachability curl timeout (default: 10)
EOF
}

cmd_version() { echo "cloudtunnel.sh 0.2.0"; }

# ==============================================================================
# Dispatch
# ==============================================================================
main() {
    local cmd="${1:-help}"; shift || true
    case "$cmd" in
        start)          cmd_start "$@";;
        _run)           cmd__run "$@";;      # internal, used by tmux
        stop)           cmd_stop "$@";;
        status)         cmd_status "$@";;
        attach)         cmd_attach "$@";;
        logs)           cmd_logs "$@";;
        token)          cmd_token "$@";;
        config)         cmd_config "$@";;
        config-help)    cmd_config_help "$@";;
        doctor|diag)    cmd_doctor "$@";;
        version|-V|--version) cmd_version;;
        help|-h|--help) cmd_help;;
        *)              die "unknown command '$cmd' (run: cloudtunnel.sh help)";;
    esac
}

# Load token + config before dispatch so every command sees resolved values
_load_config "$TOKEN_FILE"

main "$@"