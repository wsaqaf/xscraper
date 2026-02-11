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
RUN DIR_PATH=$(php -i | grep "Scan this dir for additional .ini files" | cut -d" " -f5) && \
    mkdir -p "$DIR_PATH" && \
    echo "upload_max_filesize=100M\npost_max_size=100M\nmemory_limit=256M" > "$DIR_PATH/uploads.ini"

RUN mkdir -p /var/www/html/UPLOAD_FOLDER && \
    chown -R www-data:www-data /var/www/html/UPLOAD_FOLDER && \
    chmod -R 777 /var/www/html/UPLOAD_FOLDER

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]
