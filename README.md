# Xscraper

Xscraper is an advanced tool designed to process HTTP Archive (HAR) files from X (formerly Twitter). It extracts rich tweet metadata and detailed user profiles, converting messy network traffic into structured, research-ready CSV datasets.

## ✨ Key Features
- **Intelligent Extraction**: Automatically captures account creation dates, all links within user bios, and account-level geo-location permissions.
- **Rich Tweet Metadata**: Parses hashtags, user mentions, expanded URLs, blue verification status, and engagement metrics (replies, retweets, likes).
- **Geographic Precision**: Extracts tweet-specific coordinates (longitude/latitude) and detailed place information (country, city type) when available.
- **Preserved Filenames**: Output CSVs are automatically named based on your original HAR file for easy dataset management.
- **Integrated Viewer**: Built-in web interface to visualize your data in searchable, sortable tables before downloading.
- **Fully Containerized**: Optimized for Docker with auto-configured Apache and PHP settings for handling large HAR files.

---

## 🚀 Getting Started

### **Option 1: Deploy with Docker (Recommended)**
The fastest way to get Xscraper running.

1. **Clone and Enter the Repo:**
```sh
   git clone [https://github.com/wsaqaf/xscraper.git](https://github.com/wsaqaf/xscraper.git)
   cd xscraper

```

2. **Launch the Container:**
```sh
docker-compose up -d

```


3. **Access Xscraper:**
Visit `http://localhost:8080` in your browser.

---

### **Option 2: Manual Installation**

For users preferring a local system installation.

1. **Install System Dependencies:**
* **Debian/Ubuntu**: `sudo apt update && sudo apt install apache2 php python3-pip libapache2-mod-wsgi-py3`


2. **Install Python Libraries:**
```sh
pip install -r requirements.txt

```


3. **Configure Web Server:**
Update your Apache configuration to use `xscraper.conf` and ensure permissions for the `UPLOAD_FOLDER` are set to `777`.

---

## 🛠 Usage & Hybrid Interaction

Xscraper operates in a **Hybrid Mode**, allowing you to choose the interface that best fits your workflow.

### **1. Web Interface (GUI)**

Ideal for individual file processing and immediate data visualization.

* Upload your `.har` file through the main dashboard.
* Once processed, use the **"View Table"** feature to inspect data or click **"Download"** to save the CSVs.

### **2. Command Line Interface (CLI)**

Ideal for power users, developers, or automation.

* Run the extraction engine directly from your terminal:
```sh
python3 xscraper.py <path_to_your_file>.har

```


* The script will output the result JSON and save the two corresponding CSV files directly into the `UPLOAD_FOLDER`.

---

## 📊 Data Fetching Protocol

1. Open X and search for your target query.
2. Open **Developer Tools** (F12) > **Network** tab.
3. Use the included [scrolldown-automatically.js](https://www.google.com/search?q=scrolldown-automatically.js) by pasting it into the **Console** tab to dynamically fetch tweets.
4. Once finished, right-click any network request and select **"Save all as HAR with content"**.

### **Output Generation**

Regardless of the mode used, the system generates two distinct datasets:

* `{YourFileName}_tweets_{timestamp}.csv`
* `{YourFileName}_users_{timestamp}.csv`

---

## 📁 File Structure

* `xscraper.py`: The core extraction engine (Python).
* `index.php`: The main upload and processing interface.
* `view_tweets.php`: Advanced dataset visualization tool.
* `xscraper.wsgi/conf`: Integration layers for Apache.
* `UPLOAD_FOLDER/`: Secure storage for your datasets.

---

## 📄 License

This project is licensed under the MIT License.

## 🤝 Contributors

* **Walid Saqaf** ([walid@al-saqaf.se](mailto:walid@al-saqaf.se))

For feature requests or issues, please open a pull request on the [GitHub repository](https://github.com/wsaqaf/xscraper).
