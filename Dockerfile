FROM webdevops/php-nginx:8.3

# Set your environment variables
ENV WEB_DOCUMENT_ROOT=/var/www/sage/public
ENV WEB_DOCUMENT_INDEX=index.php
ENV PHP_DISPLAY_ERRORS=1
ENV PHP_MEMORY_LIMIT=512M
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/sage

# Copy all your code into the image
COPY . /var/www/sage

# 1. Run Composer
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# 2. Setup a dummy .env.local for the build process so init_docker.sh doesn't fail
#RUN cp .env.example .env.local || true

# 3. Run the massive init script! (Using echo "YES" to bypass the safety prompt)
RUN echo "YES" | /bin/bash /var/www/sage/rollout/init_docker.sh

# 4. Stop MariaDB cleanly so the populated data is safely committed into the image layer
RUN pkill -f mariadbd || true && sleep 3

# 5. Make sure the new Docker restart script is executable
RUN chmod +x /var/www/sage/bash/restart_docker.sh

# 6. Start the Docker-specific boot script!
CMD ["/var/www/sage/bash/restart_docker.sh"]
