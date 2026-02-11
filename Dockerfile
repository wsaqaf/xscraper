# Dockerfile for Xscraper
FROM python:3.11

# Install system dependencies including Apache and PHP
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-wsgi-py3 \
    php \
    libapache2-mod-php \
    php-cli \
    && rm -rf /var/lib/apt/lists/*

# Set working directory to the standard Apache root
WORKDIR /var/www/html

# Copy requirements and install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application files
COPY . .

# Create upload folder and set wide permissions for the web user
RUN mkdir -p /var/www/html/UPLOAD_FOLDER && \
    chmod -R 777 /var/www/html/UPLOAD_FOLDER && \
    chown -R www-data:www-data /var/www/html

# Copy Apache configuration
COPY xscraper.conf /etc/apache2/sites-available/xscraper.conf
RUN a2dissite 000-default.conf && \
    a2ensite xscraper.conf && \
    a2enmod rewrite

# --- THE FIX: DYNAMICALLY FIND PHP CONFIG PATH ---
# This locates the actual PHP config directory and writes the limits there
RUN PHP_CONF_DIR=$(php -i | grep "Scan this dir for additional .ini files" | cut -d" " -f5) && \
    echo "upload_max_filesize=100M" > ${PHP_CONF_DIR}/uploads.ini && \
    echo "post_max_size=100M" >> ${PHP_CONF_DIR}/uploads.ini && \
    echo "memory_limit=256M" >> ${PHP_CONF_DIR}/uploads.ini

EXPOSE 80

CMD ["apachectl", "-D", "FOREGROUND"]
