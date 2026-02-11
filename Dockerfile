# Dockerfile for Xscraper
FROM python:3.11

# 1. Install system dependencies
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-wsgi-py3 \
    php \
    libapache2-mod-php \
    php-cli \
    && rm -rf /var/lib/apt/lists/*

# 2. THE PERMISSIONS FIX: Match container user to host user
# This prevents 403 Forbidden errors when using volumes
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# 3. Set working directory
WORKDIR /var/www/html

# 4. Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 5. Copy files and set initial ownership
COPY . .
RUN chown -R www-data:www-data /var/www/html

# 6. Configure Apache
COPY xscraper.conf /etc/apache2/sites-available/xscraper.conf
RUN a2dissite 000-default.conf && \
    a2ensite xscraper.conf && \
    a2enmod rewrite

# 7. PHP Configuration for large files
RUN for dir in /etc/php/8.2/apache2/conf.d/ /etc/php/8.1/apache2/conf.d/ /etc/php/8.0/apache2/conf.d/ /etc/php/7.4/apache2/conf.d/; do \
        if [ -d "$dir" ]; then \
            echo "upload_max_filesize=100M" > "$dir/99-overrides.ini" && \
            echo "post_max_size=110M" >> "$dir/99-overrides.ini" && \
            echo "memory_limit=512M" >> "$dir/99-overrides.ini" && \
            echo "max_execution_time=300" >> "$dir/99-overrides.ini"; \
        fi \
    done
    
RUN mkdir -p /var/www/html/UPLOAD_FOLDER && \
    chown -R www-data:www-data /var/www/html/UPLOAD_FOLDER && \
    chmod -R 777 /var/www/html/UPLOAD_FOLDER

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]
