<?php
// ==============================
// PAGINATION SYSTEM FOR BROWSING
// ==============================

function paginate_movies(array $all, int $page, array $filters = []): array {
    if (!empty($filters)) $all = apply_movie_filters($all, $filters);
    $total = count($all);
    if ($total === 0) {
        return ['total' => 0, 'total_pages' => 1, 'page' => 1, 'slice' => [], 'filters' => $filters, 'has_next' => false, 'has_prev' => false];
    }
    $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    return ['total' => $total, 'total_pages' => $total_pages, 'page' => $page, 'slice' => array_slice($all, $start, ITEMS_PER_PAGE), 'filters' => $filters, 'has_next' => $page < $total_pages, 'has_prev' => $page > 1];
}

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    $nav_row[] = ['text' => "📄 {$page}/{$total_pages}", 'callback_data' => 'noop'];
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
    }
    $kb['inline_keyboard'][] = $nav_row;
    
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $kb['inline_keyboard'][] = $action_row;
    
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    if (!$session_id) $session_id = uniqid('sess_', true);
    $pg = paginate_movies($all, (int)$page, $filters);
    
    $title = "🎬 <b>All Movies</b>\n\n";
    $title .= "📊 Total: <b>{$pg['total']}</b> | Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n\n";
    
    $i = ($pg['page'] - 1) * ITEMS_PER_PAGE + 1;
    foreach ($pg['slice'] as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_id'] ?? MAIN_CHANNEL_ID);
        $title .= "<b>{$i}.</b> $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $title .= "   🏷️ " . ($movie['quality'] ?? 'Unknown') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    delete_pagination_message($chat_id, $session_id);
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
}

function apply_movie_filters($movies, $filters) {
    if (empty($filters)) return $movies;
    $filtered = [];
    foreach ($movies as $movie) {
        $pass = true;
        foreach ($filters as $key => $value) {
            if ($key == 'quality' && stripos($movie['quality'] ?? '', $value) === false) $pass = false;
            if (!$pass) break;
        }
        if ($pass) $filtered[] = $movie;
    }
    return $filtered;
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    if (!isset($user_pagination_sessions[$session_id])) $user_pagination_sessions[$session_id] = [];
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    if (isset($user_pagination_sessions[$session_id]) && isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        deleteMessage($chat_id, $user_pagination_sessions[$session_id]['last_message_id']);
    }
}

function batch_download_with_progress($chat_id, $movies, $page_num, $user_id = null) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, "📦 Sending page {$page_num} info...\n\n0% complete", null, 'HTML', false);
    $progress_id = $progress_msg['result']['message_id'];
    $success = 0; $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        if ($i % 2 == 0 || $i == $total - 1) {
            $percent = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 Sending page {$page_num} info...\n\nProgress: {$percent}%\n✅ Sent: {$success}/{$total}", null, true);
        }
        try {
            if (deliver_item_to_chat($chat_id, $movies[$i], $user_id)) $success++; else $failed++;
        } catch (Exception $e) { $failed++; }
        if ($i < $total - 1) time_nanosleep(0, 500000000);
    }
    editMessage($chat_id, $progress_id, "✅ Complete!\n\n✅ Success: {$success}\n❌ Failed: {$failed}\n\n🔗 Join: " . MAIN_CHANNEL, null, true);
}
?>
