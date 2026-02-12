import os
import json
import csv
import re
import pandas as pd
import logging
import gc
import socket
from datetime import datetime
from flask import Flask, request, jsonify

app = Flask(__name__)

# Dynamic absolute paths for server portability
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_PATH = os.path.join(BASE_DIR, "UPLOAD_FOLDER")

if not os.path.exists(UPLOAD_PATH):
    os.makedirs(UPLOAD_PATH)

# --- COMPREHENSIVE HEADERS ---
TWEET_HEADER = [
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
    'retweeter_api_cursor', 'views', 'blue_verified', 'video_views'
]

USER_HEADER = [
    'user_id', 'user_screen_name', 'user_name', 'user_lang', 'user_geo_enabled',
    'user_location', 'user_timezone', 'user_utc_offset', 'user_tweets', 
    'user_followers', 'user_following', 'user_friends', 'user_favorites', 
    'user_lists', 'user_bio', 'user_verified', 'user_protected', 
    'user_withheld_in_countries', 'user_withheld_scope', 'user_created', 
    'user_image_url', 'user_url', 'restricted_to_public', 'is_deleted', 
    'is_suspended', 'item_updated_time', 'not_in_search_results', 'blue_verified'
]

# --- UTILITIES ---

def initialize_record(record_type="tweet"):
    """Ensures consistent CSV structure even if data is missing."""
    if record_type == "user":
        return {field: None if "time" not in field else datetime.now().strftime('%Y-%m-%d %H:%M:%S') for field in USER_HEADER}
    return {field: 0 if field in ['retweets', 'favorites', 'replies', 'quotes', 'views', 'video_views'] else None for field in TWEET_HEADER}

def format_x_date(date_str):
    if not date_str: return None
    try:
        dt = datetime.strptime(date_str, '%a %b %d %H:%M:%S %z %Y')
        return dt.strftime('%Y-%m-%d %H:%M:%S')
    except: return date_str

def clean_html(text):
    if not text: return ""
    return re.sub(r'<[^>]+>', '', text)

# --- MAIN PROCESSING LOGIC ---

