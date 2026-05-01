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
                updateStatusUI(res);
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
                updateStatusUI(res);
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

function updateStatusUI(res) {
    if (!res) return;
    if (res.isStopping) {
        document.getElementById('progressText').innerText = "\nStopping and generating results...";
        document.getElementById('stopBtn').disabled = true;
    } else if (res.isWaiting) {
        const mins = Math.floor(res.waitSecondsLeft / 60);
        const secs = res.waitSecondsLeft % 60;
        document.getElementById('progressText').innerText = `\nRate limit hit. Waiting: ${mins}m ${secs}s`;
        document.getElementById('stopBtn').disabled = false;
        document.getElementById('resumeBtn').classList.remove('hidden');
    } else {
        if (res.isPaused) {
            document.getElementById('progressText').innerText = `\nPaused. Scrolls left: ${res.scrollsLeft}`;
            document.getElementById('pauseBtn').innerText = "Unpause Scrolling";
            document.getElementById('pauseBtn').style.background = "#17bf63";
        } else if (res.isCrawling || res.queueLength > 0) {
            document.getElementById('progressText').innerText = `\nCrawling replies: ${res.queueLength} threads in queue...`;
            document.getElementById('pauseBtn').innerText = "Pause Scrolling";
            document.getElementById('pauseBtn').style.background = "#f4a261";
        } else {
            document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
            document.getElementById('pauseBtn').innerText = "Pause Scrolling";
            document.getElementById('pauseBtn').style.background = "#f4a261";
        }
        document.getElementById('stopBtn').disabled = false;
        document.getElementById('resumeBtn').classList.add('hidden');
    }
    document.getElementById('liveStatsText').innerText = `Tweets: ${res.tweetCount || 0} | Users: ${res.userCount || 0}`;
}

async function showResults(result, isPrevious = false) {
    // Attempt to pull aggregated data from background if it exists
    chrome.runtime.sendMessage({ action: 'getMasterData' }, (masterData) => {
        let finalResult = result;
        if (masterData && Object.keys(masterData.tweets || {}).length > 0) {
            const aggregated = convertToCSV(masterData.tweets, masterData.users);
            aggregated.baseName = result.baseName;
            aggregated.timestamp = result.timestamp;
            finalResult = aggregated;
        }

        document.getElementById('status').classList.add('hidden');
        document.getElementById('results').classList.remove('hidden');
        document.getElementById('controls').classList.remove('hidden');

        if (isPrevious) {
            document.getElementById('resultsTitle').innerText = "Previous Results:";
        } else {
            document.getElementById('resultsTitle').innerText = "Scraping Complete!";
        }

        document.getElementById('stats').innerText = `${finalResult.tweetCount} tweets, ${finalResult.userCount} users.`;

        // Automatically uncheck the refresh checkbox so the next scrape resumes by default
        document.getElementById('refreshPage').checked = false;

        const downloadLink = (content, filename, isJson = false) => {
            const finalContent = isJson ? content : "\uFEFF" + content;
            const blob = new Blob([finalContent], { type: isJson ? 'application/json' : 'application/octet-stream' });
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
            const baseName = finalResult.baseName || 'x';
            const ts = finalResult.timestamp || Date.now();
            downloadLink(finalResult.tweetsCSV, `${baseName}_tweets_${ts}.csv`);
        };

        document.getElementById('dlUsers').onclick = () => {
            const baseName = finalResult.baseName || 'x';
            const ts = finalResult.timestamp || Date.now();
            downloadLink(finalResult.usersCSV, `${baseName}_users_${ts}.csv`);
        };

        document.getElementById('dlJSON').onclick = () => {
            const baseName = finalResult.baseName || 'x';
            const ts = finalResult.timestamp || Date.now();
            downloadLink(finalResult.allDataJSON, `${baseName}_data_${ts}.json`, true);
        };
    });
}

document.getElementById('startBtn').addEventListener('click', async () => {
    const pages = document.getElementById('pages').value;
    const crawlDepth = document.getElementById('crawlDepth').value;
    const refreshPage = document.getElementById('refreshPage').checked;
    const includeHidden = document.getElementById('includeHidden').checked;

    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab.url.includes('x.com') && !tab.url.includes('twitter.com')) {
        alert("Please run this on x.com or twitter.com");
        return;
    }

    document.getElementById('controls').classList.add('hidden');
    document.getElementById('status').classList.remove('hidden');
    document.getElementById('results').classList.add('hidden');
    document.getElementById('stopBtn').disabled = false;

    if (refreshPage) {
        document.getElementById('progressText').innerText = "\nReloading page...";
        chrome.storage.local.set({ 
            autoScrapePages: parseInt(pages),
            autoScrapeDepth: parseInt(crawlDepth),
            autoScrapeHidden: includeHidden
        }, () => {
            chrome.runtime.sendMessage({ action: 'setSourceWindow', windowId: tab.windowId });
            chrome.tabs.reload(tab.id);
        });
        startProgressChecker(tab.id);
        return;
    }

    chrome.runtime.sendMessage({ action: 'setSourceWindow', windowId: tab.windowId });
    chrome.tabs.sendMessage(tab.id, { 
        action: 'startScraping', 
        pages: parseInt(pages), 
        depth: parseInt(crawlDepth),
        includeHidden: includeHidden,
        clearData: refreshPage 
    }, (response) => {
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

document.getElementById('stopBtn').addEventListener('click', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    chrome.tabs.sendMessage(tab.id, { action: 'stopScraping' });
    document.getElementById('progressText').innerText = "\nStopping and generating results...";
    document.getElementById('stopBtn').disabled = true;
});

document.getElementById('resumeBtn').addEventListener('click', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    chrome.tabs.sendMessage(tab.id, { action: 'resumeScraping' });
    document.getElementById('resumeBtn').classList.add('hidden');
});

document.getElementById('pauseBtn').addEventListener('click', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    chrome.tabs.sendMessage(tab.id, { action: 'togglePause' }, (res) => {
        if (res && res.isPaused) {
            document.getElementById('pauseBtn').innerText = "Unpause Scrolling";
            document.getElementById('pauseBtn').style.background = "#17bf63";
        } else {
            document.getElementById('pauseBtn').innerText = "Pause Scrolling";
            document.getElementById('pauseBtn').style.background = "#f4a261";
        }
    });
});
