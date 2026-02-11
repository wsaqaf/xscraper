import os
import json
import pandas as pd
from datetime import datetime
from flask import Flask, request, jsonify

app = Flask(__name__)

# Dynamic absolute paths for GitHub/Server portability
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_PATH = os.path.join(BASE_DIR, "UPLOAD_FOLDER")

# Ensure UPLOAD_FOLDER exists
if not os.path.exists(UPLOAD_PATH):
    os.makedirs(UPLOAD_PATH)

def format_x_date(date_str):
    """Converts 'Wed Feb 11 15:54:38 +0000 2026' to '2026-02-11 15:54:38'"""
    if not date_str:
        return None
    try:
        dt = datetime.strptime(date_str, '%a %b %d %H:%M:%S %z %Y')
        return dt.strftime('%Y-%m-%d %H:%M:%S')
    except Exception:
        return date_str

def key_exists(element, *keys):
    if not isinstance(element, dict): return False
    _element = element
    for key in keys:
        try:
            _element = _element[key]
        except (KeyError, TypeError):
            return False
    return True

def process_har_file(filename):
    file_path = os.path.join(UPLOAD_PATH, filename)
    if not os.path.isfile(file_path):
        return {"error": f"File {filename} not found"}, 404

    try:
        with open(file_path, 'r', encoding="utf-8") as f:
            content_j = json.loads(f.read())
    except Exception as e:
        return {"error": f"Invalid JSON: {str(e)}"}, 400

    tweets = {}
    users = {}

    if 'log' in content_j and 'entries' in content_j['log']:
        for entry in content_j['log']['entries']:
            url = entry.get('request', {}).get('url', '')
            # Target the SearchTimeline API found in the HAR file [cite: 59]
            if 'SearchTimeline' in url or '/graphql/' in url:
                try:
                    resp_text = entry.get('response', {}).get('content', {}).get('text')
                    if not resp_text: continue
                    data_json = json.loads(resp_text)
                    
                    instructions = []
                    if key_exists(data_json, 'data', 'search_by_raw_query', 'search_timeline', 'timeline', 'instructions'):
                        instructions = data_json['data']['search_by_raw_query']['search_timeline']['timeline']['instructions']
                    
                    for ins in instructions:
                        if ins.get('type') == 'TimelineAddEntries':
                            for item in ins.get('entries', []):
                                eid = item.get('entryId', '')
                                if 'tweet-' in eid or 'promoted-tweet-' in eid:
                                    t_res = item['content']['itemContent']['tweet_results']['result']
                                    
                                    # Unwrap visibility containers [cite: 193]
                                    if t_res.get('__typename') == 'TweetWithVisibilityResults':
                                        t_res = t_res['tweet']
                                    
                                    if not t_res or 'legacy' not in t_res: continue

                                    u_data = t_res['core']['user_results']['result']
                                    u_id = u_data['rest_id']
                                    t_id = t_res['rest_id']

                                    if u_id not in users:
                                        users[u_id] = {
                                            'user_id': u_id,
                                            'user_screen_name': u_data['core'].get('screen_name'),
                                            'user_name': u_data['core'].get('name'),
                                            'user_followers': u_data['legacy'].get('followers_count'),
                                            'user_bio': u_data['legacy'].get('description'),
                                            'user_created': format_x_date(u_data['core'].get('created_at'))
                                        }

                                    if t_id not in tweets:
                                        tweets[t_id] = {
                                            'tweet_id': t_id,
                                            'user_id': u_id,
                                            'user_screen_name': u_data['core'].get('screen_name'),
                                            'raw_text': t_res['legacy'].get('full_text'),
                                            'date_time': format_x_date(t_res['legacy'].get('created_at')),
                                            'retweets': t_res['legacy'].get('retweet_count', 0),
                                            'favorites': t_res['legacy'].get('favorite_count', 0),
                                            'is_promoted': 1 if 'promoted' in eid else 0
                                        }
                except: continue

    if not tweets:
        return {"error": "No tweets extracted."}, 400

    base = os.path.splitext(filename)[0]
    t_csv, u_csv = f"tweets_{base}.csv", f"users_{base}.csv"
    
    pd.DataFrame(tweets.values()).to_csv(os.path.join(UPLOAD_PATH, t_csv), index=False)
    pd.DataFrame(users.values()).to_csv(os.path.join(UPLOAD_PATH, u_csv), index=False)

    return {"message": f"Success! {len(tweets)} tweets captured.", "tweets_file": t_csv, "users_file": u_csv}

@app.route("/api/process", methods=["POST"])
def process_api():
    if 'file' not in request.files: return jsonify({"error": "No file"}), 400
    file = request.files['file']
    file.save(os.path.join(UPLOAD_PATH, file.filename))
    return jsonify(process_har_file(file.filename))

if __name__ == "__main__":
    import sys
    if len(sys.argv) > 1:
        print(process_har_file(sys.argv[1]))
    else:
        app.run(debug=True, host='0.0.0.0', port=5000)
