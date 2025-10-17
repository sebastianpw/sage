#!/bin/bash

# Add custom bash configuration to root's .bashrc
cat >> ~/.bashrc << 'EOF'

# Prevent double-execution
if [[ "$1" == "force" ]]; then
  unset BASHRC_ALREADY_RUN
fi

if [[ -z "$BASHRC_ALREADY_RUN" ]]; then
  export BASHRC_ALREADY_RUN=1
  export SHELL=/bin/bash
  
  # Colorized ls
  export LS_OPTIONS='--color=auto'
  eval "$(dircolors)"
  
  # Aliases
  alias ls='ls $LS_OPTIONS'
  alias ll='ls $LS_OPTIONS -ltr'
  alias l='ls $LS_OPTIONS'
  
  # Safety aliases
  alias rm='rm -i'
  alias cp='cp -i'
  alias mv='mv -i'
fi
EOF

# setup db
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bash "$SCRIPT_DIR/rollout/init_db.sh"

echo "Container setup complete!"

# Start the original entrypoint
exec /entrypoint supervisord
