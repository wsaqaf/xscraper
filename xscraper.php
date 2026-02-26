<?php
/**
 * xscraper.php - Comprehensive PHP Port
 * Fully replicates xscraper.py logic and data fields.
 */
ini_set('memory_limit', '1024M'); // Increased for large HAR files

class XScraperEngine
{
    private $uploadPath;
    private $usersDb = [];
    private $tweets = [];
    private $indexCounter = 0;

    private $tweetHeader = [
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
    ];

    private $userHeader = [
        'user_id', 'user_screen_name', 'user_name', 'user_lang', 'user_geo_enabled',
        'user_location', 'user_timezone', 'user_utc_offset', 'user_tweets',
        'user_followers', 'user_following', 'user_friends', 'user_favorites',
        'user_lists', 'user_bio', 'user_verified', 'user_protected',
        'user_withheld_in_countries', 'user_withheld_scope', 'user_created',
        'user_image_url', 'user_url', 'restricted_to_public', 'is_deleted',
        'is_suspended', 'item_updated_time', 'not_in_search_results', 'blue_verified'
    ];

    public function __construct($uploadPath)
    {
        $this->uploadPath = rtrim($uploadPath, '/') . '/';
    }

    private function initializeRecord($type = "tweet")
    {
        $header = ($type === "user") ? $this->userHeader : $this->tweetHeader;
        $rec = array_fill_keys($header, null);

        if ($type === "user") {
            $rec['item_updated_time'] = date('Y-m-d H:i:s');
        }
        else {
            foreach (['retweets', 'favorites', 'replies', 'quotes', 'views', 'video_views'] as $key) {
                $rec[$key] = 0;
            }
        }
        return $rec;
    }

    private function formatXDate($dateStr)
    {
        if (!$dateStr)
            return null;
        try {
            $date = DateTime::createFromFormat('D M d H:i:s O Y', $dateStr);
            return $date ? $date->format('Y-m-d H:i:s') : $dateStr;
        }
        catch (Exception $e) {
            return $dateStr;
        }
    }

    private function cleanHtml($text)
    {
        return $text ? preg_replace('/<[^>]+>/', '', $text) : "";
    }

    private function extractAndStoreUser($obj)
    {
        if (!is_array($obj) || !isset($obj['rest_id']))
            return;

        $legacy = $obj['legacy'] ?? [];
        $core = $obj['core'] ?? [];
        $uId = (string)$obj['rest_id'];
        $screenName = $legacy['screen_name'] ?? ($core['screen_name'] ?? null);

        if (!$screenName)
            return;

        $img = $legacy['profile_image_url_https'] ?? ($obj['profile_image_url_https'] ?? ($obj['avatar']['image_url'] ?? null));

        if (!isset($this->usersDb[$uId]) || ($img && empty($this->usersDb[$uId]['user_image_url']))) {
            $rec = $this->initializeRecord("user");
            $rec['user_id'] = $uId;
            $rec['user_screen_name'] = $screenName;
            $rec['user_name'] = $legacy['name'] ?? ($core['name'] ?? null);
            $rec['user_location'] = $legacy['location'] ?? null;
            $rec['user_image_url'] = $img;
            $rec['user_bio'] = $legacy['description'] ?? null;
            $rec['user_followers'] = $legacy['followers_count'] ?? 0;
            $rec['user_following'] = $legacy['friends_count'] ?? 0;
            $rec['user_friends'] = $legacy['friends_count'] ?? 0;
            $rec['user_lists'] = $legacy['listed_count'] ?? 0;
            $rec['user_favorites'] = $legacy['favourites_count'] ?? 0;
            $rec['user_tweets'] = $legacy['statuses_count'] ?? 0;
            $rec['user_verified'] = (isset($legacy['verified']) && $legacy['verified']) ? 1 : 0;
            $rec['blue_verified'] = (isset($obj['is_blue_verified']) && $obj['is_blue_verified']) ? 1 : 0;
            $rec['user_created'] = $this->formatXDate($legacy['created_at'] ?? null);

            $this->usersDb[$uId] = $rec;
        }
    }

