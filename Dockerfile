# Dockerfile for Xscraper

# Use official Python image with Apache
FROM python:3.9

# Install system dependencies including Apache and PHP
RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-wsgi-py3 \
    php-cli \
    php \
    php-mbstring \
    php-xml \
    php-curl \
    php-gd \
    php-zip \
    && rm -rf /var/lib/apt/lists/*

# Set working directory inside the container
WORKDIR /app

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

# Enable Apache site configuration and disable the default site
RUN a2ensite xscraper.conf && a2dissite 000-default.conf

# Ensure required Apache modules are enabled
RUN a2enmod wsgi

# Expose port 80 for web access
EXPOSE 80

# Start Apache in the foreground
CMD ["apachectl", "-D", "FOREGROUND"]