// Inject the script into the main world to intercept fetch/XHR
const script = document.createElement('script');
script.src = chrome.runtime.getURL('inject.js');
script.onload = function () {
    this.remove();
};
(document.head || document.documentElement).appendChild(script);

// Global state
let collectedData = [];
let isScraping = false;
let scrollsLeft = 0;
let lastResult = null;
let engine = null;
let shouldStopScraping = false;
let isWaiting = false;
let waitSecondsLeft = 0;

// Listen for intercepted JSON data
window.addEventListener('message', function (event) {
    if (event.source !== window || !event.data || event.data.type !== 'X_SCRAPER_DATA') return;
    try {
        const parsed = JSON.parse(event.data.text);
        collectedData.push(parsed);
        // Process on the fly if we are actively scraping
        if (isScraping && engine) {
            engine.processData([parsed]);
        }
    } catch (e) { }
});

// Listen for commands from the popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'startScraping') {
        if (!isScraping) {
            startScraping(request.pages, sendResponse, request.clearData !== false);
            return true; // Keep message channel open for async response
        } else {
            sendResponse({ status: 'error', message: 'Already scraping' });
        }
    } else if (request.action === 'getProgress') {
        const tweetCount = engine ? Object.keys(engine.tweets).length : 0;
        const userCount = engine ? Object.keys(engine.usersDb).length : 0;
        const isRetryVisible = !!findRetryButton();
        sendResponse({
            isScraping,
            isStopping: shouldStopScraping,
            scrollsLeft,
            count: collectedData.length,
            tweetCount,
            userCount,
            lastResult,
            isWaiting,
            waitSecondsLeft,
            isRetryVisible
        });
    } else if (request.action === 'stopScraping') {
        shouldStopScraping = true;
        sendResponse({ status: 'stopping' });
    } else if (request.action === 'resumeScraping') {
        clickRetryIfPresent();
        waitSecondsLeft = 0;
        isWaiting = false;
        sendResponse({ status: 'resuming' });
    }
});

function findRetryButton() {
    // Look through all elements to find text "Retry" - sometimes it's in a div or span
    return Array.from(document.querySelectorAll('span, div, button, [role="button"]'))
        .find(el => el.childNodes.length === 1 && el.textContent.trim() === 'Retry');
}

function clickRetryIfPresent() {
    const btn = findRetryButton();
    if (btn) {
        console.log("X Scraper: Found Retry button. Clicking...");
        btn.click();
        return true;
    }
    return false;
}

