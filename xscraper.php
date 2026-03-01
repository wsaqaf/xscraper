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
        'retweeter_api_cursor', 'views', 'blue_verified', 'video_views', 'user_geo_enabled'
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

    private function formatXDate($dateStr, $onlyDate = false)
    {
        if (!$dateStr)
            return null;
        try {
            // Check if it's already in Y-m-d format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return $dateStr;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateStr)) {
                return $onlyDate ? explode(' ', $dateStr)[0] : $dateStr;
            }

            $date = DateTime::createFromFormat('D M d H:i:s O Y', $dateStr);
            if ($date) {
                return $onlyDate ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
            }

            // Fallback for other formats
            $date = new DateTime($dateStr);
            return $onlyDate ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
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

        if (!isset($this->usersDb[$uId])) {
            $this->usersDb[$uId] = $this->initializeRecord("user");
            $this->usersDb[$uId]['user_id'] = $uId;
        }

        $rec = & $this->usersDb[$uId];

        // Screen Name & Name
        if (!$rec['user_screen_name'])
            $rec['user_screen_name'] = $screenName;
        if (!$rec['user_name'])
            $rec['user_name'] = $legacy['name'] ?? ($core['name'] ?? null);

        // Location & Bio
        $loc = $legacy['location'] ?? ($obj['location'] ?? ($core['location'] ?? null));
        if (is_array($loc) && isset($loc['location']))
            $loc = $loc['location'];
        if ($loc !== null && $loc !== "" && !is_array($loc)) {
            $rec['user_location'] = (string)$loc;
        }
        if (empty($rec['user_bio']))
            $rec['user_bio'] = $legacy['description'] ?? null;

        // Image
        if ($img && empty($rec['user_image_url']))
            $rec['user_image_url'] = $img;

        // Stats
        if (!empty($legacy['followers_count']))
            $rec['user_followers'] = $legacy['followers_count'];
        if (!empty($legacy['friends_count'])) {
            $rec['user_following'] = $legacy['friends_count'];
            $rec['user_friends'] = $legacy['friends_count'];
        }
        if (!empty($legacy['listed_count']))
            $rec['user_lists'] = $legacy['listed_count'];
        if (!empty($legacy['favourites_count']))
            $rec['user_favorites'] = $legacy['favourites_count'];
        if (!empty($legacy['statuses_count']))
            $rec['user_tweets'] = $legacy['statuses_count'];

        // Flags
        $verObj = $obj['verification'] ?? ($core['verification'] ?? []);
        $isVer = !empty($legacy['verified']) || !empty($verObj['verified']) || (!empty($verObj['verified_type']) && $verObj['verified_type'] !== 'Blue');
        if ($isVer)

            $rec['user_verified'] = 1;

        if (!empty($obj['is_blue_verified']) || !empty($core['is_blue_verified']))
            $rec['blue_verified'] = 1;

        if (!empty($legacy['geo_enabled']))
            $rec['user_geo_enabled'] = 1;

        // Date Created
        if (empty($rec['user_created'])) {
            $rec['user_created'] = $this->formatXDate($legacy['created_at'] ?? ($obj['created_at'] ?? ($core['created_at'] ?? null)), true);
        }

        // URL
        if (empty($rec['user_url'])) {
            $urlData = $legacy['entities']['url']['urls'][0] ?? null;
            $rec['user_url'] = $urlData['expanded_url'] ?? ($legacy['url'] ?? null);
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
                $resp = $entry['response']['content'] ?? null;
                $respText = $resp['text'] ?? null;
                if ($respText) {
                    if (($resp['encoding'] ?? '') === 'base64') {
                        $respText = base64_decode($respText);
                    }
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
                    $resp = $entry['response']['content'] ?? [];
                    $respText = $resp['text'] ?? null;
                    if (!$respText)
                        continue;

                    if (($resp['encoding'] ?? '') === 'base64') {
                        $respText = base64_decode($respText);
                    }

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
                                        'user_geo_enabled' => $uData['user_geo_enabled'] ?? 0,
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
                                        'coordinates_lat' => $leg['geo']['coordinates'][0] ?? null,
                                        'coordinates_long' => $leg['geo']['coordinates'][1] ?? null,
                                        'country' => $leg['place']['country'] ?? null,
                                        'location_fullname' => $leg['place']['full_name'] ?? null,
                                        'location_name' => $leg['place']['name'] ?? null,
                                        'location_type' => $leg['place']['place_type'] ?? null,
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

                                    if (!empty($leg['geo']) || !empty($leg['place'])) {
                                        if (isset($this->usersDb[$uId])) {
                                            $this->usersDb[$uId]['user_geo_enabled'] = 1;
                                        }
                                        if (empty($uData['user_geo_enabled'])) {
                                            $rec['user_geo_enabled'] = 1;
                                        }
                                    }

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
        $jFile = "{$base}_data_{$postfix}.json";

        $this->writeCsv($this->uploadPath . $tFile, $this->tweetHeader, $this->tweets);
        $this->writeCsv($this->uploadPath . $uFile, $this->userHeader, $this->usersDb);
        file_put_contents($this->uploadPath . $jFile, json_encode([
            'tweets' => array_values($this->tweets),
            'users' => array_values($this->usersDb)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'tweets' => $tFile,
            'users' => $uFile,
            'json' => $jFile,
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