<?php
// ==============================
// SEARCH & DELIVERY SYSTEM - FINAL
// ==============================

function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        foreach ($entries as $entry) {
            $entry_channel_type = get_channel_type_by_id($entry['channel_id'] ?? '');
            if ($is_theater_search && $entry_channel_type == 'theater') $score += 20;
            elseif (!$is_theater_search && $entry_channel_type == 'main') $score += 10;
        }
        
        if ($movie == $query_lower) $score = 100;
        elseif (strpos($movie, $query_lower) !== false) $score = 80 - (strlen($movie) - strlen($query_lower));
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        if ($score > 0) {
            $results[$movie] = ['score' => $score, 'count' => count($entries), 'latest_entry' => end($entries)];
        }
    }
    
    uasort($results, function($a, $b) { return $b['score'] - $a['score']; });
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'हिंदी', 'चाहिए'];
    $english_keywords = ['movie', 'download', 'watch', 'search', 'find'];
    
    $hindi_score = 0; $english_score = 0;
    foreach ($hindi_keywords as $k) if (strpos($text, $k) !== false) $hindi_score++;
    foreach ($english_keywords as $k) if (stripos($text, $k) !== false) $english_score++;
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => [
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Request kar sakte hain: /request"
        ],
        'english' => [
            'searching' => "🔍 Searching... Please wait",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it: /request"
        ]
    ];
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $waiting_users;
    $q = strtolower(trim($query));
    
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters");
        return;
    }
    
    $found = smart_search($q);
    
    if (!empty($found)) {
        update_stats('successful_searches', 1);
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $msg .= "$i. $movie\n";
            $i++; if ($i > 10) break;
        }
        sendMessage($chat_id, $msg);
        
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        foreach ($top_movies as $movie) {
            $keyboard['inline_keyboard'][] = [['text' => '🍿 ' . ucwords($movie), 'callback_data' => $movie]];
        }
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
    } else {
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
}

function deliver_item_to_chat($chat_id, $item, $user_id = null, $requested_by = null) {
    global $attribution;
    
    sendTypingAction($chat_id);
    
    // Get movie name from CSV
    $movie_name = $item['movie_name'] ?? 'Unknown';
    
    // Get user mention
    $user_mention = "";
    if ($user_id) {
        if ($requested_by) {
            $user_mention = "\n\n👤 Requested by: {$requested_by}";
        } else {
            $user_info = $attribution->getUserInfo($user_id);
            $username = $user_info['username'] ?? $user_info['first_name'] ?? "User";
            $user_mention = "\n\n📥 Sent to: @{$username}";
        }
        $attribution->logAttribution($user_id, $movie_name, 'delivered');
    }
    
    // Build final message
    $text = "🎬 " . $movie_name . "\n\n";
    $text .= "🔥 Channels:\n";
    $text .= "🍿 Main: @EntertainmentTadka786\n";
    $text .= "📥 Request: @EntertainmentTadka7860\n";
    $text .= "🎭 Theater: @threater_print_movies\n";
    $text .= "📂 Backup: @ETBackup\n";
    $text .= "📺 Serial: @Entertainment_Tadka_Serial_786";
    $text .= $user_mention;
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    
    return true;
}
?>
