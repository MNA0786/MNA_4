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

function build_movie_card($item, $channel_link = null) {
    $movie_name = htmlspecialchars($item['movie_name'] ?? 'Unknown');
    $quality = htmlspecialchars($item['quality'] ?? 'Unknown');
    $size = htmlspecialchars($item['size'] ?? 'Unknown');
    $language = htmlspecialchars($item['language'] ?? 'Hindi');
    $message_id = $item['message_id'] ?? $item['message_id_raw'] ?? 'N/A';
    $channel_id = $item['channel_id'] ?? 'N/A';
    
    $card = "🎬 <b>" . $movie_name . "</b>\n";
    $card .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $card .= "📊 <b>Quality:</b> " . $quality . "\n";
    $card .= "💾 <b>Size:</b> " . $size . "\n";
    $card .= "🗣️ <b>Language:</b> " . $language . "\n";
    $card .= "🆔 <b>Message ID:</b> <code>" . $message_id . "</code>\n";
    $card .= "📢 <b>Channel ID:</b> <code>" . $channel_id . "</code>\n";
    $card .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if ($channel_link) {
        $card .= "🔗 <b>Direct Link:</b> " . $channel_link . "\n";
    }
    
    return $card;
}

function deliver_item_to_chat($chat_id, $item, $user_id = null, $requested_by = null) {
    global $attribution;
    
    sendTypingAction($chat_id);
    
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
    } else {
        $source_channel = $item['channel_id'];
    }
    
    $channel_link = get_direct_channel_link($item['message_id'] ?? $item['message_id_raw'], $source_channel);
    
    // Prepare attribution text
    $attribution_text = "";
    if ($user_id) {
        if ($requested_by) {
            $attribution_text = "\n👤 <b>Requested by:</b> {$requested_by}\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        } else {
            $mention = $attribution->getUserMention($user_id);
            $attribution_text = "\n📥 <b>Sent to:</b> {$mention}\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        }
        $attribution->logAttribution($user_id, $item['movie_name'], 'delivered');
    }
    
    $delivery_success = false;
    $message_id_to_copy = null;
    
    // Get numeric message ID
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $message_id_to_copy = $item['message_id'];
    } elseif (!empty($item['message_id_raw']) && is_numeric($item['message_id_raw'])) {
        $message_id_to_copy = $item['message_id_raw'];
    }
    
    if ($message_id_to_copy) {
        sendUploadDocumentAction($chat_id);
        
        // Try to get original message to check if it has media
        $original_msg = json_decode(getMessage($source_channel, $message_id_to_copy), true);
        $has_media = false;
        $original_caption = "";
        
        if ($original_msg && isset($original_msg['result'])) {
            $result = $original_msg['result'];
            
            if (isset($result['photo']) || isset($result['video']) || isset($result['document']) || isset($result['animation'])) {
                $has_media = true;
            }
            
            if (isset($result['caption'])) {
                $original_caption = $result['caption'];
            }
        }
        
        if ($has_media) {
            // Message has media, copy it with attribution in caption
            $final_caption = $original_caption . $attribution_text;
            $copy_result = json_decode(copyMessage($chat_id, $source_channel, $message_id_to_copy, $final_caption), true);
            
            if ($copy_result && $copy_result['ok']) {
                $delivery_success = true;
                bot_log("Movie delivered (with media): {$item['movie_name']} to $chat_id");
            }
        }
        
        // If no media or copy failed, send as text message with movie card
        if (!$delivery_success) {
            $movie_card = build_movie_card($item, $channel_link);
            $final_text = $movie_card . "\n" . $attribution_text;
            sendMessage($chat_id, $final_text, null, 'HTML', false);
            $delivery_success = true;
            bot_log("Movie delivered (as text): {$item['movie_name']} to $chat_id");
        }
    }
    
    // Final fallback - send simple text
    if (!$delivery_success) {
        $movie_card = build_movie_card($item, $channel_link);
        $final_text = $movie_card . "\n" . $attribution_text;
        sendMessage($chat_id, $final_text, null, 'HTML', false);
        $delivery_success = true;
        bot_log("Movie delivered (fallback): {$item['movie_name']} to $chat_id");
    }
    
    if ($delivery_success) {
        update_stats('total_downloads', 1);
    }
    
    return $delivery_success;
}
?>
