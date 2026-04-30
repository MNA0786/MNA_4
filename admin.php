<?php
// ==============================
// ADMIN COMMANDS & FUNCTIONS
// ==============================
function admin_stats($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $msg = "📊 <b>Bot Statistics</b>\n\n🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n👥 Total Users: " . count($users_data['users'] ?? []) . "\n🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n✅ Successful: " . ($stats['successful_searches'] ?? 0) . "\n❌ Failed: " . ($stats['failed_searches'] ?? 0) . "\n📥 Total Downloads: " . ($stats['total_downloads'] ?? 0);
    sendMessage($chat_id, $msg, null, 'HTML');
}

function show_csv_data($chat_id, $show_all = false) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    if (!file_exists(CSV_FILE)) { sendMessage($chat_id, "❌ CSV file not found."); return; }
    $movies = get_cached_movies();
    $limit = $show_all ? count($movies) : 10;
    $movies = array_reverse(array_slice($movies, 0, $limit));
    $msg = "📊 <b>CSV Movie Database</b>\n📁 Total: " . count($movies) . "\n\n";
    $i = 1;
    foreach ($movies as $m) $msg .= "$i. 🎬 " . htmlspecialchars($m['movie_name']) . "\n";
    sendMessage($chat_id, $msg, null, 'HTML');
}

function test_csv($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    $movies = get_cached_movies();
    $msg = "";
    foreach ($movies as $m) $msg .= "🎬 {$m['movie_name']} | ID: {$m['message_id_raw']} | Channel: {$m['channel_id']}\n";
    sendMessage($chat_id, $msg ?: "No data", null, 'HTML');
}

function check_date($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    sendMessage($chat_id, "📅 Use /stats for upload info.");
}

function pending_requests($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    $req_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending = array_filter($req_data['requests'] ?? [], fn($r) => $r['status'] == 'pending');
    if (empty($pending)) { sendMessage($chat_id, "📭 No pending requests."); return; }
    $msg = "📝 <b>Pending Requests</b>\n\n";
    foreach ($pending as $r) $msg .= "🆔 {$r['id']}\n🎬 {$r['movie_name']}\n👤 " . ($r['username'] ? "@{$r['username']}" : "User#{$r['user_id']}") . "\n━━━━━━━━━━\n";
    $keyboard = ['inline_keyboard' => [[['text' => '✅ Approve All', 'callback_data' => 'bulk_approve']]]];
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function bulk_approve($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    sendMessage($chat_id, "✅ Bulk approve feature ready. Use /pending_request to view.");
}

function perform_cleanup($chat_id) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    global $movie_cache;
    $movie_cache = [];
    sendMessage($chat_id, "🧹 Cleanup completed! Cache cleared.");
}

function toggle_maintenance_mode($chat_id, $mode) {
    if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Access denied."); return; }
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    $MAINTENANCE_MODE = ($mode == 'on');
    $MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe'll be back soon!";
    sendMessage($chat_id, $MAINTENANCE_MODE ? "🔧 Maintenance mode ENABLED" : "✅ Maintenance mode DISABLED");
}

function show_user_requests($chat_id, $user_id) {
    $req_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_reqs = array_filter($req_data['requests'] ?? [], fn($r) => $r['user_id'] == $user_id);
    if (empty($user_reqs)) { sendMessage($chat_id, "📭 No requests found."); return; }
    $msg = "📝 <b>Your Requests</b>\n\n";
    foreach (array_slice($user_reqs, 0, 10) as $r) $msg .= "🎬 {$r['movie_name']}\n📅 {$r['date']}\n✅ Status: {$r['status']}\n━━━━━━━━━━\n";
    sendMessage($chat_id, $msg, null, 'HTML');
}

function show_request_limit($chat_id, $user_id) {
    $req_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $count = 0;
    foreach ($req_data['requests'] ?? [] as $r) if ($r['user_id'] == $user_id && $r['date'] == $today) $count++;
    sendMessage($chat_id, "📋 Daily Request Limit: " . DAILY_REQUEST_LIMIT . "\nUsed Today: $count\nRemaining: " . max(0, DAILY_REQUEST_LIMIT - $count));
}