# X Scraper Extension

A Chrome Extension for seamlessly extracting Tweets and Users directly from `x.com` / `twitter.com`. 

Unlike traditional methods that require downloading and parsing HAR files offline, this extension intercepts X's network requests live as you scroll. It effectively rebuilds the exact same data model as the backend `xscraper.php` framework (which this extension is based off of), pulling extensive interaction metrics without the hassle of downloading JSON archives locally.

## Features
- **Live Network Interception:** Runs passively in the background of your active X tab, capturing `TimelineAddEntries` blocks continuously.
- **Real-time Progress Tracking:** See live counts of how many unique tweets and users have been scraped so far directly in the extension popup while it scrolls.
- **Set Your Scrolling Speed:** Input exactly how many pages you want to retrieve. The extension autonomously scrolls to the bottom of the feed at timed increments (2.5 seconds) allowing lazy-loading elements to yield fully populated JSON trees.
- **Smart Naming Convention:** Identifies if you are on a profile (e.g., `/userx`) or searching (via `?q=testing...`), dynamically naming your resulting files `userx_tweets...`, `userx_data...`, etc., to automatically sort your data collections.
- **In-Memory Transformation:** Extracts more than 60 data columns cleanly avoiding external backend servers, completely isolating operations for security.
- **1-Click Generation:** Auto-constructs and pushes three discrete downloads directly from your browser: 
  - `tweets.csv` (contains raw texts, interactions, user mappings, hashtags, quote identifiers, and verified status)
  - `users.csv` (contains comprehensive account metrics like lists, followers, image URLs, and account creation dates)
  - `data.json` (a unified JSON object containing all scraped tweet and user objects)

## Installation Guide (For Development/Unpacked Mode)

As this is currently an unpacked local extension, you need to sideload it onto Chrome manually:

1. Clone or download this repository to a directory on your computer:
   ```bash
   git clone https://github.com/wsaqaf/xscraper.git
   ```
   *Note: If you already downloaded it as a ZIP, simply extract the folder to an accessible location like your Desktop.*
2. Open Google Chrome.
3. In your URL bar, navigate to **`chrome://extensions/`**.
4. In the top-right corner of the Extensions dashboard, toggle on **Developer mode**.
5. Click the **Load unpacked** button that appears in the top-left menu.
6. A selection window will appear. Select the exact `chrome_extension` folder located inside the cloned `xscraper` directory.
7. The extension will now appear as an active card on your dashboard, accompanied by an X Scraper icon in your browser toolbar!

## How to Use

1. Navigate to **[x.com](https://x.com)** or **[twitter.com](https://twitter.com)**.
2. Go to your desired target. This can be:
   - A search results timeline (e.g., querying for `#OpenAI`)
   - A specific user profile page
   - A trending topic feed
3. Click the **X Scraper Extension icon** in the top Chrome toolbar.
4. Input the number of "Pages to scroll". (e.g., `5`).
5. Click **Start Scraping**.
6. The extension will begin to automatically jump down the page, waiting a few seconds on each chunk for data to register. *Please do not navigate away or close the tab while this runs.*
7. Once finished, a small overlay widget will appear in the bottom-left corner of the webpage alongside an updated pop-up summarizing the data.
8. Click **Download Tweets**, **Download Users**, and/or **Download JSON** to obtain your cleanly formatted datasets natively to your computer.

## Known Limitations / Troubleshooting

*   **Pacing Constraints**: Scraping is an automated scroll that triggers X's standard graph load requests. If X throttles or delays the feed rendering, data chunks missing from the viewport during the jump sequence are skipped.
*   **Context Scope Limitations**: Since it relies on interception locally via `postMessage()`, reloading the page or switching tabs violently could disrupt the injection scripts tracking array.

## Credits & License

Ported natively from [wsaqaf/xscraper](https://github.com/wsaqaf/xscraper) PHP backend to Vanilla JS for browser implementation. 

## Author
**Dr. Walid Al-Saqaf**  
Email: walid[@]al-saqaf.se
