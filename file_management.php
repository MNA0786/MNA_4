<?php
// ==============================
// FILE INITIALIZATION & MANAGEMENT
// ==============================

function initialize_files() {
    $files = [
        CSV_FILE => CSV_HEADER,
        USERS_FILE => json_encode(['users' => []], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);
        }
    }
    
    validate_and_fix_csv_format();
    
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}

function validate_and_fix_csv_format() {
    if (!file_exists(CSV_FILE)) return;
    
    $handle = fopen(CSV_FILE, 'r');
    if ($handle === FALSE) return;
    
    $header = fgetcsv($handle);
    if ($header === FALSE) {
        fclose($handle);
        return;
    }
    
    if (count($header) != 7 || $header[0] != 'movie_name') {
        bot_log("CSV format mismatch. Migrating to 7-column format...", 'WARNING');
        
        $existing_data = [];
        while (($row = fgetcsv($handle)) !== FALSE) {
            $existing_data[] = $row;
        }
        fclose($handle);
        
        $new_handle = fopen(CSV_FILE, 'w');
        fputcsv($new_handle, ['movie_name', 'message_id', 'channel_id', 'video_path', 'quality', 'size', 'language']);
        
        foreach ($existing_data as $row) {
            $new_row = [
                $row[0] ?? '',
                $row[1] ?? '',
                $row[8] ?? $row[2] ?? '',
                $row[3] ?? '',
                $row[4] ?? 'Unknown',
                $row[5] ?? 'Unknown',
                $row[6] ?? 'Hindi'
            ];
            fputcsv($new_handle, $new_row);
        }
        fclose($new_handle);
        bot_log("CSV migrated. Total rows: " . count($existing_data));
    } else {
        fclose($handle);
    }
}

function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

function get_cached_movies() {
    global $movie_cache;
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        file_put_contents($filename, CSV_HEADER);
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                $entry = [
                    'movie_name' => trim($row[0]),
                    'message_id_raw' => isset($row[1]) ? trim($row[1]) : '',
                    'channel_id' => isset($row[2]) ? trim($row[2]) : '',
                    'video_path' => isset($row[3]) ? trim($row[3]) : '',
                    'quality' => isset($row[4]) ? trim($row[4]) : 'Unknown',
                    'size' => isset($row[5]) ? trim($row[5]) : 'Unknown',
                    'language' => isset($row[6]) ? trim($row[6]) : 'Hindi',
                    'date' => date('d-m-Y'),
                    'source_channel' => isset($row[2]) ? trim($row[2]) : ''
                ];
                
                if (is_numeric($entry['message_id_raw'])) {
                    $entry['message_id'] = intval($entry['message_id_raw']);
                } else {
                    $entry['message_id'] = null;
                }

                $data[] = $entry;

                $movie = strtolower($entry['movie_name']);
                if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
                $movie_messages[$movie][] = $entry;
            }
        }
        fclose($handle);
    }

    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    bot_log("CSV loaded - " . count($data) . " entries");
    return $data;
}

function add_movie($movie_name, $message_id, $channel_id, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi') {
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return false;
    if (empty($message_id)) return false;
    if (empty($channel_id)) return false;
    
    $date = date('d-m-Y');
    $entry = [$movie_name, $message_id, $channel_id, $video_path, $quality, $size, $language];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle === FALSE) return false;
    
    fputcsv($handle, $entry);
    fclose($handle);

    $movie = strtolower(trim($movie_name));
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id,
        'channel_id' => $channel_id,
        'video_path' => $video_path,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'date' => $date,
        'message_id' => is_numeric($message_id) ? intval($message_id) : null,
        'source_channel' => $channel_id
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    if (!empty($waiting_users[$movie])) {
        foreach ($waiting_users[$movie] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            sendMessage($user_chat_id, "🎉 Movie <b>$movie_name</b> has been added!", null, 'HTML');
        }
        unset($waiting_users[$movie]);
    }

    update_stats('total_movies', 1);
    bot_log("Movie added: $movie_name | ID: $message_id | Channel: $channel_id");
    return true;
}

function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

function get_all_movies_list() {
    return get_cached_movies();
}

function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'request_count' => 0,
            'requests' => []
        ];
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    return $users_data['users'][$user_id];
}
?>
