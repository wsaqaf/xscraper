// Check if already scraping on load
chrome.tabs.query({ active: true, currentWindow: true }, ([tab]) => {
    if (tab && (tab.url.includes('x.com') || tab.url.includes('twitter.com'))) {
        chrome.tabs.sendMessage(tab.id, { action: 'getProgress' }, (res) => {
            if (res && res.isScraping) {
                document.getElementById('controls').classList.add('hidden');
                document.getElementById('status').classList.remove('hidden');
                document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
                startProgressChecker(tab.id);
            } else if (res && res.lastResult) {
                showResults(res.lastResult, true);
            }
        });
    }
});

let checkProgress;

function startProgressChecker(tabId) {
    if (checkProgress) clearInterval(checkProgress);
    checkProgress = setInterval(() => {
        chrome.tabs.sendMessage(tabId, { action: 'getProgress' }, (res) => {
            if (res && res.isScraping) {
                document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
            } else {
                clearInterval(checkProgress);
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

    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab.url.includes('x.com') && !tab.url.includes('twitter.com')) {
        alert("Please run this on x.com or twitter.com");
        return;
    }

    document.getElementById('controls').classList.add('hidden');
    document.getElementById('status').classList.remove('hidden');
    document.getElementById('results').classList.add('hidden');

    chrome.tabs.sendMessage(tab.id, { action: 'startScraping', pages: parseInt(pages) }, (response) => {
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