async function startScraping(pages, sendResponse, clearData = true) {
    shouldStopScraping = false;
    isScraping = true;
    scrollsLeft = pages;

    // Clear previously collected data to avoid duplicates from old scrolls
    // (We might want to keep the initial page load data, but clearing makes it predictable)
    if (clearData) {
        collectedData = [];
        engine = new XScraperEngine();
    } else if (!engine) {
        engine = new XScraperEngine();
        engine.processData(collectedData);
    }

    let consecutiveNoGrowth = 0;
    let lastScrollHeight = 0;

    for (let i = 0; i < pages; i++) {
        if (shouldStopScraping) {
            console.log("Scraping stopped by user.");
            break;
        }

        const currentHeight = document.body.scrollHeight;

        // 1. Check for Retry Button (Error handling)
        if (findRetryButton()) {
            clickRetryIfPresent();
            // Short wait to see if it resolves
            await new Promise(resolve => setTimeout(resolve, 3000));

            // Check again
            if (findRetryButton()) {
                console.log("X Scraper: Retry persistent. Likely rate limited. Waiting 15 minutes...");
                await waitWithCountdown(15 * 60); // 15 minutes
                if (shouldStopScraping) break;
                // Try clicking one more time after waiting
                clickRetryIfPresent();
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }

        // 2. Perform Scroll
        window.scrollTo(0, currentHeight);
        scrollsLeft = pages - i - 1;

        // 3. Wait for content
        // We wait slightly longer if we suspect we are reaching a limit or if content is slow
        await new Promise(resolve => setTimeout(resolve, 2500));

        // 4. Check for end of feed
        if (document.body.scrollHeight === currentHeight) {
            consecutiveNoGrowth++;
            if (consecutiveNoGrowth >= 4) { // Allow a few retries for slow loading
                console.log("X Scraper: No more content detected after multiple attempts. Stopping.");
                break;
            }
        } else {
            consecutiveNoGrowth = 0;
        }

        lastScrollHeight = document.body.scrollHeight;
    }

    shouldStopScraping = false;
    isScraping = false;
    isWaiting = false;
    waitSecondsLeft = 0;

    // Generate results
    const result = engine.convertToCSV();

    const baseName = getBaseNameFromUrl();
    result.baseName = baseName;
    result.timestamp = Date.now();

    // Store globally so the popup can retrieve it if reopened
    lastResult = result;

    showOverlay(result.tweetsCSV, result.usersCSV, result.allDataJSON, result.tweetCount, result.userCount, baseName, result.timestamp);

    if (typeof sendResponse === 'function') {
        sendResponse({ status: 'done', result });
    }
}

async function waitWithCountdown(seconds) {
    isWaiting = true;
    waitSecondsLeft = seconds;
    while (waitSecondsLeft > 0 && !shouldStopScraping) {
        await new Promise(res => setTimeout(res, 1000));
        waitSecondsLeft--;
    }
    isWaiting = false;
    waitSecondsLeft = 0;
}

function getBaseNameFromUrl() {
    try {
        const url = new URL(window.location.href);
        const q = url.searchParams.get('q');
        if (q) {
            return q.replace(/[^a-zA-Z0-9_\-]/g, '_').substring(0, 50);
        }
        const pathParts = url.pathname.split('/').filter(p => p);
        if (pathParts.length > 0) {
            const firstPart = pathParts[0];
            const ignoreList = ['home', 'explore', 'notifications', 'messages', 'i', 'search'];
            if (!ignoreList.includes(firstPart)) {
                return firstPart.replace(/[^a-zA-Z0-9_\-]/g, '_');
            }
        }
    } catch (e) { }
    return 'x';
}

function showOverlay(tweetsCSV, usersCSV, allDataJSON, tweetCount, userCount, baseName = 'x', timestamp = Date.now()) {
    let overlay = document.getElementById('xscraper-overlay');
    if (overlay) overlay.remove();

    overlay = document.createElement('div');
    overlay.id = 'xscraper-overlay';
    // Use shadow DOM if we want full isolation, but simple inline styles usually work ok
    overlay.style.cssText = `
        position: fixed; bottom: 30px; left: 30px; background: #fff; 
        padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 9999999; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #14171a; border: 1px solid #ccd6dd; width: 300px;
    `;

    overlay.innerHTML = `
        <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold;">X Scraper Complete</h3>
        <p style="margin: 0 0 15px 0; font-size: 14px; color: #657786;">Found ${tweetCount} tweets and ${userCount} users.</p>
        <button id="xs-dl-tweets" style="display: block; width: 100%; margin-bottom: 10px; padding: 10px; background: #1DA1F2; color: #fff; border: none; border-radius: 9999px; cursor: pointer; font-size: 15px; font-weight: bold; transition: background 0.2s;">Download Tweets CSV</button>
        <button id="xs-dl-users" style="display: block; width: 100%; margin-bottom: 10px; padding: 10px; background: #17bf63; color: #fff; border: none; border-radius: 9999px; cursor: pointer; font-size: 15px; font-weight: bold; transition: background 0.2s;">Download Users CSV</button>
        <button id="xs-dl-json" style="display: block; width: 100%; margin-bottom: 15px; padding: 10px; background: #6e84a3; color: #fff; border: none; border-radius: 9999px; cursor: pointer; font-size: 15px; font-weight: bold; transition: background 0.2s;">Download JSON (All Data)</button>
        <button id="xs-close" style="display: block; width: 100%; padding: 10px; background: #e1e8ed; color: #14171a; border: none; border-radius: 9999px; cursor: pointer; font-size: 15px; font-weight: bold; transition: background 0.2s;">Close</button>
    `;

    document.body.appendChild(overlay);

    const downloadCsv = (filename, content) => {
        const contentWithBOM = "\uFEFF" + content;
        const blob = new Blob([contentWithBOM], { type: 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        a.addEventListener('click', (e) => e.stopPropagation());
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            URL.revokeObjectURL(url);
            a.remove();
        }, 1000);
    };

    document.getElementById('xs-dl-tweets').onclick = () => downloadCsv(`${baseName}_tweets_${timestamp}.csv`, tweetsCSV);
    document.getElementById('xs-dl-users').onclick = () => downloadCsv(`${baseName}_users_${timestamp}.csv`, usersCSV);
    document.getElementById('xs-dl-json').onclick = () => downloadCsv(`${baseName}_data_${timestamp}.json`, allDataJSON);
    document.getElementById('xs-close').onclick = () => overlay.remove();
}

/**
 * XScraperEngine - JS Port of xscraper.php
 */
class XScraperEngine {
    constructor() {
        this.usersDb = {};
        this.tweets = {};
        this.indexCounter = 1;

        this.tweetHeader = [
            'index_on_page', 'tweet_id', 'tweet_permalink_path', 'in_reply_to_user',
            'in_reply_to_tweet', 'quoted_tweet_id', 'user_screen_name',
            'user_id', 'user_name', 'user_location', 'user_timezone', 'user_lang',
            'user_bio', 'user_image_url', 'date_time', 'tweet_date',
            'coordinates_long', 'coordinates_lat', 'country', 'location_fullname',
            'location_name', 'location_type', 'raw_text', 'clear_text', 'user_verified',
            'hashtags', 'responses_to_tweeter', 'urls', 'user_mentions',
            'tweet_language', 'filter_level', 'is_retweet', 'is_quote', 'is_reply',
            'is_referenced', 'retweeted_tweet_id', 'retweeted_user_id',
            'retweeter_ids', 'is_message', 'has_image', 'media_link', 'has_video',
            'has_link', 'links', 'expanded_links', 'retweets', 'quotes', 'favorites',
            'replies', 'source', 'mentions_of_tweeter', 'context_annotations',
            'possibly_sensitive', 'conversation_id', 'withheld_copyright',
            'withheld_in_countries', 'withheld_scope', 'is_protected_or_deleted',
            'retweeter_api_cursor', 'views', 'blue_verified', 'video_views', 'user_geo_enabled'
        ];

        this.userHeader = [
            'user_id', 'user_screen_name', 'user_name', 'user_lang', 'user_geo_enabled',
            'user_location', 'user_timezone', 'user_utc_offset', 'user_tweets',
            'user_followers', 'user_following', 'user_friends', 'user_favorites',
            'user_lists', 'user_bio', 'user_verified', 'user_protected',
            'user_withheld_in_countries', 'user_withheld_scope', 'user_created',
            'user_image_url', 'user_url', 'restricted_to_public', 'is_deleted',
            'is_suspended', 'item_updated_time', 'not_in_search_results', 'blue_verified'
        ];
    }

    initializeRecord(type = "tweet") {
        const header = type === "user" ? this.userHeader : this.tweetHeader;
        const rec = {};
        for (let h of header) rec[h] = null;

        if (type === "user") {
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            rec['item_updated_time'] = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        } else {
            for (let key of ['retweets', 'favorites', 'replies', 'quotes', 'views', 'video_views']) {
                rec[key] = 0;
            }
        }
        return rec;
    }

    formatXDate(dateStr, onlyDate = false) {
        if (!dateStr) return null;
        try {
            // Check if already in YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
            if (typeof dateStr === 'string') {
                if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
                if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(dateStr)) {
                    return onlyDate ? dateStr.split(' ')[0] : dateStr;
                }
            }

            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            const pad = n => String(n).padStart(2, '0');
            const datePart = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            if (onlyDate) return datePart;
            return `${datePart} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        } catch (e) {
            return dateStr;
        }
    }

    cleanHtml(text) {
        return text ? text.replace(/<[^>]+>/g, '') : "";
    }

    extractAndStoreUser(obj) {
        if (!obj || typeof obj !== 'object' || !obj.rest_id) return;

        const legacy = obj.legacy || {};
        const core = obj.core || {};
        const uId = String(obj.rest_id);
        const screenName = legacy.screen_name || core.screen_name || obj.screen_name || null;

        if (!screenName) return;

        const img = legacy.profile_image_url_https || obj.profile_image_url_https || (obj.avatar && obj.avatar.image_url) || null;

        if (!this.usersDb[uId]) {
            this.usersDb[uId] = this.initializeRecord("user");
            this.usersDb[uId].user_id = uId;
        }

        const rec = this.usersDb[uId];

        // Fill in missing bits
        if (!rec.user_screen_name) rec.user_screen_name = screenName;
        // Location - Handle string or object {location: "..."}
        let loc = legacy.location || core.location || obj.location || null;
        if (loc && typeof loc === 'object' && loc.location !== undefined) loc = loc.location;
        if (loc !== null && loc !== undefined && loc !== "") {
            rec.user_location = String(loc);
        }
        if (!rec.user_image_url) rec.user_image_url = img;
        if (!rec.user_bio) rec.user_bio = legacy.description || null;

        if (!rec.user_url) {
            const urlData = legacy.entities?.url?.urls?.[0];
            rec.user_url = urlData?.expanded_url || legacy.url || null;
        }

        // Stats (update if non-zero)
        if (legacy.followers_count) rec.user_followers = legacy.followers_count;
        if (legacy.friends_count) {
            rec.user_following = legacy.friends_count;
            rec.user_friends = legacy.friends_count;
        }
        if (legacy.listed_count) rec.user_lists = legacy.listed_count;
        if (legacy.favourites_count) rec.user_favorites = legacy.favourites_count;
        if (legacy.statuses_count) rec.user_tweets = legacy.statuses_count;

        // Flags
        // Flags
        const verObj = obj.verification || core.verification || {};
        const vType = (verObj.verified_type || "").toLowerCase();
        // user_verified is legacy/org (Gold/Grey or any non-paid blue)
        const isVer = legacy.verified || verObj.verified || (vType && vType !== 'blue');
        if (isVer) rec.user_verified = 1;

        if (obj.is_blue_verified || core.is_blue_verified || vType === 'blue') rec.blue_verified = 1;
        if (legacy.geo_enabled) rec.user_geo_enabled = 1;

        if (!rec.user_created) {
            rec.user_created = this.formatXDate(legacy.created_at || obj.created_at || (obj.core && obj.core.created_at) || null, true);
        }
    }

    recursiveSignatureScan(data) {
        if (Array.isArray(data)) {
            data.forEach(v => this.recursiveSignatureScan(v));
        } else if (data && typeof data === 'object') {
            if (data.rest_id) this.extractAndStoreUser(data);
            for (const key in data) {
                this.recursiveSignatureScan(data[key]);
            }
        }
    }

    processData(collectedObjects) {
        // Phase 1: Mine Users
        for (const data of collectedObjects) {
            this.recursiveSignatureScan(data);
        }

        // Phase 2: Process Tweets
        for (const data of collectedObjects) {
            if (!data || !data.data) continue;

            let instructions = [];

            if (data.data.search_by_raw_query?.search_timeline?.timeline?.instructions) {
                instructions = data.data.search_by_raw_query.search_timeline.timeline.instructions;
            } else if (data.data.user?.result?.timeline_v2?.timeline?.instructions) {
                instructions = data.data.user.result.timeline_v2.timeline.instructions;
            } else if (data.data.threaded_conversation_with_injections_v2?.instructions) {
                instructions = data.data.threaded_conversation_with_injections_v2.instructions;
            }

            for (const ins of instructions) {
                if (ins.type === 'TimelineAddEntries' && ins.entries) {
                    for (const item of ins.entries) {
                        const eid = item.entryId || '';
                        if (!eid.includes('tweet-') && !eid.includes('promoted')) continue;

                        let tRes = item.content?.itemContent?.tweet_results?.result;
                        if (!tRes) continue;
                        if (tRes.__typename === 'TweetWithVisibilityResults') tRes = tRes.tweet;

                        if (!tRes || !tRes.legacy) continue;

                        const leg = tRes.legacy;
                        const tId = String(tRes.rest_id);

                        if (tRes.core?.user_results?.result) {
                            this.extractAndStoreUser(tRes.core.user_results.result);
                        }

                        if (tId && !this.tweets[tId]) {
                            const uId = String(tRes.core?.user_results?.result?.rest_id || '');
                            // Re-fetch uData after the fresh extractAndStoreUser call
                            const uData = this.usersDb[uId] || {};

                            const fullDate = this.formatXDate(leg.created_at || null);
                            const rawText = leg.full_text || '';

                            const hMatches = [...rawText.matchAll(/#(\w+)/g)].map(m => m[1]);
                            const entities = leg.entities || {};
                            const linksList = [];
                            const expandedList = [];

                            if (entities.urls) {
                                for (const u of entities.urls) {
                                    if (u.url) linksList.push(u.url);
                                    if (u.expanded_url) expandedList.push(u.expanded_url);
                                }
                            }

                            const mentionsList = [];
                            if (entities.user_mentions) {
                                for (const m of entities.user_mentions) {
                                    if (m.screen_name) mentionsList.push(m.screen_name);
                                }
                            }

                            const mediaSource = leg.extended_entities || leg.entities || {};
                            const mediaLinks = [];
                            let hasImg = null;
                            let hasVid = null;
                            let vViews = 0;

                            if (mediaSource.media) {
                                for (const m of mediaSource.media) {
                                    if (m.media_url_https) mediaLinks.push(m.media_url_https);
                                    if (m.type === 'photo') hasImg = "1";
                                    if (['video', 'animated_gif'].includes(m.type)) hasVid = "1";
                                    if (m.mediaStats && m.mediaStats.viewCount) {
                                        vViews += m.mediaStats.viewCount;
                                    }
                                }
                            }

                            const rec = this.initializeRecord("tweet");
                            Object.assign(rec, {
                                index_on_page: this.indexCounter++,
                                tweet_id: tId,
                                user_id: uId,
                                user_screen_name: uData.user_screen_name || null,
                                user_name: uData.user_name || null,
                                user_location: uData.user_location || null,
                                user_geo_enabled: uData.user_geo_enabled || 0,
                                user_image_url: uData.user_image_url || null,
                                user_bio: uData.user_bio || null,
                                user_verified: uData.user_verified || 0,
                                blue_verified: uData.blue_verified || 0,
                                raw_text: rawText,
                                clear_text: this.cleanHtml(rawText),
                                date_time: fullDate,
                                tweet_date: fullDate ? fullDate.split(' ')[0] + " 00:00:00" : null,
                                tweet_language: leg.lang || null,
                                in_reply_to_tweet: leg.in_reply_to_status_id_str || null,
                                in_reply_to_user: leg.in_reply_to_screen_name || null,
                                is_reply: leg.in_reply_to_status_id_str ? "1" : null,
                                is_retweet: leg.retweeted_status_id_str ? "1" : null,
                                retweeted_tweet_id: leg.retweeted_status_id_str || null,
                                is_quote: (leg.is_quote_status || leg.quoted_status_id_str) ? "1" : null,
                                quoted_tweet_id: leg.quoted_status_id_str || null,
                                hashtags: hMatches.length > 0 ? hMatches.join(' ') : null,
                                coordinates_lat: leg.geo?.coordinates?.[0] || null,
                                coordinates_long: leg.geo?.coordinates?.[1] || null,
                                country: leg.place?.country || null,
                                location_fullname: leg.place?.full_name || null,
                                location_name: leg.place?.name || null,
                                location_type: leg.place?.place_type || null,
                                has_link: linksList.length > 0 ? "1" : null,
                                links: linksList.join(' '),
                                expanded_links: expandedList.join(' '),
                                user_mentions: mentionsList.join(' '),
                                retweets: leg.retweet_count || 0,
                                favorites: leg.favorite_count || 0,
                                replies: leg.reply_count || 0,
                                quotes: leg.quote_count || 0,
                                views: tRes.views?.count || 0,
                                source: this.cleanHtml(tRes.source || ''),
                                media_link: mediaLinks.join(' '),
                                has_image: hasImg,
                                has_video: hasVid,
                                video_views: vViews > 0 ? vViews : 0,
                                tweet_permalink_path: "https://x.com/" + (uData.user_screen_name || 'i') + "/status/" + tId
                            });

                            if (leg.geo || leg.place) {
                                if (this.usersDb[uId]) this.usersDb[uId].user_geo_enabled = 1;
                                rec.user_geo_enabled = 1;
                            }

                            this.tweets[tId] = rec;
                        }
                    }
                }
            }
        }
    }

    convertToCSV() {
        // Safe CSV escaping helper
        const escapeCSV = (val) => {
            if (val === null || val === undefined) return '';
            const strVal = String(val);
            if (strVal.includes(',') || strVal.includes('"') || strVal.includes('\n') || strVal.includes('\r')) {
                return '"' + strVal.replace(/"/g, '""') + '"';
            }
            return strVal;
        };

        const tweetsArr = Object.values(this.tweets).sort((a, b) => a.index_on_page - b.index_on_page);
        let tweetsCSV = this.tweetHeader.join(",") + "\n";
        for (const t of tweetsArr) {
            tweetsCSV += this.tweetHeader.map(h => escapeCSV(t[h])).join(",") + "\n";
        }

        const usersArr = Object.values(this.usersDb);
        let usersCSV = this.userHeader.join(",") + "\n";
        for (const u of usersArr) {
            usersCSV += this.userHeader.map(h => escapeCSV(u[h])).join(",") + "\n";
        }

        return {
            tweetsCSV,
            usersCSV,
            allDataJSON: JSON.stringify({ tweets: tweetsArr, users: usersArr }, null, 2),
            tweetCount: tweetsArr.length,
            userCount: usersArr.length
        };
    }
}

// Check for auto-scrape instructions after a requested reload
chrome.storage.local.get(['autoScrapePages'], (res) => {
    if (res.autoScrapePages) {
        chrome.storage.local.remove('autoScrapePages');
        // Wait 3.5 seconds to allow initial tweets to load from the network
        setTimeout(() => {
            startScraping(res.autoScrapePages, null, false);
        }, 3500);
    }
});
