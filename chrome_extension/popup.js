// Check if already scraping on load
chrome.tabs.query({ active: true, currentWindow: true }, ([tab]) => {
    if (tab && (tab.url.includes('x.com') || tab.url.includes('twitter.com'))) {
        chrome.tabs.sendMessage(tab.id, { action: 'getProgress' }, (res) => {
            if (chrome.runtime.lastError) {
                // Connection might be momentarily lost due to reload
                return;
            }
            if (res && res.isScraping) {
                document.getElementById('controls').classList.add('hidden');
                document.getElementById('status').classList.remove('hidden');
                document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
                document.getElementById('liveStatsText').innerText = `Tweets: ${res.tweetCount || 0} | Users: ${res.userCount || 0}`;
                startProgressChecker(tab.id);
            } else if (res) {
                if (res.count > 0 || res.lastResult) {
                    document.getElementById('refreshPage').checked = false;
                }
                if (res.lastResult) {
                    showResults(res.lastResult, true);
                }
            }
        });
    }
});

let checkProgress;

function startProgressChecker(tabId) {
    if (checkProgress) clearInterval(checkProgress);
    let hasStarted = false;
    checkProgress = setInterval(() => {
        chrome.tabs.sendMessage(tabId, { action: 'getProgress' }, (res) => {
            if (chrome.runtime.lastError) {
                // Connection might be momentarily lost due to reload
                return;
            }
            if (res && res.isScraping) {
                hasStarted = true;
                document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
                document.getElementById('liveStatsText').innerText = `Tweets: ${res.tweetCount || 0} | Users: ${res.userCount || 0}`;
            } else if (res && res.lastResult) {
                clearInterval(checkProgress);
                showResults(res.lastResult, false);
            } else if (hasStarted) {
                clearInterval(checkProgress);
                document.getElementById('progressText').innerText = `\nScraping stopped unexpectedly.`;
            }
        });
    }, 1000);
}

function showResults(result, isPrevious = false) {
    document.getElementById('status').classList.add('hidden');
    document.getElementById('results').classList.remove('hidden');
    document.getElementById('controls').classList.remove('hidden');

    if (isPrevious) {
        document.getElementById('resultsTitle').innerText = "Previous Results:";
    } else {
        document.getElementById('resultsTitle').innerText = "Scraping Complete!";
    }

    document.getElementById('stats').innerText = `${result.tweetCount} tweets, ${result.userCount} users.`;

    // Automatically uncheck the refresh checkbox so the next scrape resumes by default
    document.getElementById('refreshPage').checked = false;

    const downloadLink = (content, filename) => {
        const contentWithBOM = "\uFEFF" + content;
        const blob = new Blob([contentWithBOM], { type: 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            URL.revokeObjectURL(url);
            a.remove();
        }, 1000);
    };

    document.getElementById('dlTweets').onclick = () => {
        const baseName = result.baseName || 'x';
        const ts = result.timestamp || Date.now();
        downloadLink(result.tweetsCSV, `${baseName}_tweets_${ts}.csv`);
    };

    document.getElementById('dlUsers').onclick = () => {
        const baseName = result.baseName || 'x';
        const ts = result.timestamp || Date.now();
        downloadLink(result.usersCSV, `${baseName}_users_${ts}.csv`);
    };
}

document.getElementById('startBtn').addEventListener('click', async () => {
    const pages = document.getElementById('pages').value;
    const refreshPage = document.getElementById('refreshPage').checked;

    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab.url.includes('x.com') && !tab.url.includes('twitter.com')) {
        alert("Please run this on x.com or twitter.com");
        return;
    }

    document.getElementById('controls').classList.add('hidden');
    document.getElementById('status').classList.remove('hidden');
    document.getElementById('results').classList.add('hidden');

    if (refreshPage) {
        document.getElementById('progressText').innerText = "\nReloading page...";
        chrome.storage.local.set({ autoScrapePages: parseInt(pages) }, () => {
            chrome.tabs.reload(tab.id);
        });
        startProgressChecker(tab.id);
        return;
    }

    chrome.tabs.sendMessage(tab.id, { action: 'startScraping', pages: parseInt(pages), clearData: refreshPage }, (response) => {
        if (!response) {
            // Popup context might be lost or content script is not loaded
            return;
        }
        if (response.status === 'done') {
            showResults(response.result, false);
        }
    });

    // Wait for startScraping response handler loop
    // But since startScraping takes a while, we also poll progress independently
    startProgressChecker(tab.id);
});
