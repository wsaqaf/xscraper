import os
import sys
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

# --- CONFIGURATION ---
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_PATH = os.path.join(BASE_DIR, "UPLOAD_FOLDER")

if not os.path.exists(UPLOAD_PATH):
    os.makedirs(UPLOAD_PATH)

# --- HEADERS ---
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
    if record_type == "user":
        return {
            field: datetime.now().strftime('%Y-%m-%d %H:%M:%S') 
            if field == 'item_updated_time' 
            else None 
            for field in USER_HEADER
        }
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

def safe_get(data, key):
    """Deep retrieval that handles nested dicts"""
    if not isinstance(data, dict): return None
    val = data.get(key)
    if isinstance(val, dict) and key in val:
        return val.get(key)
    return val

def clean_timezone(tz_str):
    """Ensure timezone is valid and not a date."""
    if not tz_str: return None
    tz_str = str(tz_str).strip()
    if re.search(r'20\d{2}', tz_str):
        return None
    return tz_str

# --- GLOBAL USER DATABASE & EXTRACTOR ---
users_db = {}

def extract_and_store_user(obj):
    if not isinstance(obj, dict): return
    if 'rest_id' not in obj: return
    
    legacy = obj.get('legacy') or {}
    core = obj.get('core') or {}
    avatar = obj.get('avatar') or {} 
    
    screen_name = legacy.get('screen_name') or core.get('screen_name')
    if not screen_name: return

    u_id = obj.get('rest_id')
    
    img = (
        safe_get(avatar, 'image_url') or 
        safe_get(legacy, 'profile_image_url_https') or 
        safe_get(obj, 'profile_image_url_https')
    )
    
    loc = safe_get(legacy, 'location') or safe_get(obj, 'location')
    
    created_raw = (
        legacy.get('created_at') or 
        core.get('created_at') or 
        obj.get('created_at')
    )
    
    raw_tz = legacy.get('time_zone')
    clean_tz = clean_timezone(raw_tz)

    # --- MAP VALUES ---
    # friends_count = The number of people this user is FOLLOWING
    # listed_count = The number of public lists this user is a member of
    following_count = legacy.get('friends_count') 
    lists_count = legacy.get('listed_count')

    if u_id not in users_db or (img and not users_db[u_id].get('user_image_url')):
        rec = initialize_record("user")
        rec.update({
            'user_id': str(u_id), # Ensure ID is string to prevent SciNotation
            'user_screen_name': screen_name,
            'user_name': legacy.get('name') or core.get('name'),
            'user_location': loc,
            'user_timezone': clean_tz,
            'user_image_url': img,
            'user_bio': legacy.get('description'),
            'user_followers': legacy.get('followers_count'),
            'user_following': following_count, 
            'user_friends': following_count,   
            'user_lists': lists_count,         
            'user_favorites': legacy.get('favourites_count'), 
            'user_tweets': legacy.get('statuses_count'),
            'user_verified': 1 if legacy.get('verified') else 0,
            'blue_verified': 1 if obj.get('is_blue_verified') else 0,
            'user_created': format_x_date(created_raw)
        })
        users_db[u_id] = rec

def recursive_signature_scan(data):
    if isinstance(data, dict):
        extract_and_store_user(data)
        for k, v in data.items():
            recursive_signature_scan(v)
    elif isinstance(data, list):
        for item in data:
            recursive_signature_scan(item)

