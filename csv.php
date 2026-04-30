<?php
// ==============================
// CSV FUNCTIONS (3-COLUMN LOCKED)
// ==============================
function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,channel_id\n",
        USERS_FILE => json_encode(['users' => [], 'total_requests' => 0, 'message_logs' => [], 'daily_stats' => []], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode(['total_movies' => 0, 'total_users' => 0, 'total_searches' => 0, 'total_downloads' => 0, 'successful_searches' => 0, 'failed_searches' => 0, 'daily_activity' => [], 'last_updated' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode(['requests' => [], 'pending_approval' => [], 'completed_requests' => [], 'user_request_count' => []], JSON_PRETTY_PRINT)
    ];
    foreach ($files as $file => $content) {
        if (!file_exists($file)) file_put_contents($file, $content);
    }
    if (!file_exists(BACKUP_DIR)) mkdir(BACKUP_DIR, 0777, true);
    if (!file_exists(LOG_FILE)) file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
}
initialize_files();

function bot_log($message, $type = 'INFO') {
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $type: $message\n", FILE_APPEND);
}

function get_cached_movies() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) return $movie_cache['data'];
    $movie_cache = ['data' => load_and_clean_csv(), 'timestamp' => time()];
    return $movie_cache['data'];
}

function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,channel_id\n");
        return [];
    }
    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $entry = [
                    'movie_name' => trim($row[0]),
                    'message_id_raw' => trim($row[1]),
                    'channel_id' => trim($row[2]),
                    'message_id' => is_numeric(trim($row[1])) ? intval(trim($row[1])) : null,
                    'source_channel' => trim($row[2])
                ];
                $data[] = $entry;
                $movie_key = strtolower($entry['movie_name']);
                if (!isset($movie_messages[$movie_key])) $movie_messages[$movie_key] = [];
                $movie_messages[$movie_key][] = $entry;
            }
        }
        fclose($handle);
    }
    $handle = fopen($filename, "w");
    fputcsv($handle, ['movie_name', 'message_id', 'channel_id']);
    foreach ($data as $row) {
        fputcsv($handle, [$row['movie_name'], $row['message_id_raw'], $row['channel_id']]);
    }
    fclose($handle);
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    bot_log("CSV loaded - " . count($data) . " movies");
    return $data;
}

function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) $stats['daily_activity'][$today] = ['searches' => 0, 'downloads' => 0, 'users' => 0];
    if ($field == 'total_searches') $stats['daily_activity'][$today]['searches'] += $increment;
    if ($field == 'total_downloads') $stats['daily_activity'][$today]['downloads'] += $increment;
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null
        ];
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        bot_log("New user registered: $user_id");
    }
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    return $users_data['users'][$user_id];
}