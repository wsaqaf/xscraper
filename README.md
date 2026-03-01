# X Scraper

A lightweight, two-in-one data extraction tool. It allows you to scrape comprehensive Tweet and User metrics directly from X (formerly Twitter) using two distinct methods: 
1. **A native Chrome Extension** (Live browser scraping)
2. **A PHP Web Engine** (Offline HAR file processing)

*Note: This repository replaces the older python-based scraper, which has been moved and archived under `xscraper-py`.*

## Data Extracted
Both engines mirror an identical database extraction script capable of extracting 60+ parameters without relying on the official X API. 

This includes variables such as:
- **Interactions:** View count, Retweets, Favorites, Replies, Video Views
- **Media & Links:** Direct video/image CDN URLs, expanded external links
- **Account Data:** User IDs, follower/following counts, profile URLs, bio statements, creation dates, geographic coordinates.
- **Verification mapping:** Accurately maps both Legacy (Checkmark) and Blue verified metrics natively.

## Option 1: Live Chrome Extension (Recommended)
You can directly scrape tweets and user accounts dynamically by scrolling down an X feed, without requiring offline tools or HAR file downloads.

### Setup Guide
1. Go to your `chrome://extensions/` page in Google Chrome.
2. Toggle on **Developer mode** in the top-right corner.
3. Click **Load unpacked** and select the `/chrome_extension` folder located inside this repository.
4. Go to x.com (a profile, hashtag, or search query) and click the extension icon.
5. Enter the number of pages you want to scroll and click **Start**.

The extension will auto-scroll the webpage, fetching the API responses as you go. When complete, an overlay will pop up allowing you to instantly download cleanly formatted `tweets.csv`, `users.csv`, and a combined `data.json` file.
*(See `chrome_extension/README.md` for more details).*

---

## Option 2: PHP & Web Interface (HAR Processing)
If you prefer handling raw network requests, you can save `.har` (HTTP Archive) files from Chrome's Network inspector and drop them into a graphical PHP web application. 

### Setup Guide
Requires a local web server (like Apache/XAMPP or MAMP on macOS/Ubuntu) running PHP. 

1. Ensure the `xscraper` directory is placed inside your server's root folder (e.g., `htdocs` or `/var/www/html`).
2. Navigate to `http://localhost/xscraper/index.php`.
3. Drop a `.har` file exported from Google Chrome into the UI.
4. The PHP script (`xscraper.php`) will aggressively parse the JSON structures from the HAR entries, creating compiled `.csv` and `.json` documents natively into the `UPLOAD_FOLDER/` directly on your server.
5. In the UI, click to instantly view or download the rendered CSV or JSON contents.

### PHP Files Overview
- `xscraper.php` - The core parsing logical engine. Iterates deeply through GraphQL and instruction schemas to build identical data structures.
- `index.php` - A clean, simple Bootstrap frontend UI for uploading files and checking progress.
- `view_tweets.php` - A browser-based CSV parsing tool allowing you to rapidly scan your offline data tables.
- `UPLOAD_FOLDER/` - A protected staging ground where your generated CSV and JSON datasets will be natively placed dynamically by PHP. 

## Warning / Fair Use
This script strictly mimics passive reading metrics for academic and research collection parameters. When using the Chrome Extension, X enforces natural API rate-limiting via scrolling behaviors, so extended infinite scrolls can intermittently be delayed or paused by X. 

## License
MIT License. Feel free to clone or modify the engine paths according to your schema needs!

## Author
**Dr. Walid Al-Saqaf**  
Email: walid[@]al-saqaf.se
