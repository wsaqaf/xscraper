const TWEET_HEADERS = [
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

const USER_HEADERS = [
    'user_id', 'user_screen_name', 'user_name', 'user_lang', 'user_geo_enabled',
    'user_location', 'user_timezone', 'user_utc_offset', 'user_tweets',
    'user_followers', 'user_following', 'user_friends', 'user_favorites',
    'user_lists', 'user_bio', 'user_verified', 'user_protected',
    'user_withheld_in_countries', 'user_withheld_scope', 'user_created',
    'user_image_url', 'user_url', 'restricted_to_public', 'is_deleted',
    'is_suspended', 'item_updated_time', 'not_in_search_results', 'blue_verified'
];

function convertToCSV(tweets, users) {
    const escapeCSV = (val) => {
        if (val === null || val === undefined) return '';
        const strVal = String(val);
        if (strVal.includes(',') || strVal.includes('"') || strVal.includes('\n') || strVal.includes('\r')) {
            return '"' + strVal.replace(/"/g, '""') + '"';
        }
        return strVal;
    };

    const tweetsArr = Object.values(tweets).sort((a, b) => (a.index_on_page || 0) - (b.index_on_page || 0));
    let tweetsCSV = TWEET_HEADERS.join(",") + "\n";
    for (const t of tweetsArr) {
        tweetsCSV += TWEET_HEADERS.map(h => escapeCSV(t[h])).join(",") + "\n";
    }

    const usersArr = Object.values(users);
    let usersCSV = USER_HEADERS.join(",") + "\n";
    for (const u of usersArr) {
        usersCSV += USER_HEADERS.map(h => escapeCSV(u[h])).join(",") + "\n";
    }

    return {
        tweetsCSV,
        usersCSV,
        allDataJSON: JSON.stringify({ tweets: tweetsArr, users: usersArr }, null, 2),
        tweetCount: tweetsArr.length,
        userCount: usersArr.length
    };
}
