<?php
// ==============================
// SMART SEARCH & PAGINATION UI
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = [];
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        if ($movie == $query_lower) $score = 100;
        elseif (strpos($movie, $query_lower) !== false) $score = 80 - (strlen($movie) - strlen($query_lower));
        else { similar_text($movie, $query_lower, $similarity); if ($similarity > 60) $score = $similarity; }
        if ($score > 0) {
            $qualities = [];
            foreach ($entries as $entry) $qualities[] = 'Unknown';
            $results[$movie] = [
                'score' => $score,
                'movie_name' => $movie,
                'count' => count($entries),
                'qualities' => array_unique($qualities),
                'entries' => $entries
            ];
        }
    }
    uasort($results, fn($a, $b) => $b['score'] - $a['score']);
    return array_slice($results, 0, 20);
}

function show_search_results($chat_id, $query, $page = 1) {
    $results = smart_search($query);
    $total = count($results);
    if ($total == 0) {
        sendMessage($chat_id, "😔 No results found for: <b>" . htmlspecialchars($query) . "</b>\n\n💡 Try different spelling or request the movie.", null, 'HTML');
        return false;
    }
    $per_page = 5;
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * $per_page;
    $page_results = array_slice($results, $start, $per_page, true);
    $message = "🔍 <b>Search Results for: " . htmlspecialchars($query) . "</b>\n📊 Found: $total movies\n📄 Page: $page / $total_pages\n\n";
    $i = $start + 1;
    foreach ($page_results as $movie_name => $data) {
        $icon = '🎬';
        $message .= "<b>{$i}.</b> {$icon} " . ucwords($movie_name) . "\n   └ 📦 <b>" . $data['count'] . "</b> version(s)\n\n";
        $i++;
    }
    $keyboard = ['inline_keyboard' => []];
    $keyboard['inline_keyboard'][] = [['text' => '📥 GET ALL FILES (' . $total . ' movies)', 'callback_data' => 'get_all_' . base64_encode($query)]];
    foreach ($page_results as $movie_name => $data) {
        $display = strlen($movie_name) > 35 ? substr($movie_name, 0, 32) . '...' : $movie_name;
        $keyboard['inline_keyboard'][] = [['text' => "🎬 " . $display . " (" . $data['count'] . " files)", 'callback_data' => 'sel_mov_' . base64_encode($movie_name)]];
    }
    $nav = [];
    if ($page > 1) $nav[] = ['text' => '⬅️ PREV', 'callback_data' => 'search_page_' . base64_encode($query) . '_' . ($page - 1)];
    $nav[] = ['text' => "📄 $page / $total_pages", 'callback_data' => 'current'];
    if ($page < $total_pages) $nav[] = ['text' => 'NEXT ➡️', 'callback_data' => 'search_page_' . base64_encode($query) . '_' . ($page + 1)];
    $keyboard['inline_keyboard'][] = $nav;
    $keyboard['inline_keyboard'][] = [['text' => '❌ Cancel Search', 'callback_data' => 'cancel_search']];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
    return true;
}

function show_movie_versions($chat_id, $movie_name) {
    global $movie_messages;
    $movie_key = strtolower($movie_name);
    if (!isset($movie_messages[$movie_key])) {
        sendMessage($chat_id, "❌ Movie not found: " . htmlspecialchars($movie_name));
        return;
    }
    $entries = $movie_messages[$movie_key];
    $message = "🎬 <b>" . htmlspecialchars($movie_name) . "</b>\n━━━━━━━━━━━━━━━━━━━\n📁 Available Versions:\n\n";
    $keyboard = ['inline_keyboard' => []];
    $keyboard['inline_keyboard'][] = [['text' => '📥 GET ALL VERSIONS (' . count($entries) . ' files)', 'callback_data' => 'get_all_versions_' . base64_encode($movie_name)]];
    $keyboard['inline_keyboard'][] = [['text' => '━━━━━━━━━━━━━━━━━━━', 'callback_data' => 'sep']];
    foreach ($entries as $entry) {
        $icon = '🎬';
        $keyboard['inline_keyboard'][] = [[
            'text' => "$icon Version #" . ($entry['message_id_raw'] ?? '?'),
            'callback_data' => 'download_' . $entry['message_id_raw'] . '_' . $entry['channel_id']
        ]];
    }
    $keyboard['inline_keyboard'][] = [['text' => '🔙 Back to Search Results', 'callback_data' => 'back_to_search_' . base64_encode($movie_name)]];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function advanced_search($chat_id, $query, $user_id = null) {
    if (strlen(trim($query)) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);
    show_search_results($chat_id, $query, 1);
    update_stats('total_searches', 1);
}