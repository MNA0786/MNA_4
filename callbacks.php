<?php
// ==============================
// CALLBACK QUERY HANDLER
// ==============================
function handle_callback_query($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $data = $callback_query['data'];
    $message_id = $callback_query['message']['message_id'];
    global $movie_messages;

    // Movie selection
    if (isset($movie_messages[strtolower($data)])) {
        foreach ($movie_messages[strtolower($data)] as $entry) deliver_item_to_chat($chat_id, $entry);
        sendMessage($chat_id, "✅ '$data' delivered!");
        answerCallbackQuery($callback_query['id'], "Sent");
    }
    // Pagination for totalupload
    elseif (strpos($data, 'tu_prev_') === 0) totalupload_controller($chat_id, (int)str_replace('tu_prev_', '', $data));
    elseif (strpos($data, 'tu_next_') === 0) totalupload_controller($chat_id, (int)str_replace('tu_next_', '', $data));
    elseif (strpos($data, 'tu_view_') === 0) {
        $page = (int)str_replace('tu_view_', '', $data);
        $all = get_cached_movies();
        $start = ($page - 1) * ITEMS_PER_PAGE;
        $slice = array_slice($all, $start, ITEMS_PER_PAGE);
        foreach ($slice as $item) deliver_item_to_chat($chat_id, $item);
        answerCallbackQuery($callback_query['id'], "Sending page $page");
    }
    elseif ($data === 'tu_stop') {
        sendMessage($chat_id, "✅ Pagination stopped. Use /totalupload to start again.");
        answerCallbackQuery($callback_query['id'], "Stopped");
    }
    // Search pagination
    elseif (strpos($data, 'search_page_') === 0) {
        $parts = explode('_', $data, 4);
        show_search_results($chat_id, base64_decode($parts[2]), (int)$parts[3]);
        answerCallbackQuery($callback_query['id'], "Page " . $parts[3]);
    }
    elseif (strpos($data, 'get_all_') === 0) {
        $q = base64_decode(str_replace('get_all_', '', $data));
        $res = smart_search($q);
        $cnt = 0;
        foreach ($res as $movie) foreach ($movie['entries'] as $e) { deliver_item_to_chat($chat_id, $e); $cnt++; }
        sendMessage($chat_id, "✅ Sent $cnt files for: $q");
        answerCallbackQuery($callback_query['id'], "Sent $cnt files");
    }
    elseif (strpos($data, 'sel_mov_') === 0) {
        show_movie_versions($chat_id, base64_decode(str_replace('sel_mov_', '', $data)));
        answerCallbackQuery($callback_query['id'], "Loading versions...");
    }
    elseif (strpos($data, 'get_all_versions_') === 0) {
        $movie = base64_decode(str_replace('get_all_versions_', '', $data));
        $key = strtolower($movie);
        if (isset($movie_messages[$key])) {
            $cnt = 0;
            foreach ($movie_messages[$key] as $e) { deliver_item_to_chat($chat_id, $e); $cnt++; }
            sendMessage($chat_id, "✅ Sent $cnt versions of: $movie");
            answerCallbackQuery($callback_query['id'], "Sent $cnt files");
        } else answerCallbackQuery($callback_query['id'], "Not found", true);
    }
    elseif (strpos($data, 'download_') === 0) {
        $parts = explode('_', $data);
        $msg_id = $parts[1];
        $ch_id = $parts[2];
        foreach ($movie_messages as $entries) foreach ($entries as $e) {
            if ($e['message_id_raw'] == $msg_id && $e['channel_id'] == $ch_id) {
                deliver_item_to_chat($chat_id, $e);
                answerCallbackQuery($callback_query['id'], "✅ File sent!");
                break 2;
            }
        }
    }
    elseif (strpos($data, 'back_to_search_') === 0) {
        show_search_results($chat_id, base64_decode(str_replace('back_to_search_', '', $data)), 1);
        answerCallbackQuery($callback_query['id'], "Back to search");
    }
    elseif ($data === 'cancel_search') {
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "🔍 Search cancelled. Type any movie name to start new search.");
        answerCallbackQuery($callback_query['id'], "Cancelled");
    }
    // Admin panel
    elseif ($data === 'admin_panel' && $user_id == ADMIN_ID) {
        $msg = "🛠️ <b>Admin Panel</b>\n\n📊 Quick Actions:\n• /stats\n• /checkcsv\n• /pending_request\n• /bulk_approve\n• /checkdate\n• /cleanup\n• /maintenance";
        $kb = ['inline_keyboard' => [
            [['text' => '📊 Stats', 'callback_data' => 'admin_stats'], ['text' => '🎬 Check CSV', 'callback_data' => 'admin_checkcsv']],
            [['text' => '📝 Pending', 'callback_data' => 'admin_pending'], ['text' => '✅ Bulk', 'callback_data' => 'admin_bulk']],
            [['text' => '🧹 Cleanup', 'callback_data' => 'admin_cleanup'], ['text' => '🔧 Maintenance', 'callback_data' => 'admin_maintenance']],
            [['text' => '🔙 Back', 'callback_data' => 'back_to_start']]
        ]];
        sendMessage($chat_id, $msg, $kb, 'HTML');
        answerCallbackQuery($callback_query['id'], "Admin Panel");
    }
    elseif ($data === 'admin_stats' && $user_id == ADMIN_ID) { admin_stats($chat_id); answerCallbackQuery($callback_query['id'], "Stats"); }
    elseif ($data === 'admin_checkcsv' && $user_id == ADMIN_ID) { show_csv_data($chat_id, false); answerCallbackQuery($callback_query['id'], "CSV"); }
    elseif ($data === 'admin_pending' && $user_id == ADMIN_ID) { pending_requests($chat_id); answerCallbackQuery($callback_query['id'], "Pending"); }
    elseif ($data === 'admin_bulk' && $user_id == ADMIN_ID) { bulk_approve($chat_id); answerCallbackQuery($callback_query['id'], "Bulk"); }
    elseif ($data === 'admin_cleanup' && $user_id == ADMIN_ID) { perform_cleanup($chat_id); answerCallbackQuery($callback_query['id'], "Cleanup"); }
    elseif ($data === 'admin_maintenance' && $user_id == ADMIN_ID) { sendMessage($chat_id, "🔧 Use /maintenance on/off"); answerCallbackQuery($callback_query['id'], "Maintenance"); }
    elseif ($data === 'back_to_start') { send_start_message($chat_id, $user_id); answerCallbackQuery($callback_query['id'], "Back"); }
    elseif ($data === 'help_command') { send_help_message($chat_id); answerCallbackQuery($callback_query['id'], "Help"); }
    elseif ($data === 'download_latest') {
        $movies = get_cached_movies();
        foreach (array_slice(array_reverse($movies), 0, 10) as $m) deliver_item_to_chat($chat_id, $m);
        answerCallbackQuery($callback_query['id'], "Sending latest");
    }
    elseif ($data === 'browse_all') { totalupload_controller($chat_id, 1); answerCallbackQuery($callback_query['id'], "Browse all"); }
    else { answerCallbackQuery($callback_query['id'], "❌ Not available"); }
}