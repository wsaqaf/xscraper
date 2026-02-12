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

# --- HEADERS (PRESERVED EXACT NAMES) ---
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

def safe_get(data, key):
    if not isinstance(data, dict): return None
    val = data.get(key)
    if isinstance(val, dict) and key in val:
        return val.get(key)
    return val

# --- GLOBAL USER DATABASE & EXTRACTOR ---
users_db = {}

def extract_and_store_user(obj):
    if not isinstance(obj, dict) or 'rest_id' not in obj: return
    
    legacy = obj.get('legacy') or {}
    core = obj.get('core') or {}
    avatar = obj.get('avatar') or {}
    screen_name = legacy.get('screen_name') or core.get('screen_name')
    if not screen_name: return

    u_id = obj.get('rest_id')
    img = (safe_get(avatar, 'image_url') or safe_get(legacy, 'profile_image_url_https') or safe_get(obj, 'profile_image_url_https'))
    
    bio_links = [u.get('expanded_url') for u in legacy.get('entities', {}).get('description', {}).get('urls', []) if u.get('expanded_url')]
    raw_created_at = core.get('created_at') or legacy.get('created_at')
    formatted_created_at = format_x_date(raw_created_at)

    should_update = (u_id not in users_db or (img and not users_db[u_id].get('user_image_url')) or (formatted_created_at and not users_db[u_id].get('user_created')))

    if should_update:
        rec = users_db.get(u_id, initialize_record("user"))
        rec.update({
            'user_id': u_id,
            'user_screen_name': screen_name,
            'user_name': legacy.get('name') or core.get('name'),
            'user_location': legacy.get('location') or safe_get(obj, 'location'),
            'user_image_url': img,
            'user_bio': legacy.get('description'),
            'user_followers': legacy.get('followers_count'),
            'user_friends': legacy.get('friends_count'),
            'user_tweets': legacy.get('statuses_count'),
            'user_verified': 1 if legacy.get('verified') else 0,
            'blue_verified': 1 if obj.get('is_blue_verified') else 0,
            'user_created': formatted_created_at,
            'user_url': " ".join(bio_links) if bio_links else None,
            'user_geo_enabled': 1 if legacy.get('geo_enabled') else 0
        })
        users_db[u_id] = rec

def recursive_signature_scan(data):
    if isinstance(data, dict):
        extract_and_store_user(data)
        for v in data.values(): recursive_signature_scan(v)
    elif isinstance(data, list):
        for item in data: recursive_signature_scan(item)

