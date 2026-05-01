<?php
// ==============================
// SEARCH & DELIVERY SYSTEM
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
    
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
    } else {
        $source_channel = $item['channel_id'];
    }
    
    $attribution_text = "";
    if ($user_id) {
        if ($requested_by) {
            $attribution_text = "\n\n👤 <b>Requested by:</b> {$requested_by}\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        } else {
            $mention = $attribution->getUserMention($user_id);
            $attribution_text = "\n\n📥 <b>Sent to:</b> {$mention}\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        }
        $attribution->logAttribution($user_id, $item['movie_name'], 'delivered');
    }
    
    $delivery_success = false;
    
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        sendUploadDocumentAction($chat_id);
        
        $original_msg = json_decode(getMessage($source_channel, $item['message_id']), true);
        $original_caption = "";
        if ($original_msg && isset($original_msg['result']['caption'])) {
            $original_caption = $original_msg['result']['caption'];
        } elseif ($original_msg && isset($original_msg['result']['text'])) {
            $original_caption = $original_msg['result']['text'];
        }
        
        $new_caption = $original_caption . $attribution_text;
        $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id'], $new_caption), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            $delivery_success = true;
            bot_log("Movie delivered: {$item['movie_name']} to $chat_id");
        }
    }
    
    if (!$delivery_success && !empty($item['message_id_raw'])) {
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            $result = json_decode(forwardMessage($chat_id, $source_channel, $message_id_clean), true);
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                $delivery_success = true;
                if ($user_id) {
                    sendMessage($chat_id, $attribution_text, null, 'HTML', false);
                }
            }
        }
    }

    if (!$delivery_success) {
        $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
        $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
        $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
        $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
        if (!empty($item['message_id']) && !empty($source_channel)) {
            $text .= "\n🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $source_channel) . "\n";
        }
        $text .= $attribution_text;
        sendMessage($chat_id, $text, null, 'HTML', false);
        $delivery_success = true;
        update_stats('total_downloads', 1);
    }
    
    return $delivery_success;
}
?>
