# Xscraper

Xscraper is a web-based tool designed to process HTTP Archive (HAR) files, extract tweet-related data, and provide structured output in CSV format. The system runs using Python and PHP with Apache, making it accessible via a web interface.

## Features
- Upload HAR files and extract tweets and user information.
- Store extracted data in CSV format.
- Web-based interface for viewing and downloading processed data.
- Fully containerized with Docker for easy deployment.

---

## 🚀 Deployment Options
Xscraper can be deployed in two ways:

### **Option 1: Manual Installation**
If you want to run Xscraper without Docker, follow these steps:

1. **Clone the repository:**
   ```sh
   git clone https://github.com/wsaqaf/xscraper.git
   cd xscraper
   ```

2. **Install required dependencies:**
   - **System Packages:**
     ```sh
     sudo apt update && sudo apt install -y apache2 libapache2-mod-wsgi-py3 php-cli php python3-pip
     ```
   - **Python Libraries:**
     ```sh
     pip install -r requirements.txt
     ```

3. **Configure Apache & WSGI:**
   ```sh
   sudo cp xscraper.conf /etc/apache2/sites-available/xscraper.conf
   sudo a2ensite xscraper
   sudo systemctl restart apache2
   ```

4. **Access the app:**
   Open a browser and visit:
   ```
   http://localhost
   ```

---

### **Option 2: Deploy with Docker (Recommended)**
This is the **easiest way** to set up Xscraper.

1. **Clone the repository:**
   ```sh
   git clone https://github.com/wsaqaf/xscraper.git
   cd xscraper
   ```

2. **Run Xscraper using Docker Compose:**
   ```sh
   docker-compose up -d
   ```

3. **Access the app:**
   Open a browser and visit:
   ```
   http://localhost:8080
   ```

---

## 📁 File Structure
```
Xscraper/
│── Dockerfile         # Defines the Docker container
│── docker-compose.yml # Simplifies deployment with Docker
│── xscraper.py        # Python script to process HAR files
│── index.php          # Web interface for uploading files
│── view_tweets.php    # Displays processed data
│── xscraper.conf      # Apache configuration for deployment
│── xscraper.wsgi      # WSGI entry point for running Python app
│── UPLOAD_FOLDER/     # Stores uploaded files and processed data
│── README.md          # Documentation
```

---

## 📄 License
This project is licensed under the MIT License.

## 🤝 Contributors
- **Walid Saqaf** (walid[@]al-saqaf.se)

For any issues or feature requests, feel free to submit a pull request or open an issue on [GitHub](https://github.com/wsaqaf/xscraper).
