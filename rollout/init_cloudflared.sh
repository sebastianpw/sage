#!/bin/bash

cd /var/www/sage

# Download the official Cloudflare package
curl -LO https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb

# Install the package
dpkg -i cloudflared-linux-amd64.deb