# --- CORE PROCESSING LOGIC ---
def process_har_file(filename):
    file_path = filename if os.path.exists(filename) else os.path.join(UPLOAD_PATH, filename)
    if not os.path.isfile(file_path): return {"error": f"File {filename} not found"}, 404

    try:
        with open(file_path, 'r', encoding="utf-8") as f:
            content_j = json.loads(f.read())
        gc.collect() 
    except Exception as e: return {"error": f"Invalid JSON: {str(e)}"}, 400

    global users_db
    users_db, tweets, index_counter = {}, {}, 0

    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            resp_text = entry.get('response', {}).get('content', {}).get('text')
            if resp_text:
                try: recursive_signature_scan(json.loads(resp_text))
                except: continue

    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            url = entry.get('request', {}).get('url', '')
            if any(x in url for x in ['/graphql/', 'SearchTimeline', 'UserTweets']):
                try:
                    resp_text = entry.get('response', {}).get('content', {}).get('text')
                    if not resp_text: continue
                    data_json = json.loads(resp_text)
                    data_root = data_json.get('data', {})
                    
                    instructions = (data_root.get('search_by_raw_query', {}).get('search_timeline', {}).get('timeline', {}).get('instructions', []) or 
                                   data_root.get('user', {}).get('result', {}).get('timeline_v2', {}).get('timeline', {}).get('instructions', []) or 
                                   data_root.get('threaded_conversation_with_injections_v2', {}).get('instructions', []))

                    for ins in instructions:
                        if ins.get('type') == 'TimelineAddEntries':
                            for item in ins.get('entries', []):
                                eid = item.get('entryId', '')
                                if 'tweet-' not in eid and 'promoted' not in eid: continue
                                content = item.get('content', {}).get('itemContent', {})
                                t_res = content.get('tweet_results', {}).get('result', {})
                                if t_res.get('__typename') == 'TweetWithVisibilityResults': t_res = t_res.get('tweet', {})
                                if not t_res or 'legacy' not in t_res: continue
                                leg_t = t_res['legacy']
                                
                                u_res = t_res.get('core', {}).get('user_results', {}).get('result', {})
                                if u_res: extract_and_store_user(u_res)
                                
                                u_id, t_id = u_res.get('rest_id'), t_res.get('rest_id')
                                u_data = users_db.get(u_id, {})

                                if t_id and t_id not in tweets:
                                    t_rec = initialize_record("tweet")
                                    ent = leg_t.get('entities', {})
                                    h_tags = " ".join([h['text'] for h in ent.get('hashtags', [])])
                                    u_ments = " ".join([m['screen_name'] for m in ent.get('user_mentions', [])])
                                    exp_links = " ".join([l['expanded_url'] for l in ent.get('urls', []) if 'expanded_url' in l])
                                    full_dt = format_x_date(leg_t.get('created_at'))
                                    
                                    # Retweet Logic
                                    rt_tweet_id = leg_t.get('retweeted_status_id_str')
                                    rt_user_id = None
                                    rt_res = leg_t.get('retweeted_status_result', {}).get('result', {})
                                    if rt_res:
                                        rt_user_id = rt_res.get('core', {}).get('user_results', {}).get('result', {}).get('rest_id')

                                    t_rec.update({
                                        'index_on_page': index_counter,
                                        'tweet_id': t_id,
                                        'user_id': u_id,
                                        'user_screen_name': u_data.get('user_screen_name'),
                                        'user_name': u_data.get('user_name'),
                                        'user_location': u_data.get('user_location'),
                                        'user_image_url': u_data.get('user_image_url'),
                                        'raw_text': leg_t.get('full_text'),
                                        'clear_text': clean_html(leg_t.get('full_text')),
                                        'date_time': full_dt,
                                        'tweet_date': full_dt.split(" ")[0] if full_dt else None,
                                        'retweets': leg_t.get('retweet_count', 0),
                                        'favorites': leg_t.get('favorite_count', 0),
                                        'replies': leg_t.get('reply_count', 0),
                                        'quotes': leg_t.get('quote_count', 0),
                                        'views': t_res.get('views', {}).get('count', 0),
                                        'source': clean_html(t_res.get('source', '')),
                                        'tweet_permalink_path': f"https://x.com/{u_data.get('user_screen_name')}/status/{t_id}",
                                        'tweet_language': leg_t.get('lang'),
                                        'blue_verified': u_data.get('blue_verified', 0),
                                        'hashtags': h_tags,
                                        'user_mentions': u_ments,
                                        'expanded_links': exp_links,
                                        'is_retweet': 1 if rt_tweet_id else 0,
                                        'retweeted_tweet_id': rt_tweet_id,
                                        'retweeted_user_id': rt_user_id,
                                        'is_reply': 1 if leg_t.get('in_reply_to_status_id_str') else 0,
                                        'is_quote': 1 if t_res.get('quoted_status_result') else 0,
                                        'is_referenced': 1 if (leg_t.get('in_reply_to_status_id_str') or t_res.get('quoted_status_result') or rt_tweet_id) else 0
                                    })
                                    
                                    geo = leg_t.get('coordinates')
                                    if geo and geo.get('type') == 'Point':
                                        t_rec['coordinates_long'], t_rec['coordinates_lat'] = geo['coordinates'][0], geo['coordinates'][1]
                                    
                                    place = leg_t.get('place', {})
                                    if place:
                                        t_rec.update({'country': place.get('country'), 'location_fullname': place.get('full_name'), 'location_name': place.get('name'), 'location_type': place.get('place_type')})

                                    if 'media' in ent:
                                        t_rec['media_link'] = " ".join([m['media_url_https'] for m in ent['media']])
                                        if any(m['type'] == 'photo' for m in ent['media']): t_rec['has_image'] = "1"
                                        if any(m['type'] == 'video' for m in ent['media']): t_rec['has_video'] = "1"
                                        t_rec['video_views'] = sum([m.get('mediaStats', {}).get('viewCount', 0) for m in ent['media'] if 'mediaStats' in m])

                                    tweets[t_id] = t_rec
                                    index_counter += 1
                except: continue

    # --- UPDATED NAMING CONVENTION ---
    base_name = os.path.splitext(os.path.basename(filename))[0]
    dt_postfix = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    t_csv = f"{base_name}_tweets_{dt_postfix}.csv"
    u_csv = f"{base_name}_users_{dt_postfix}.csv"

    try:
        pd.DataFrame(list(tweets.values())).reindex(columns=TWEET_HEADER).to_csv(os.path.join(UPLOAD_PATH, t_csv), index=False)
        pd.DataFrame(list(users_db.values())).reindex(columns=USER_HEADER).to_csv(os.path.join(UPLOAD_PATH, u_csv), index=False)
    except Exception as e: return {"error": str(e)}, 500

    return {"message": "Success", "tweets_count": len(tweets), "files": [t_csv, u_csv]}

@app.route("/api/process", methods=["POST"])
def process_api():
    if 'file' not in request.files: return jsonify({"error": "No file"}), 400
    file = request.files['file']
    file_path = os.path.join(UPLOAD_PATH, file.filename)
    file.save(file_path)
    return jsonify(process_har_file(file.filename))

if __name__ == "__main__":
    if len(sys.argv) > 1: print(json.dumps(process_har_file(sys.argv[1]), indent=2))
    else: app.run(debug=False, host='0.0.0.0', port=5000)
