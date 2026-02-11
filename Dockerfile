# Dockerfile for Xscraper
# Based on Python 3.11 with Apache and PHP integration
FROM python:3.11

# 1. Install system dependencies including Apache and PHP
# [cite_start]We include php-cli and libapache2-mod-php for web execution [cite: 9]
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-wsgi-py3 \
    php \
    libapache2-mod-php \
    php-cli \
    php-mbstring \
    php-xml \
    php-curl \
    php-gd \
    php-zip \
    && rm -rf /var/lib/apt/lists/*

# 2. Set working directory to the standard Apache root
# [cite_start]This ensures consistency between container paths and Docker volumes [cite: 9]
WORKDIR /var/www/html

# 3. Handle Python dependencies
# [cite_start]Copy requirements first to leverage Docker layer caching [cite: 9]
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 4. Copy the application files
COPY . .

# 5. Setup the UPLOAD_FOLDER with correct permissions
# [cite_start]chmod 777 is used to allow the web server user (www-data) to write files [cite: 9]
RUN mkdir -p /var/www/html/UPLOAD_FOLDER && \
    chmod -R 777 /var/www/html/UPLOAD_FOLDER && \
    chown -R www-data:www-data /var/www/html

# 6. Configure Apache
# [cite_start]Copy and enable the custom site configuration [cite: 9]
COPY xscraper.conf /etc/apache2/sites-available/xscraper.conf
RUN a2dissite 000-default.conf && \
    a2ensite xscraper.conf && \
    a2enmod rewrite

# 7. FIXED PHP CONFIGURATION BLOCK
# This ensures the directory exists and overrides the default 2MB upload limit.
# We target the common Debian PHP config path used by libapache2-mod-php.
RUN mkdir -p /etc/php/8.2/apache2/conf.d/ && \
    echo "upload_max_filesize=100M" > /etc/php/8.2/apache2/conf.d/uploads.ini && \
    echo "post_max_size=100M" >> /etc/php/8.2/apache2/conf.d/uploads.ini && \
    echo "memory_limit=256M" >> /etc/php/8.2/apache2/conf.d/uploads.ini

# 8. Final Environment Setup
EXPOSE 80

# [cite_start]Start Apache in the foreground to keep the container running [cite: 9]
CMD ["apachectl", "-D", "FOREGROUND"]
