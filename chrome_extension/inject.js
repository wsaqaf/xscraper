(function () {
    const origFetch = window.fetch;
    window.fetch = async function (...args) {
        const response = await origFetch.apply(this, args);
        const url = args[0] instanceof Request ? args[0].url : (typeof args[0] === 'string' ? args[0] : '');
        if (url && (url.includes('/graphql/') || url.includes('SearchTimeline') || url.includes('UserTweets'))) {
            // Clone the response because reading text() consumes the stream
            const clone = response.clone();
            clone.text().then(text => {
                window.postMessage({
                    type: 'X_SCRAPER_DATA',
                    url: url,
                    text: text
                }, '*');
            }).catch(e => {
                console.error("XScraper inject error on fetch clone: ", e);
            });
        }
        return response;
    };

    const XHR = XMLHttpRequest.prototype;
    const open = XHR.open;
    const send = XHR.send;

    XHR.open = function (method, url) {
        this._url = url;
        return open.apply(this, arguments);
    };

    XHR.send = function () {
        this.addEventListener('load', function () {
            if (this._url && (this._url.includes('/graphql/') || this._url.includes('SearchTimeline') || this._url.includes('UserTweets'))) {
                try {
                    const ct = this.getResponseHeader('content-type') || '';
                    if (ct.includes('application/json')) {
                        window.postMessage({
                            type: 'X_SCRAPER_DATA',
                            url: this._url,
                            text: this.responseText
                        }, '*');
                    }
                } catch (e) { }
            }
        });
        return send.apply(this, arguments);
    };
})();
