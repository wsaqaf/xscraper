from flask import Flask, request, jsonify, send_from_directory
import sys
import os
import json
import csv
import re
from datetime import datetime
from bs4 import BeautifulSoup
import html
import logging
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException
import pandas as pd

# Initialize Flask application
app = Flask(__name__)

# Define the process_har_file function that will be called by both
# the Flask route and the command line interface
def process_har_file(input_file):
    skip_ads = 1
    file_name = input_file
    output_file = input_file

    if not os.path.isfile("UPLOAD_FOLDER/"+file_name):
        return {"error": f"File: UPLOAD_FOLDER/{file_name} not found"}, 404

    with open("UPLOAD_FOLDER/"+file_name, 'r', encoding="utf-8") as f:
        contents = f.read()

    content_j = json.loads(contents)
    m = ""

    try:
        m = re.search('https://x\.com/(.+[^/])/?', content_j['log']['pages'][0]['title'])
    except:
        try:
            m = re.search('https://x\.com/(.+[^/])/?', content_j['log']['entries'][0]['request']['url'])
        except:
            pass

    # Initialize functions
    def initialize_record(record_type=""):
        # Your existing initialize_record function
        new_record={}
        if record_type=="user":
            new_record = {
                'user_id': 0,
                'user_screen_name': None,
                'user_name': None,
                'user_lang': None,
                'user_geo_enabled': 0,  # Assuming false (0) as default
                'user_location': None,
                'user_timezone': None,
                'user_utc_offset': 0,
                'user_tweets': 0,
                'user_followers': 0,
                'user_following': 0,
                'user_friends': 0,
                'user_favorites': 0,
                'user_lists': 0,
                'user_bio': None,
                'user_verified': 0,  # Assuming false (0) as default
                'user_protected': 0,  # Assuming false (0) as default
                'user_withheld_in_countries': None,
                'user_withheld_scope': None,
                'user_created': '2006-03-21 00:00:00',
                'user_image_url': None,
                'user_url': None,
                'restricted_to_public': 0,  # Assuming false (0) as default
                'is_deleted': 0,  # Assuming false (0) as default
                'is_suspended': 0,  # Assuming false (0) as default
                'item_updated_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),  # This will be the current timestamp
                'not_in_search_results': 0  # Assuming false (0) as default
            }
        else:
            new_record = {
                'index_on_page': 0,  # Assuming 0 as a default value, adjust as needed
                'tweet_id': 0,
                'tweet_permalink_path': None,
                'in_reply_to_user': 0,
                'in_reply_to_tweet': 0,
                'quoted_tweet_id': 0,
                'user_screen_name': None,
                'user_id': 0,
                'user_name': None,
                'user_location': None,
                'user_timezone': None,
                'user_lang': None,
                'user_bio': None,
                'user_image_url': None,
                'date_time': None,
                'tweet_date': None,
                'coordinates_long': None,
                'coordinates_lat': None,
                'country': None,
                'location_fullname': None,
                'location_name': None,
                'location_type': None,
                'raw_text': None,
                'clear_text': '',  # Assuming an empty string as default
                'user_verified': 0,  # Assuming false (0) as default
                'hashtags': None,
                'responses_to_tweeter': 0,
                'urls': None,
                'user_mentions': None,
                'tweet_language': None,
                'filter_level': None,
                'is_retweet': 0,  # Assuming false (0) as default
                'is_quote': 0,  # Assuming false (0) as default
                'is_reply': 0,  # Assuming false (0) as default
                'is_referenced': 0,  # Assuming false (0) as default
                'retweeted_tweet_id': 0,
                'retweeted_user_id': 0,
                'retweeter_ids': None,
                'is_message': 0,  # Assuming false (0) as default
                'has_image': 0,  # Assuming false (0) as default
                'media_link': None,
                'has_video': 0,  # Assuming false (0) as default
                'has_link': 0,  # Assuming false (0) as default
                'links': None,
                'expanded_links': None,
                'retweets': 0,  # Default value
                'quotes': 0,  # Default value
                'favorites': 0,  # Default value
                'replies': 0,  # Default value
                'source': None,
                'mentions_of_tweeter': 0,
                'context_annotations': None,
                'possibly_sensitive': 0,  # Assuming false (0) as default
                'conversation_id': 0,
                'withheld_copyright': 0,  # Assuming false (0) as default
                'withheld_in_countries': None,
                'withheld_scope': None,
                'is_protected_or_deleted': 0,  # Assuming false (0) as default
                'retweeter_api_cursor': -1  # Default value as per your structure
            }
        return new_record        

    def key_exists(element, *keys):
        # Your existing key_exists function
        if not isinstance(element, dict):
            raise AttributeError('keys_exists() expects dict as first argument.')
        if len(keys) == 0:
            raise AttributeError('keys_exists() expects at least two arguments, one given.')

        _element = element
        for key in keys:
            try:
                _element = _element[key]
            except KeyError:
                return False
        return True

    def debug_content(content):
        text_file = open("temp.txt", "w")
        text_file.write(content)
        text_file.close()

    i = 0
    labels_str = ["tweet_id", "tweet_time", "tweet_url", "user_id", "user_name", "user_webpage", "user_profile", "user_profile_pic", "user_followers", "tweet_text", "weblink_url", "weblink_title", "weblink_pic", "weblink_preview", "photo_url", "video_url", "video_duration"]
    labels_num = ["video_view_count", "retweets", "replies", "quotes", "favorites"]
    xtweets_list = []
    xtweets_list.append(labels_str + labels_num)

    tweets = {}
    users = {}
    if key_exists(content_j, 'log', 'entries'):
        for entry in content_j['log']['entries']:
            if 'https://x.com/i/api/graphql/' in entry['request']['url']:
                try:
                    if 'content' in entry['response'] and 'text' in entry['response']['content']:
                        try:
                            text = json.loads(entry['response']['content']['text'])
                        except:
                            continue
                        tweet_list = []
                        if key_exists(text, 'data', 'search_by_raw_query', 'search_timeline', 'timeline', 'instructions'):
                            tweet_list = text['data']['search_by_raw_query']['search_timeline']['timeline']['instructions']
                        for instruction in tweet_list:
                            if key_exists(instruction, 'entries'):
                                for entry in instruction['entries']:
                                    is_ad = 0
                                    if entry['entryId'].startswith('promoted-tweet-'):
                                        if skip_ads:
                                            continue
                                        else:
                                            is_ad = 1
                                    if key_exists(entry, 'content', 'itemContent', 'tweet_results', 'result'):
                                        tweet_data = entry['content']['itemContent']['tweet_results']['result']
                                        if key_exists(tweet_data, 'core', 'user_results', 'result'):
                                            user = tweet_data['core']['user_results']['result']
                                            if user['rest_id'] and user['rest_id'] in users and users[user['rest_id']]['user_created'] == "2006-03-21 00:00:00":
                                                del users[user['rest_id']]
                                            if user['rest_id'] and user['rest_id'] not in users:
                                                new_user = initialize_record("user")
                                                new_user['user_id'] = user['rest_id']
                                                new_user['user_screen_name'] = user['legacy']['screen_name']
                                                new_user['user_name'] = user['legacy']['name']
                                                new_user['user_bio'] = user['legacy']['description']
                                                if is_ad:
                                                    new_user['user_bio'] = '[ADVERTISER-TWEETER]: ' + new_user['user_bio']
                                                new_user['user_location'] = user['legacy']['location']
                                                new_user['user_tweets'] = user['legacy']['statuses_count']
                                                new_user['user_followers'] = user['legacy']['followers_count']
                                                new_user['user_friends'] = user['legacy']['friends_count']
                                                new_user['user_favorites'] = user['legacy']['favourites_count']
                                                if key_exists(user, 'professional'):
                                                    new_user['user_bio'] = user['professional']['professional_type'] + " - " + new_user['user_bio']
                                                    for catgry in user['professional']['category']:
                                                        new_user['user_bio'] = catgry['name'] + " - " + new_user['user_bio']
                                                if user.get('is_blue_verified'):
                                                    new_user['blue_verified'] = 1
                                                if user['legacy']['verified']:
                                                    new_user['user_verified'] = 1
                                                new_user['user_withheld_in_countries'] = ' '.join(user['legacy']['withheld_in_countries'])
                                                parsed_date = datetime.strptime(user['legacy']['created_at'], '%a %b %d %H:%M:%S %z %Y')
                                                new_user['user_created'] = parsed_date.strftime('%Y-%m-%d %H:%M:%S')
                                                new_user['user_image_url'] = user['legacy']['profile_image_url_https']
                                                if key_exists(user, 'legacy', 'entities', 'url', 'urls'):
                                                    try:
                                                        if user['legacy']['entities']['url']['urls']:
                                                            if key_exists(user['legacy']['entities']['url']['urls'][0], 'expanded_url'):
                                                                new_user['user_url'] = user['legacy']['entities']['url']['urls'][0]['expanded_url']
                                                            elif key_exists(user['legacy']['entities']['url']['urls'][0], 'url'):
                                                                new_user['user_url'] = user['legacy']['entities']['url']['urls'][0]['url']
                                                    except:
                                                        print("Error with URL: " + str(user['legacy']['entities']['url']['urls'][0]))
                                                users[user['rest_id']] = new_user
                                            elif user['rest_id']:
                                                new_user = users[user['rest_id']]

                                            if tweet_data['rest_id'] and tweet_data['rest_id'] not in tweets:
                                                new_tweet = initialize_record()
                                                new_tweet['tweet_id'] = tweet_data['rest_id']
                                                new_tweet['user_id'] = new_user['user_id']
                                                new_user['user_id'] = user['rest_id']

                                                new_tweet['user_screen_name'] = new_user['user_screen_name']
                                                new_tweet['user_name'] = new_user['user_name']
                                                new_tweet['user_location'] = new_user['user_location']
                                                new_tweet['user_bio'] = new_user['user_bio']
                                                new_tweet['user_image_url'] = new_user['user_image_url']
                                                if key_exists(new_user, 'user_verified'):
                                                    new_tweet['user_verified'] = new_user['user_verified']
                                                if key_exists(new_user, 'blue_verified'):
                                                    new_tweet['blue_verified'] = new_user['blue_verified']
                                                if key_exists(tweet_data['legacy'], 'possibly_sensitive'):
                                                    if tweet_data['legacy']['possibly_sensitive'] == 'TRUE':
                                                        new_tweet['possibly_sensitive'] = 1
                                                new_tweet['conversation_id'] = tweet_data['legacy']['conversation_id_str']

                                                parsed_date = datetime.strptime(tweet_data['legacy']['created_at'], '%a %b %d %H:%M:%S %z %Y')
                                                new_tweet['date_time'] = parsed_date.strftime('%Y-%m-%d %H:%M:%S')
                                                new_tweet['tweet_date'] = parsed_date.strftime('%Y-%m-%d')
                                                new_tweet['tweet_permalink_path'] = f"https://x.com/{new_tweet['user_screen_name']}/status/{new_tweet['tweet_id']}"
                                                new_tweet['retweets'] = tweet_data['legacy']['retweet_count']
                                                new_tweet['favorites'] = tweet_data['legacy']['favorite_count']
                                                new_tweet['replies'] = tweet_data['legacy']['reply_count']
                                                new_tweet['quotes'] = tweet_data['legacy']['quote_count']
                                                if key_exists(tweet_data, 'views', 'count'):
                                                    new_tweet['views'] = tweet_data['views']['count']
                                                new_tweet['raw_text'] = tweet_data['legacy']['full_text']
                                                if is_ad:
                                                    new_tweet['raw_text'] = '[ADVERTISEMENT-TWEET]: ' + new_tweet['raw_text']
                                                new_tweet['source'] = re.sub(r'<[^>]+>', '', tweet_data['source'])
                                                new_tweet['tweet_language'] = tweet_data['legacy']['lang']
                                                if tweet_data['legacy']['is_quote_status']:
                                                    quoted_tweet = initialize_record()
                                                    new_tweet['is_quote'] = 1
                                                    new_tweet['quoted_tweet_id'] = tweet_data['legacy']['quoted_status_id_str']
                                                    new_tweet['raw_text'] = new_tweet['raw_text'] + "\n---\n" + tweet_data['legacy']['quoted_status_permalink']['expanded']
                                                    if key_exists(tweet_data, 'quoted_status_result', 'result', 'legacy'):
                                                        quoted_tweet = tweet_data['quoted_status_result']['result']['legacy']
                                                        quoted_user = tweet_data['quoted_status_result']['result']['core']['user_results']['result']['legacy']
                                                        if not key_exists(quoted_tweet, 'full_text'):
                                                            quoted_tweet['full_text'] = ""
                                                        new_tweet['raw_text'] = new_tweet['raw_text'] + "\n" + quoted_tweet['full_text']
                                                        if key_exists(quoted_tweet, 'user_id_str'):
                                                            if quoted_tweet['user_id_str'] not in users:
                                                                users[quoted_tweet['user_id_str']] = initialize_record("user")
                                                                users[quoted_tweet['user_id_str']]['user_id'] = quoted_tweet['user_id_str']
                                                                users[quoted_tweet['user_id_str']]['user_screen_name'] = quoted_user['screen_name']
                                                                users[quoted_tweet['user_id_str']]['user_name'] = quoted_user['name']
                                                if key_exists(tweet_data['legacy'], 'place', 'bounding_box'):
                                                    try:
                                                        new_tweet['coordinates_long'] = (tweet_data['legacy']['place']['bounding_box']['coordinates'][0][0][0] + tweet_data['legacy']['place']['bounding_box']['coordinates'][0][2][0]) / 2
                                                        new_tweet['coordinates_lat'] = (tweet_data['legacy']['place']['bounding_box']['coordinates'][0][0][1] + tweet_data['legacy']['place']['bounding_box']['coordinates'][0][2][1]) / 2
                                                        new_tweet['country'] = tweet_data['legacy']['place']['country']
                                                        new_tweet['location_fullname'] = tweet_data['legacy']['place']['full_name']
                                                        new_tweet['location_name'] = tweet_data['legacy']['place']['name']
                                                        new_tweet['location_type'] = tweet_data['legacy']['place']['place_type']
                                                    except:
                                                        print("bbox: " + str(tweet_data['legacy']['place']))
                                                if key_exists(tweet_data, 'legacy', 'in_reply_to_user_id_str'):
                                                    new_tweet['in_reply_to_user'] = tweet_data['legacy']['in_reply_to_user_id_str']
                                                    new_tweet['is_reply'] = 1
                                                if key_exists(tweet_data, 'legacy', 'in_reply_to_status_id_str'):
                                                    new_tweet['in_reply_to_tweet'] = tweet_data['legacy']['in_reply_to_status_id_str']
                                                    new_tweet['is_reply'] = 1
                                                if key_exists(tweet_data['legacy']['entities'], 'media'):
                                                    new_tweet['media_link'] = ""
                                                    new_tweet['video_views'] = 0
                                                    for medium in tweet_data['legacy']['entities']['media']:
                                                        new_tweet['media_link'] = (medium['media_url_https'] + " " + new_tweet['media_link']).strip()
                                                        if medium['type'] == 'photo':
                                                            new_tweet['has_image'] = 1
                                                        if medium['type'] == 'video':
                                                            new_tweet['has_video'] = 1
                                                            if key_exists(medium, 'additional_media_info', 'title'):
                                                                new_tweet['raw_text'] = new_tweet['raw_text'] + " - " + medium['additional_media_info']['title']
                                                            if key_exists(medium, 'mediaStats', 'viewCount'):
                                                                new_tweet['video_views'] = new_tweet['video_views'] + medium['mediaStats']['viewCount']

                                                if tweet_data['legacy']['entities']['hashtags']:
                                                    new_tweet['hashtags'] = ""
                                                    for hashtag in tweet_data['legacy']['entities']['hashtags']:
                                                        new_tweet['hashtags'] = ("#" + hashtag['text'] + " " + new_tweet['hashtags']).strip()
                                                if tweet_data['legacy']['entities']['urls']:
                                                    new_tweet['has_link'] = 1
                                                    new_tweet['expanded_links'] = ""
                                                    new_tweet['urls'] = ""
                                                    for url in tweet_data['legacy']['entities']['urls']:
                                                        new_tweet['expanded_links'] = (url['expanded_url'] + " " + new_tweet['expanded_links']).strip()
                                                        new_tweet['urls'] = (url['url'] + " " + new_tweet['urls']).strip()
                                                if tweet_data['legacy']['entities']['user_mentions']:
                                                    new_tweet['is_message'] = 1
                                                    new_tweet['user_mentions'] = ""
                                                    for mentions in tweet_data['legacy']['entities']['user_mentions']:
                                                        new_tweet['user_mentions'] = ("@" + mentions['screen_name'] + " " + new_tweet['user_mentions']).strip()
                                                        if mentions['id_str'] not in users:
                                                            users[mentions['id_str']] = initialize_record("user")
                                                            users[mentions['id_str']]['user_id'] = mentions['id_str']
                                                            users[mentions['id_str']]['user_screen_name'] = mentions['screen_name']
                                                            users[mentions['id_str']]['user_name'] = mentions['name']
                                                new_tweet['clear_text'] = re.sub(r'<[^>]+>', '', new_tweet['raw_text'])
                                                new_tweet['index_on_page'] = i
                                                tweets[new_tweet['tweet_id']] = new_tweet
                                                i += 1
                except (AttributeError, KeyError) as ex:
                    logging.exception("error")

    tweet_header = [
        'index_on_page', 'tweet_id', 'tweet_permalink_path', 'in_reply_to_user',
        'full_source', 'in_reply_to_tweet', 'quoted_tweet_id', 'user_screen_name',
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
        'retweeter_api_cursor', 'views', 'blue_verified'
    ]

    user_header = [
        'user_id', 'user_screen_name', 'user_name', 'user_lang', 'user_geo_enabled',
        'user_location', 'user_timezone', 'user_utc_offset', 'user_tweets', 
        'user_followers', 'user_following', 'user_friends', 'user_favorites', 
        'user_lists', 'user_bio', 'user_verified', 'user_protected', 
        'user_withheld_in_countries', 'user_withheld_scope', 'user_created', 
        'user_image_url', 'user_url', 'restricted_to_public', 'is_deleted', 
        'is_suspended', 'item_updated_time', 'not_in_search_results', 'blue_verified'
    ]

    tweets = dict(sorted(tweets.items(), key=lambda item: item[1]['date_time']))

    base, ext = os.path.splitext(file_name)
    csv_file_name = "UPLOAD_FOLDER/" + 'tweets_' + base + '.csv'
    with open(csv_file_name, 'w', newline='', encoding='utf-8') as csv_file:
        writer = csv.writer(csv_file)
        writer.writerow(tweet_header)
        for record in tweets.values():
            row = [record.get(field, '') for field in tweet_header]
            writer.writerow(row)

    df = pd.read_csv(csv_file_name)
    df.to_csv(csv_file_name, index=False)

    csv_file_name = "UPLOAD_FOLDER/" + 'users_' + base + '.csv'
    with open(csv_file_name, 'w', newline='', encoding='utf-8') as csv_file:
        writer = csv.writer(csv_file)
        writer.writerow(user_header)
        for record in users.values():
            row = [record.get(field, '') for field in user_header]
            writer.writerow(row)

    df = pd.read_csv(csv_file_name)
    df.to_csv(csv_file_name, index=False)
    
    # Optionally remove the original HAR file to save space
    os.remove("UPLOAD_FOLDER/" + output_file)
    
    return {
        "message": f"Done! Created {len(tweets)} tweets from {len(users)} users",
        "tweets_count": len(tweets),
        "users_count": len(users),
        "tweets_file": 'tweets_' + base + '.csv',
        "users_file": 'users_' + base + '.csv'
    }

# Flask route for home page
@app.route("/")
def home():
    return "Xscraper is running successfully!"

# Flask route for processing HAR files via API
@app.route("/api/process", methods=["POST"])
def process_api():
    if 'file' not in request.files:
        return jsonify({"error": "No file part"}), 400
    
    file = request.files['file']
    if file.filename == '':
        return jsonify({"error": "No file selected"}), 400
    
    # Generate a unique filename
    import uuid
    unique_id = str(uuid.uuid4())
    filename = f"{unique_id}.har"
    
    # Save the file
    filepath = os.path.join("UPLOAD_FOLDER", filename)
    file.save(filepath)
    
    # Process the file
    result = process_har_file(filename)
    
    return jsonify(result)

# Command-line interface
if __name__ == "__main__":
    if len(sys.argv) > 1:
        # If called from command line with an argument, process the HAR file
        result = process_har_file(sys.argv[1])
        print(result["message"])
    else:
        # If called without arguments, run the Flask app
        app.run(debug=True, host='0.0.0.0', port=5000)