<?php
// ==============================
// SEARCH PAGINATION SYSTEM
// ==============================

function search_movies($query, $movies) {
    $query_lower = strtolower(trim($query));
    $results = [];
    
    foreach ($movies as $movie) {
        $movie_name_lower = strtolower($movie['movie_name']);
        if ($movie_name_lower == $query_lower || strpos($movie_name_lower, $query_lower) !== false) {
            $results[] = $movie;
        } else {
            similar_text($movie_name_lower, $query_lower, $similarity);
            if ($similarity > 60) $results[] = $movie;
        }
    }
    
    usort($results, function($a, $b) {
        $score_a = stripos($a['quality'] ?? '', '1080') !== false ? 100 : 50;
        $score_b = stripos($b['quality'] ?? '', '1080') !== false ? 100 : 50;
        return $score_b - $score_a;
    });
    
    return $results;
}

function send_paginated_results($chat_id, $query, $page = 1, $user_id = null) {
    $movies = get_cached_movies();
    $results = search_movies($query, $movies);

    if (empty($results)) {
        sendMessage($chat_id, "❌ No results found for: <b>" . htmlspecialchars($query) . "</b>", null, 'HTML');
        return;
    }

    $per_page = ITEMS_PER_PAGE;
    $total = count($results);
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * $per_page;
    $items = array_slice($results, $start, $per_page);

    $text = "🔍 <b>Results for:</b> <code>" . htmlspecialchars($query) . "</code>\n\n";
    $text .= "📊 Found: {$total} | Page: {$page}/{$total_pages}\n\n";
    
    $counter = $start + 1;
    foreach ($items as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_id'] ?? MAIN_CHANNEL_ID);
        $text .= "<b>{$counter}.</b> {$channel_icon} " . htmlspecialchars($movie['movie_name']) . "\n";
        $text .= "   📊 " . ($movie['quality'] ?? 'Unknown') . " | 💾 " . ($movie['size'] ?? 'Unknown') . "\n\n";
        $counter++;
    }

    $keyboard = ['inline_keyboard' => []];
    $keyboard['inline_keyboard'][] = [['text' => "📥 Get All {$total} Files", 'callback_data' => "all_" . base64_encode($query)]];
    
    foreach ($items as $movie) {
        $msg_id = $movie['message_id'];
        $name = strlen($movie['movie_name']) > 30 ? substr($movie['movie_name'], 0, 27) . '...' : $movie['movie_name'];
        $keyboard['inline_keyboard'][] = [['text' => "📁 " . $name, 'callback_data' => "get_{$msg_id}"]];
    }
    
    $pagination = [];
    if ($page > 1) $pagination[] = ['text' => '◀️ Prev', 'callback_data' => "page_" . ($page - 1) . "_" . base64_encode($query)];
    $pagination[] = ['text' => "📄 {$page}/{$total_pages}", 'callback_data' => 'noop'];
    if ($page < $total_pages) $pagination[] = ['text' => 'Next ▶️', 'callback_data' => "page_" . ($page + 1) . "_" . base64_encode($query)];
    $keyboard['inline_keyboard'][] = $pagination;
    $keyboard['inline_keyboard'][] = [['text' => '❌ Close', 'callback_data' => 'close_search']];
    
    if ($page == 1) {
        sendMessage($chat_id, $text, $keyboard, 'HTML');
    } else {
        global $last_search_message_id;
        if (isset($last_search_message_id[$chat_id])) {
            editMessage($chat_id, $last_search_message_id[$chat_id], $text, $keyboard);
        }
    }
}

function safe_deliver_movies($chat_id, $movies, $user_id = null) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress = sendMessage($chat_id, "📦 Sending {$total} movies...\n\n0% complete", null, 'HTML', false);
    $progress_id = $progress['result']['message_id'];
    $success = 0;
    
    for ($i = 0; $i < $total; $i++) {
        if ($i % 2 == 0 || $i == $total - 1) {
            $percent = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 Sending {$total} movies...\n\nProgress: {$percent}%\n✅ Sent: {$success}/{$total}", null, true);
        }
        if (deliver_item_to_chat($chat_id, $movies[$i], $user_id)) $success++;
        if ($i < $total - 1) time_nanosleep(0, 500000000);
    }
    editMessage($chat_id, $progress_id, "✅ Complete!\n\n✅ Sent: {$success}/{$total}\n\n🔗 Join: " . MAIN_CHANNEL, null, true);
}

function handle_pagination_callback($data, $chat_id, $message_id, $user_id = null) {
    $parts = explode('_', $data, 3);
    if (count($parts) < 3) return;
    $page = intval($parts[1]);
    $query = base64_decode($parts[2]);
    if (empty($query)) return;
    global $last_search_message_id;
    $last_search_message_id[$chat_id] = $message_id;
    send_paginated_results($chat_id, $query, $page, $user_id);
}

function advanced_search_with_pagination($chat_id, $query, $user_id = null) {
    global $waiting_users;
    $q = strtolower(trim($query));
    if (strlen($q) < 2) { sendMessage($chat_id, "❌ Please enter at least 2 characters"); return; }
    
    $movies = get_cached_movies();
    $results = search_movies($q, $movies);
    
    if (!empty($results)) {
        update_stats('successful_searches', 1);
        send_paginated_results($chat_id, $q, 1, $user_id);
    } else {
        update_stats('failed_searches', 1);
        sendMessage($chat_id, "😔 Movie not found!\n\n📝 Request: /request " . $query, null, 'HTML');
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    update_stats('total_searches', 1);
}
?>