# --- CORE PROCESSING LOGIC ---
def process_har_file(filename):
    if os.path.exists(filename):
        file_path = filename
    else:
        file_path = os.path.join(UPLOAD_PATH, filename)
    
    if not os.path.isfile(file_path):
        return {"error": f"File {filename} not found"}, 404

    try:
        print(f"Loading {filename}...")
        with open(file_path, 'r', encoding="utf-8") as f:
            content_j = json.loads(f.read())
        gc.collect() 
    except Exception as e:
        return {"error": f"Invalid JSON: {str(e)}"}, 400

    global users_db
    users_db = {}
    tweets = {}
    index_counter = 0

    print("Phase 1: Mining User Profiles via Signature Scan...")
    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            resp_text = entry.get('response', {}).get('content', {}).get('text')
            if not resp_text: continue
            try:
                data_json = json.loads(resp_text)
                recursive_signature_scan(data_json)
            except: continue

    print(f"Found {len(users_db)} unique users.")

    print("Phase 2: Processing Tweets...")
    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            url = entry.get('request', {}).get('url', '')
            if any(x in url for x in ['/graphql/', 'SearchTimeline', 'UserTweets']):
                try:
                    resp_text = entry.get('response', {}).get('content', {}).get('text')
                    if not resp_text: continue
                    data_json = json.loads(resp_text)
                    
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
                                if 'tweet-' not in eid and 'promoted' not in eid: continue
                                
                                content = item.get('content', {}).get('itemContent', {})
                                t_res = content.get('tweet_results', {}).get('result', {})
                                
                                if t_res.get('__typename') == 'TweetWithVisibilityResults':
                                    t_res = t_res.get('tweet', {})

                                if not t_res or 'legacy' not in t_res: continue
                                leg_t = t_res['legacy']
                                
                                u_res = t_res.get('core', {}).get('user_results', {}).get('result', {})
                                if u_res: extract_and_store_user(u_res)
                                
                                u_id = u_res.get('rest_id')
                                u_data = users_db.get(u_id, {})
                                
                                t_id = t_res.get('rest_id')
                                if t_id and t_id not in tweets:
                                    t_rec = initialize_record("tweet")
                                    
                                    # --- EXTRACT MENTIONS & REPLIES (FIX) ---
                                    ent = leg_t.get('entities', {})
                                    mentions_str = ""
                                    if 'user_mentions' in ent:
                                        # Join screen names with spaces: "user1 user2"
                                        mentions_str = " ".join([m['screen_name'] for m in ent['user_mentions']])
                                    
                                    reply_to_user = leg_t.get('in_reply_to_screen_name')
                                    reply_to_tweet = leg_t.get('in_reply_to_status_id_str')
                                    
                                    t_rec.update({
                                        'index_on_page': index_counter,
                                        'tweet_id': str(t_id), # String to force ID preservation
                                        'user_id': str(u_id),
                                        'user_screen_name': u_data.get('user_screen_name'),
                                        'user_name': u_data.get('user_name'),
                                        'user_location': u_data.get('user_location'),
                                        'user_timezone': u_data.get('user_timezone'),
                                        'user_image_url': u_data.get('user_image_url'),
                                        'raw_text': leg_t.get('full_text'),
                                        'clear_text': clean_html(leg_t.get('full_text')),
                                        'date_time': format_x_date(leg_t.get('created_at')),
                                        'retweets': leg_t.get('retweet_count', 0),
                                        'favorites': leg_t.get('favorite_count', 0),
                                        'replies': leg_t.get('reply_count', 0),
                                        'quotes': leg_t.get('quote_count', 0),
                                        'views': t_res.get('views', {}).get('count', 0),
                                        'source': clean_html(t_res.get('source', '')),
                                        'tweet_permalink_path': f"https://x.com/{u_data.get('user_screen_name')}/status/{t_id}",
                                        # Populated fields:
                                        'user_mentions': mentions_str,
                                        'in_reply_to_user': reply_to_user,
                                        'in_reply_to_tweet': str(reply_to_tweet) if reply_to_tweet else None,
                                        'is_reply': 1 if reply_to_tweet else 0,
                                        'is_retweet': 1 if leg_t.get('retweeted_status_result') else 0,
                                        'is_quote': 1 if leg_t.get('is_quote_status') else 0
                                    })
                                    
                                    if 'media' in ent:
                                        t_rec['media_link'] = " ".join([m['media_url_https'] for m in ent['media']])
                                        if any(m['type'] == 'photo' for m in ent['media']):
                                            t_rec['has_image'] = "1"
                                        if any(m['type'] == 'video' for m in ent['media']):
                                            t_rec['has_video'] = "1"
                                        v_views = 0
                                        for m in ent['media']:
                                            if 'mediaStats' in m and 'viewCount' in m['mediaStats']:
                                                v_views += m['mediaStats']['viewCount']
                                        t_rec['video_views'] = v_views

                                    tweets[t_id] = t_rec
                                    index_counter += 1
                except: continue

    del content_j
    gc.collect()

    base_name = os.path.splitext(os.path.basename(filename))[0]
    dt_postfix = datetime.now().strftime('%Y%m%d_%H%M%S')
    t_csv = f"{base_name}_tweets_{dt_postfix}.csv"
    u_csv = f"{base_name}_users_{dt_postfix}.csv"

    try:
        df_tweets = pd.DataFrame(list(tweets.values()))
        if df_tweets.empty: 
            df_tweets = pd.DataFrame(columns=TWEET_HEADER)
        else:
            # Ensure columns exist
            for col in TWEET_HEADER:
                if col not in df_tweets.columns: df_tweets[col] = None
            df_tweets = df_tweets[TWEET_HEADER]
            
        # Force string type for IDs to prevent Excel scientific notation conversion
        for col in ['tweet_id', 'user_id', 'in_reply_to_tweet', 'quoted_tweet_id']:
            if col in df_tweets.columns:
                df_tweets[col] = df_tweets[col].astype(str).replace('nan', '')

        df_tweets.to_csv(os.path.join(UPLOAD_PATH, t_csv), index=False, quoting=csv.QUOTE_ALL)

        df_users = pd.DataFrame(list(users_db.values()))
        if df_users.empty: 
            df_users = pd.DataFrame(columns=USER_HEADER)
        else:
             for col in USER_HEADER:
                if col not in df_users.columns: df_users[col] = None
             df_users = df_users[USER_HEADER]
             
        # Force string for User IDs
        if 'user_id' in df_users.columns:
             df_users['user_id'] = df_users['user_id'].astype(str).replace('nan', '')

        df_users.to_csv(os.path.join(UPLOAD_PATH, u_csv), index=False, quoting=csv.QUOTE_ALL)
    except Exception as e:
        return {"error": str(e)}, 500

    return {"message": "Success", "tweets_count": len(tweets), "files": [t_csv, u_csv]}

@app.route("/api/process", methods=["POST"])
def process_api():
    if 'file' not in request.files: return jsonify({"error": "No file"}), 400
    file = request.files['file']
    file_path = os.path.join(UPLOAD_PATH, file.filename)
    file.save(file_path)
    return jsonify(process_har_file(file.filename))

def find_free_port():
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(('', 0))
        return s.getsockname()[1]

if __name__ == "__main__":
    if len(sys.argv) > 1:
        target_file = sys.argv[1]
        print(f"--- CLI Mode: Processing {target_file} ---")
        result = process_har_file(target_file)
        print(json.dumps(result, indent=2))
        print("Done.")
    else:
        print("--- Server Mode: Waiting for API requests ---")
        port = 5000
        try:
            app.run(debug=False, host='0.0.0.0', port=port)
        except OSError:
            port = find_free_port()
            print(f"Port 5000 busy. Starting on port {port}")
            app.run(debug=False, host='0.0.0.0', port=port)
