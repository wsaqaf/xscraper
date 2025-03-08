<<<<<<< HEAD
# harprocessor
Processes HAR files obtained from scrolling X
=======
# Xscraper

Xscraper is a tool for processing HTTP Archive (HAR) files to extract and analyze tweets, users, and interactions. It provides a web-based interface using PHP and Apache, combined with Python for backend processing. The extracted data can be downloaded as CSV files or viewed in a structured table.

## Features
- Upload HAR files and process tweet data
- Extract detailed tweet metadata (likes, retweets, replies, quotes, media, etc.)
- Store extracted tweets and users in structured CSV format
- View extracted data via a web interface
- Support for Docker deployment

## Prerequisites
- **System Dependencies**:
  - Python 3.9+
  - Apache2
  - mod_wsgi
  - PHP
  - php-cli
  
- **Python Libraries**:
  Install required dependencies with:
  ```sh
  pip install -r requirements.txt
  ```

## Installation
### 1. Clone the Repository
```sh
git clone git@github.com:wsaqaf/xscraper.git
cd xscraper
```

### 2. Setup Apache & WSGI Configuration
Modify the Apache configuration file to include:
```
<VirtualHost *:80>
    ServerName YOUR_SERVER_NAME

    WSGIDaemonProcess xscraper python-path=/var/www/xscraper:/var/www/xscraper/venv/lib/python3.x/site-packages
    WSGIProcessGroup xscraper
    WSGIScriptAlias / /var/www/xscraper/xscraper.wsgi

    <Directory /var/www/xscraper>
        Require all granted
    </Directory>
</VirtualHost>
```
Restart Apache:
```sh
sudo systemctl restart apache2
```

### 3. Run with Docker
Alternatively, you can deploy Xscraper using Docker:
```sh
docker build -t xscraper .
docker run -p 8080:80 xscraper
```

## Usage
1. Access the web interface: `http://localhost:8080`
2. Upload a HAR file containing Twitter data.
3. The system processes the file and extracts:
   - Tweets (`tweets_*.csv`)
   - Users (`users_*.csv`)
4. View extracted data in the web interface or download CSVs.

## File Structure
```
Xscraper/
│── xscraper/         # Python processing script
│── web/              # PHP-based web interface
│── config/           # Apache configuration
│── static/           # CSS, JavaScript
│── requirements.txt  # Python dependencies
│── Dockerfile        # Containerization setup
│── xscraper.wsgi     # WSGI entry point
│── README.md         # Documentation
```

## Contributors
- **Walid Saqaf**

## License
This project is licensed under the MIT License.

>>>>>>> 0e616ee (Initial commit for HAR Processor)
