# Dockerfile for Xscraper

# Use official Python image with Apache
FROM python:3.11

# Install system dependencies including Apache and PHP
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

# Set working directory inside the container
WORKDIR /app

# Create upload folder and set permissions
RUN mkdir -p /app/UPLOAD_FOLDER && \
    chmod 777 /app/UPLOAD_FOLDER

# Copy only requirements.txt first to leverage Docker caching
COPY requirements.txt /app/requirements.txt

# Install Python dependencies
RUN pip install --no-cache-dir -r /app/requirements.txt

# Now copy the rest of the application files
COPY . /app

# Copy Apache configuration file to the correct directory
COPY xscraper.conf /etc/apache2/sites-available/xscraper.conf

# Ensure correct permissions for Apache config
RUN chmod 644 /etc/apache2/sites-available/xscraper.conf

# Set correct permissions for web server
RUN chown -R www-data:www-data /app && \
    chmod -R 755 /app

# Enable required Apache modules
RUN a2enmod rewrite
RUN a2enmod php8.2 || a2enmod php8.1 || a2enmod php8.0 || a2enmod php7.4 || echo "No PHP module found"

# Remove default site and enable our site
RUN a2dissite 000-default.conf
RUN a2ensite xscraper.conf

# Expose port 80 for web access
EXPOSE 80

# Start Apache in the foreground
CMD ["apachectl", "-D", "FOREGROUND"]