def process_har_file(filename):
    file_path = os.path.join(UPLOAD_PATH, filename)
    if not os.path.isfile(file_path):
        return {"error": f"File {filename} not found"}, 404

    try:
        with open(file_path, 'r', encoding="utf-8") as f:
            content_j = json.loads(f.read())
        gc.collect() 
    except Exception as e:
        return {"error": f"Invalid JSON: {str(e)}"}, 400

    tweets = {}
    users = {}
    index_counter = 0

    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            url = entry.get('request', {}).get('url', '')
            
            # Target X API GraphQL endpoints
            if any(x in url for x in ['/graphql/', 'SearchTimeline', 'UserTweets']):
                try:
                    resp_text = entry.get('response', {}).get('content', {}).get('text')
                    if not resp_text: continue
                    data_json = json.loads(resp_text)
                    
                    # Navigate modern timeline instructions
                    data_root = data_json.get('data', {})
                    search_root = data_root.get('search_by_raw_query', {}).get('search_timeline', {}).get('timeline', {})
                    user_root = data_root.get('user', {}).get('result', {}).get('timeline_v2', {}).get('timeline', {})
                    conv_root = data_root.get('threaded_conversation_with_injections_v2', {})
                    
                    instructions = search_root.get('instructions', []) or \
                                   user_root.get('instructions', []) or \
                                   conv_root.get('instructions', [])

                    for ins in instructions:
                        if ins.get('type') == 'TimelineAddEntries':
                            for item in ins.get('entries', []):
                                eid = item.get('entryId', '')
                                
                                # Skip non-tweet metadata entries to prevent hangs
                                if any(x in eid for x in ['cursor-', 'who-to-follow', 'message-prompt']):
                                    continue
                                
                                content = item.get('content', {}).get('itemContent', {})
                                if not content: continue
                                
                                t_res = content.get('tweet_results', {}).get('result', {})
                                if not t_res: continue
                                
                                # Unwrap modern visibility containers
                                if t_res.get('__typename') == 'TweetWithVisibilityResults':
                                    t_res = t_res.get('tweet', {})

                                if 'legacy' not in t_res: continue
                                leg_t = t_res['legacy']

                                # --- USER EXTRACTION (Core vs Legacy) ---
                                u_res = t_res.get('core', {}).get('user_results', {}).get('result', {})
                                if not u_res: continue
                                
                                u_id = u_res.get('rest_id')
                                if u_id and u_id not in users:
                                    u_leg = u_res.get('legacy', {})
                                    u_core = u_res.get('core', {})
                                    u_rec = initialize_record("user")
                                    u_rec.update({
                                        'user_id': u_id,
                                        'user_screen_name': u_core.get('screen_name') or u_leg.get('screen_name'),
                                        'user_name': u_core.get('name') or u_leg.get('name'),
                                        'user_location': u_leg.get('location'),
                                        'user_image_url': u_leg.get('profile_image_url_https'),
                                        'user_bio': u_leg.get('description'),
                                        'user_followers': u_leg.get('followers_count'),
                                        'user_friends': u_leg.get('friends_count'),
                                        'user_tweets': u_leg.get('statuses_count'),
                                        'user_verified': 1 if u_leg.get('verified') else 0,
                                        'blue_verified': 1 if u_res.get('is_blue_verified') else 0,
                                        'user_created': format_x_date(u_leg.get('created_at'))
                                    })
                                    users[u_id] = u_rec

                                # --- TWEET EXTRACTION ---
                                t_id = t_res.get('rest_id')
                                if t_id and t_id not in tweets:
                                    t_rec = initialize_record("tweet")
                                    t_rec.update({
                                        'index_on_page': index_counter,
                                        'tweet_id': t_id,
                                        'user_id': u_id,
                                        'user_screen_name': users[u_id]['user_screen_name'],
                                        'user_name': users[u_id]['user_name'],
                                        'user_location': users[u_id]['user_location'],
                                        'user_image_url': users[u_id]['user_image_url'],
                                        'raw_text': leg_t.get('full_text'),
                                        'clear_text': clean_html(leg_t.get('full_text')),
                                        'date_time': format_x_date(leg_t.get('created_at')),
                                        'retweets': leg_t.get('retweet_count', 0),
                                        'favorites': leg_t.get('favorite_count', 0),
                                        'replies': leg_t.get('reply_count', 0),
                                        'quotes': leg_t.get('quote_count', 0),
                                        'views': t_res.get('views', {}).get('count', 0),
                                        'source': clean_html(t_res.get('source', '')),
                                        'tweet_permalink_path': f"https://x.com/{users[u_id]['user_screen_name']}/status/{t_id}"
                                    })
                                    
                                    # Media & Engagement
                                    ent = leg_t.get('entities', {})
                                    if 'media' in ent:
                                        t_rec['media_link'] = " ".join([m['media_url_https'] for m in ent['media']])
                                        t_rec['has_image'] = 1 if any(m['type'] == 'photo' for m in ent['media']) else 0
                                        t_rec['has_video'] = 1 if any(m['type'] == 'video' for m in ent['media']) else 0
                                        v_views = 0
                                        for m in ent['media']:
                                            if 'mediaStats' in m and 'viewCount' in m['mediaStats']:
                                                v_views += m['mediaStats']['viewCount']
                                        t_rec['video_views'] = v_views

                                    tweets[t_id] = t_rec
                                    index_counter += 1
                except:
                    continue

    # Memory cleanup before CSV conversion
    del content_j
    gc.collect()

    if not tweets:
        return {"error": "No data found. Ensure the HAR contains valid GraphQL responses."}, 400

    base = os.path.splitext(filename)[0]
    t_csv, u_csv = f"tweets_{base}.csv", f"users_{base}.csv"
    
    pd.DataFrame(tweets.values())[TWEET_HEADER].to_csv(os.path.join(UPLOAD_PATH, t_csv), index=False)
    pd.DataFrame(users.values())[USER_HEADER].to_csv(os.path.join(UPLOAD_PATH, u_csv), index=False)

    return {"message": "Success", "files": [t_csv, u_csv]}

@app.route("/api/process", methods=["POST"])
def process_api():
    if 'file' not in request.files: return jsonify({"error": "No file"}), 400
    file = request.files['file']
    file_path = os.path.join(UPLOAD_PATH, file.filename)
    file.save(file_path)
    return jsonify(process_har_file(file.filename))

if __name__ == "__main__":
    # Debug OFF is essential for shared hosting compatibility
    app.run(debug=False, host='0.0.0.0', port=5000)