    private function recursiveSignatureScan($data)
    {
        if (is_array($data)) {
            if (isset($data['rest_id']))
                $this->extractAndStoreUser($data);
            foreach ($data as $v)
                $this->recursiveSignatureScan($v);
        }
    }

    public function processHar($filePath)
    {
        $jsonRaw = file_get_contents($filePath);
        $content = json_decode($jsonRaw, true);
        unset($jsonRaw);

        if (!$content)
            return false;

        // Phase 1: Mine Users
        if (isset($content['log']['entries'])) {
            foreach ($content['log']['entries'] as $entry) {
                $respText = $entry['response']['content']['text'] ?? null;
                if ($respText) {
                    $data = json_decode($respText, true);
                    if ($data)
                        $this->recursiveSignatureScan($data);
                }
            }
        }

        // Phase 2: Process Tweets
        if (isset($content['log']['entries'])) {
            foreach ($content['log']['entries'] as $entry) {
                $url = $entry['request']['url'] ?? '';
                if (preg_match('/(\/graphql\/|SearchTimeline|UserTweets)/', $url)) {
                    $respText = $entry['response']['content']['text'] ?? null;
                    if (!$respText)
                        continue; // Skip if no content

                    $data = json_decode($respText, true);
                    if (!$data)
                        continue;

                    $instructions = $data['data']['search_by_raw_query']['search_timeline']['timeline']['instructions'] ??
                        ($data['data']['user']['result']['timeline_v2']['timeline']['instructions'] ??
                        ($data['data']['threaded_conversation_with_injections_v2']['instructions'] ?? []));

                    foreach ($instructions as $ins) {
                        if (($ins['type'] ?? '') === 'TimelineAddEntries') {
                            foreach ($ins['entries'] ?? [] as $item) {
                                $eid = $item['entryId'] ?? '';
                                if (strpos($eid, 'tweet-') === false && strpos($eid, 'promoted') === false)
                                    continue;

                                $tRes = $item['content']['itemContent']['tweet_results']['result'] ?? null;
                                if (($tRes['__typename'] ?? '') === 'TweetWithVisibilityResults')
                                    $tRes = $tRes['tweet'];

                                if (!$tRes || !isset($tRes['legacy']))
                                    continue;

                                $leg = $tRes['legacy'];
                                $tId = (string)$tRes['rest_id'];

                                if (isset($tRes['core']['user_results']['result'])) {
                                    $this->extractAndStoreUser($tRes['core']['user_results']['result']);
                                }

                                if ($tId && !isset($this->tweets[$tId])) {
                                    $uId = (string)($tRes['core']['user_results']['result']['rest_id'] ?? '');
                                    $uData = $this->usersDb[$uId] ?? [];

                                    $fullDate = $this->formatXDate($leg['created_at'] ?? null);
                                    $rawText = $leg['full_text'] ?? '';

                                    // Extraction logic
                                    preg_match_all('/#(\w+)/', $rawText, $hMatches);
                                    $entities = $leg['entities'] ?? [];
                                    $linksList = [];
                                    $expandedList = [];
                                    if (isset($entities['urls'])) {
                                        foreach ($entities['urls'] as $u) {
                                            if (!empty($u['url']))
                                                $linksList[] = $u['url'];
                                            if (!empty($u['expanded_url']))
                                                $expandedList[] = $u['expanded_url'];
                                        }
                                    }

                                    $mentionsList = [];
                                    if (isset($entities['user_mentions'])) {
                                        foreach ($entities['user_mentions'] as $m) {
                                            if (!empty($m['screen_name']))
                                                $mentionsList[] = $m['screen_name'];
                                        }
                                    }

                                    $mediaSource = $leg['extended_entities'] ?? ($leg['entities'] ?? []);
                                    $mediaLinks = [];
                                    $hasImg = null;
                                    $hasVid = null;
                                    $vViews = 0;
                                    if (isset($mediaSource['media'])) {
                                        foreach ($mediaSource['media'] as $m) {
                                            $mediaLinks[] = $m['media_url_https'] ?? '';
                                            if (($m['type'] ?? '') === 'photo')
                                                $hasImg = "1";
                                            if (in_array($m['type'] ?? '', ['video', 'animated_gif']))
                                                $hasVid = "1";
                                            $vViews += $m['mediaStats']['viewCount'] ?? 0;
                                        }
                                    }

                                    $rec = $this->initializeRecord("tweet");
                                    $rec = array_merge($rec, [
                                        'index_on_page' => $this->indexCounter++,
                                        'tweet_id' => $tId,
                                        'user_id' => $uId,
                                        'user_screen_name' => $uData['user_screen_name'] ?? null,
                                        'user_name' => $uData['user_name'] ?? null,
                                        'user_location' => $uData['user_location'] ?? null,
                                        'user_image_url' => $uData['user_image_url'] ?? null,
                                        'user_bio' => $uData['user_bio'] ?? null,
                                        'user_verified' => $uData['user_verified'] ?? 0,
                                        'blue_verified' => $uData['blue_verified'] ?? 0,
                                        'raw_text' => $rawText,
                                        'clear_text' => $this->cleanHtml($rawText),
                                        'date_time' => $fullDate,
                                        'tweet_date' => $fullDate ? explode(' ', $fullDate)[0] . " 00:00:00" : null,
                                        'tweet_language' => $leg['lang'] ?? null,
                                        'in_reply_to_tweet' => $leg['in_reply_to_status_id_str'] ?? null,
                                        'in_reply_to_user' => $leg['in_reply_to_screen_name'] ?? null,
                                        'is_reply' => (!empty($leg['in_reply_to_status_id_str'])) ? "1" : null,
                                        'is_retweet' => (!empty($leg['retweeted_status_id_str'])) ? "1" : null,
                                        'retweeted_tweet_id' => $leg['retweeted_status_id_str'] ?? null,
                                        'is_quote' => (!empty($leg['is_quote_status']) || !empty($leg['quoted_status_id_str'])) ? "1" : null,
                                        'quoted_tweet_id' => $leg['quoted_status_id_str'] ?? null,
                                        'hashtags' => !empty($hMatches[1]) ? implode(' ', $hMatches[1]) : null,
                                        'has_link' => (!empty($linksList)) ? "1" : null,
                                        'links' => implode(' ', $linksList),
                                        'expanded_links' => implode(' ', $expandedList),
                                        'user_mentions' => implode(' ', $mentionsList),
                                        'retweets' => $leg['retweet_count'] ?? 0,
                                        'favorites' => $leg['favorite_count'] ?? 0,
                                        'replies' => $leg['reply_count'] ?? 0,
                                        'quotes' => $leg['quote_count'] ?? 0,
                                        'views' => $tRes['views']['count'] ?? 0,
                                        'source' => $this->cleanHtml($tRes['source'] ?? ''),
                                        'media_link' => implode(' ', $mediaLinks),
                                        'has_image' => $hasImg,
                                        'has_video' => $hasVid,
                                        'video_views' => $vViews > 0 ? $vViews : 0,
                                        'tweet_permalink_path' => "https://x.com/" . ($uData['user_screen_name'] ?? 'i') . "/status/$tId"
                                    ]);
                                    $this->tweets[$tId] = $rec;
                                }
                            }
                        }
                    }
                }
            }
        }

        $base = pathinfo($filePath, PATHINFO_FILENAME);
        $postfix = date('Ymd_His');
        $tFile = "{$base}_tweets_{$postfix}.csv";
        $uFile = "{$base}_users_{$postfix}.csv";

        $this->writeCsv($this->uploadPath . $tFile, $this->tweetHeader, $this->tweets);
        $this->writeCsv($this->uploadPath . $uFile, $this->userHeader, $this->usersDb);

        return [
            'tweets' => $tFile,
            'users' => $uFile,
            'tweet_count' => count($this->tweets),
            'user_count' => count($this->usersDb)
        ];
    }

    private function writeCsv($path, $headers, $data)
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, $headers, ",", "\"", "\\");
        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fp, $line, ",", "\"", "\\");
        }
        fclose($fp);
    }
}