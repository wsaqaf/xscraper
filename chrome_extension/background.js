let masterTweets = {};
let masterUsers = {};
let crawlQueue = [];
let isCrawling = false;
let sourceWindowId = null;
let currentChildTabId = null;
let crawlTimeout = null;

chrome.tabs.onRemoved.addListener((tabId) => {
    if (tabId === currentChildTabId) {
        console.log("X Scraper: Child tab " + tabId + " closed. Moving to next.");
        clearTimeout(crawlTimeout);
        currentChildTabId = null;
        setTimeout(processNext, 200);
    }
});

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'appendData') {
        const { tweets, users } = request;
        for (const tId in tweets) {
            if (!masterTweets[tId]) {
                masterTweets[tId] = tweets[tId];
            } else {
                for (const key in tweets[tId]) {
                    if (tweets[tId][key] !== null && tweets[tId][key] !== undefined && tweets[tId][key] !== "") {
                        masterTweets[tId][key] = tweets[tId][key];
                    }
                }
            }
        }
        for (const uId in users) {
            if (!masterUsers[uId]) {
                masterUsers[uId] = users[uId];
            } else {
                for (const key in users[uId]) {
                    if (users[uId][key] !== null && users[uId][key] !== undefined && users[uId][key] !== "") {
                        masterUsers[uId][key] = users[uId][key];
                    }
                }
            }
        }
        if (sendResponse) sendResponse({ success: true });
        return;
    }
    if (request.action === 'addToQueue') {
        crawlQueue.push(...request.items);
        if (!currentChildTabId) {
            processNext();
        }
        if (sendResponse) sendResponse({ success: true });
        return;
    }
    if (request.action === 'childDone') {
        if (sender.tab) {
            console.log("X Scraper: Child tab " + sender.tab.id + " reported done.");
            chrome.tabs.remove(sender.tab.id, () => {
                if (chrome.runtime.lastError) {
                    // Already closed
                }
            });
            // If this was our tracked tab, clear it immediately
            if (sender.tab.id === currentChildTabId) {
                clearTimeout(crawlTimeout);
                currentChildTabId = null;
                setTimeout(processNext, 200);
            }
        }
        return;
    }
    if (request.action === 'getMasterData') {
        sendResponse({ tweets: masterTweets, users: masterUsers });
        return true;
    }
    if (request.action === 'clearMasterData') {
        masterTweets = {};
        masterUsers = {};
        crawlQueue = [];
        isCrawling = false;
        if (currentChildTabId) {
            chrome.tabs.remove(currentChildTabId, () => { if (chrome.runtime.lastError) {} });
        }
        currentChildTabId = null;
        sourceWindowId = null;
        return;
    }
    if (request.action === 'setSourceWindow') {
        sourceWindowId = request.windowId;
        if (sendResponse) sendResponse({ success: true });
        return;
    }
    if (request.action === 'getCrawlStatus') {
        sendResponse({ 
            isCrawling: !!currentChildTabId || (crawlQueue.length > 0 && isCrawling), 
            queueLength: crawlQueue.length, 
            tweetCount: Object.keys(masterTweets).length, 
            userCount: Object.keys(masterUsers).length 
        });
        return true;
    }
});

function processNext() {
    if (crawlQueue.length === 0) {
        isCrawling = false;
        currentChildTabId = null;
        console.log("X Scraper: Crawl queue empty.");
        return;
    }
    
    // If already crawling a tab, don't start another
    if (currentChildTabId) return;

    isCrawling = true;
    const item = crawlQueue.shift();
    
    // Placeholder ID to prevent race conditions during tab creation
    currentChildTabId = -1; 

    // Set parameters for the next tab
    chrome.storage.local.set({ 
        autoScrapePages: 3, 
        autoScrapeDepth: item.depth,
        isAutoCrawlChild: true,
        autoScrapeHidden: item.includeHidden
    }, () => {
        chrome.tabs.create({ 
            url: item.url, 
            active: false,
            windowId: sourceWindowId 
        }, (tab) => {
            currentChildTabId = tab.id;
            console.log("X Scraper: Created child tab " + tab.id + " for " + item.url);
            
            // Safety timeout: 45 seconds to finish one thread
            clearTimeout(crawlTimeout);
            crawlTimeout = setTimeout(() => {
                if (currentChildTabId === tab.id) {
                    console.log("X Scraper: Thread timeout on tab " + tab.id + ". Skipping...");
                    currentChildTabId = null;
                    chrome.tabs.remove(tab.id, () => { if (chrome.runtime.lastError) {} });
                    processNext();
                }
            }, 45000);
        });
    });
}
