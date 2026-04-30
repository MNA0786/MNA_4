<?php
// ==============================
// UTILITY FUNCTIONS (BROWSE, CHANNEL)
// ==============================
function totalupload_controller($chat_id, $page = 1) {
    $all = get_cached_movies();
    if (empty($all)) { sendMessage($chat_id, "📭 No movies found!"); return; }
    $total = count($all);
    $total_pages = min(7, ceil($total / ITEMS_PER_PAGE));
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    $slice = array_slice($all, $start, ITEMS_PER_PAGE);
    $msg = "🎬 <b>Movie Browser</b>\n📊 Total: $total movies\n📄 Page: $page / $total_pages\n\n";
    $i = $start + 1;
    foreach ($slice as $m) $msg .= "<b>{$i}.</b> 🎬 " . htmlspecialchars($m['movie_name']) . "\n\n";
    $keyboard = ['inline_keyboard' => []];
    $nav = [];
    if ($page > 1) $nav[] = ['text' => '⬅️ PREV', 'callback_data' => 'tu_prev_' . ($page - 1)];
    $nav[] = ['text' => "📄 $page / $total_pages", 'callback_data' => 'current'];
    if ($page < $total_pages) $nav[] = ['text' => 'NEXT ➡️', 'callback_data' => 'tu_next_' . ($page + 1)];
    if (!empty($nav)) $keyboard['inline_keyboard'][] = $nav;
    $keyboard['inline_keyboard'][] = [['text' => '📥 Send Page', 'callback_data' => 'tu_view_' . $page], ['text' => '❌ Close', 'callback_data' => 'tu_stop']];
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function show_latest_movies($chat_id, $limit = 10) {
    $movies = get_cached_movies();
    $latest = array_reverse(array_slice($movies, -$limit));
    if (empty($latest)) { sendMessage($chat_id, "📭 No movies found!"); return; }
    $msg = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    foreach ($latest as $m) $msg .= "$i. 🎬 <b>" . htmlspecialchars($m['movie_name']) . "</b>\n\n";
    $keyboard = ['inline_keyboard' => [[['text' => '📥 Get All Latest', 'callback_data' => 'download_latest'], ['text' => '📊 Browse All', 'callback_data' => 'browse_all']]]];
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function show_trending_movies($chat_id) {
    $movies = get_cached_movies();
    $trending = array_slice(array_reverse($movies), 0, 10);
    if (empty($trending)) { sendMessage($chat_id, "📭 No trending movies!"); return; }
    $msg = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    foreach ($trending as $m) $msg .= "$i. 🎬 <b>" . htmlspecialchars($m['movie_name']) . "</b>\n\n";
    sendMessage($chat_id, $msg, null, 'HTML');
}

function show_theater_movies($chat_id) {
    $all = get_cached_movies();
    $filtered = array_filter($all, fn($m) => str_contains(strtolower($m['movie_name'] ?? ''), 'theater'));
    $filtered = array_slice($filtered, 0, 10);
    if (empty($filtered)) { sendMessage($chat_id, "❌ No theater movies found!"); return; }
    $msg = "🎭 <b>Theater Print Movies</b>\n\n";
    $i = 1;
    foreach ($filtered as $m) $msg .= "$i. 🎬 <b>" . htmlspecialchars($m['movie_name']) . "</b>\n\n";
    sendMessage($chat_id, $msg, null, 'HTML');
}

function show_all_channels_info($chat_id) {
    $message = "📢 <b>Entertainment Tadka - All Channels & Groups</b>\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🍿 <b>PUBLIC CHANNEL 1 (Main)</b>\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📌 Username: @EntertainmentTadka786\n🎬 Content: Latest movies, web-series, Hindi dubbed\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📺 <b>PUBLIC CHANNEL 2 (Serials)</b>\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📌 Username: @Entertainment_Tadka_Serial_786\n📺 Content: TV serials, daily soaps, episodes\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🎭 <b>PUBLIC CHANNEL 3 (Theater Prints)</b>\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📌 Username: @threater_print_movies\n🎬 Content: HDTC, HDTS, theater quality prints\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔒 <b>PUBLIC CHANNEL 4 (Backup)</b>\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📌 Username: @ETBackup\n💾 Content: Auto backups, data protection\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📥 <b>REQUEST GROUP (Support)</b>\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📌 Username: @EntertainmentTadka7860\n💬 Purpose: Movie requests, bug reports, support\n✅ Auto-notification: Get notified when requested movies are added\n\n💡 How to Use:\n• Join all public channels for latest updates\n• Use request group for movie requests\n• Type any movie name to search\n• Use /help for all commands";
    $keyboard = ['inline_keyboard' => [
        [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'], ['text' => '📺 Serials', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
        [['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'], ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']],
        [['text' => '📥 Request Group', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🔍 Search', 'switch_inline_query_current_chat' => '']]
    ]];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) return false;
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $username = $users_data['users'][$user_id]['username'] ?? '';
    $first_name = $users_data['users'][$user_id]['first_name'] ?? '';
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id, 'user_id' => $user_id, 'username' => $username, 'first_name' => $first_name,
        'movie_name' => $movie_name, 'language' => $language, 'date' => date('Y-m-d'), 'time' => date('H:i:s'), 'status' => 'pending'
    ];
    $requests_data['user_request_count'][$user_id] = ($requests_data['user_request_count'][$user_id] ?? 0) + 1;
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    $admin_msg = "🎯 New Movie Request\n🎬 Movie: $movie_name\n🗣️ Language: $language\n👤 User: " . ($username ? "@$username" : $first_name ?: "User#$user_id") . "\n🆔 User ID: $user_id\n📅 Date: " . date('Y-m-d H:i:s') . "\n🆔 Request ID: $request_id";
    sendMessage(ADMIN_ID, $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
    return true;
}

function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $count = 0;
    foreach ($requests_data['requests'] ?? [] as $req) {
        if ($req['user_id'] == $user_id && $req['date'] == $today) $count++;
    }
    return $count < DAILY_REQUEST_LIMIT;
}