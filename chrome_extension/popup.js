document.getElementById('startBtn').addEventListener('click', async () => {
    const pages = document.getElementById('pages').value;

    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab.url.includes('x.com') && !tab.url.includes('twitter.com')) {
        alert("Please run this on x.com or twitter.com");
        return;
    }

    document.getElementById('controls').classList.add('hidden');
    document.getElementById('status').classList.remove('hidden');

    chrome.tabs.sendMessage(tab.id, { action: 'startScraping', pages: parseInt(pages) }, (response) => {
        if (!response) {
            // Popup context might be lost or content script is not loaded
            return;
        }
        if (response.status === 'done') {
            document.getElementById('status').classList.add('hidden');
            document.getElementById('results').classList.remove('hidden');
            document.getElementById('stats').innerText = `${response.result.tweetCount} tweets, ${response.result.userCount} users.`;

            // Wait, creating download link inside popup via chrome.downloads is safer but popup may not have downloads permissions unless added.
            // Using a simple HTML5 download attribute object URL link handles it without needing special permissions
            const downloadLink = (content, filename) => {
                const blob = new Blob([content], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                URL.revokeObjectURL(url);
                a.remove();
            };

            document.getElementById('dlTweets').onclick = () => {
                const baseName = response.result.baseName || 'x';
                downloadLink(response.result.tweetsCSV, `${baseName}_tweets_${Date.now()}.csv`);
            };

            document.getElementById('dlUsers').onclick = () => {
                const baseName = response.result.baseName || 'x';
                downloadLink(response.result.usersCSV, `${baseName}_users_${Date.now()}.csv`);
            };
        }
    });

    // Optionally listen to progress
    const checkProgress = setInterval(() => {
        chrome.tabs.sendMessage(tab.id, { action: 'getProgress' }, (res) => {
            if (res && res.isScraping) {
                document.getElementById('progressText').innerText = `\nScrolls left: ${res.scrollsLeft}`;
            } else {
                clearInterval(checkProgress);
            }
        });
    }, 1000);
});
