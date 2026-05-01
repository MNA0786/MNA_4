<?php
// ==============================
// COMMAND HANDLER
// ==============================

function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            $welcome = "🎬 <b>Welcome to Entertainment Tadka!</b>\n\n";
            $welcome .= "📢 <b>How to use:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Partial names also work\n\n";
            $welcome .= "🔍 <b>Examples:</b>\n";
            $welcome .= "• Mandala Murders 2025\n";
            $welcome .= "• Lokah Chapter 1 Chandra 2025\n";
            $welcome .= "• Idli Kadai (2025)\n";
            $welcome .= "• IT - Welcome to Derry (2025) S01\n";
            $welcome .= "• Engaged S01 & S02\n\n";
            $welcome .= "❌ <b>Don't type:</b>\n";
            $welcome .= "• Technical questions\n";
            $welcome .= "• Player instructions\n";
            $welcome .= "• Non-movie queries\n\n";
            $welcome .= "📢 <b>Join Our Channels:</b>\n\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📺 Serial: @Entertainment_Tadka_Serial_786\n";
            $welcome .= "📥 Request: @EntertainmentTadka7860\n";
            $welcome .= "🎭 Theater: @threater_print_movies\n";
            $welcome .= "📂 Backup: @ETBackup\n\n";
            $welcome .= "💬 Need help? Use /help for all commands";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'],
                     ['text' => '📺 Serial', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
                    [['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'],
                     ['text' => '📂 Backup', 'url' => 'https://t.me/ETBackup']],
                    [['text' => '📥 Request', 'url' => 'https://t.me/EntertainmentTadka7860'],
                     ['text' => '❓ Help', 'callback_data' => 'help_command']]
                ]
            ];
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            break;

        case '/help':
            $help = "🤖 <b>Commands Guide</b>\n\n";
            $help .= "🎯 <b>Search:</b>\n";
            $help .= "• Type movie name directly\n";
            $help .= "• /search movie_name\n\n";
            $help .= "📁 <b>Browse:</b>\n";
            $help .= "• /totaluploads - All movies\n\n";
            $help .= "📝 <b>Request:</b>\n";
            $help .= "• /request movie_name\n";
            $help .= "• /myrequests\n\n";
            $help .= "📢 <b>Channels & Groups:</b>\n";
            $help .= "• /channel - All channels\n\n";
            
            if ($user_id == ADMIN_ID) {
                $help .= "👑 <b>Admin Commands:</b>\n";
                $help .= "• /pendingrequests - View pending requests\n";
                $help .= "• /bulkapprove - Bulk approve all\n";
            }
            sendMessage($chat_id, $help, null, 'HTML');
            break;

        case '/search':
        case '/s':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /search movie_name\nExample: /search kgf 2", null, 'HTML');
                return;
            }
            advanced_search_with_pagination($chat_id, $movie_name, $user_id);
            break;

        case '/totaluploads':
        case '/allmovies':
        case '/browse':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;

        case '/request':
        case '/req':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request movie_name\nExample: /request Pushpa 2", null, 'HTML');
                return;
            }
            if (add_movie_request($user_id, $movie_name)) {
                sendMessage($chat_id, "✅ Request submitted! We'll add it soon.\n\n📢 Join: " . MAIN_CHANNEL, null, 'HTML');
            } else {
                sendMessage($chat_id, "❌ Daily limit reached! Max " . DAILY_REQUEST_LIMIT . " requests per day.", null, 'HTML');
            }
            break;

        case '/myrequests':
        case '/myreqs':
            show_user_requests($chat_id, $user_id);
            break;

        case '/channel':
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
            break;

        case '/mainchannel':
            show_main_channel_info($chat_id);
            break;

        case '/serialchannel':
        case '/serial':
            show_serial_channel_info($chat_id);
            break;

        case '/theaterchannel':
        case '/theaterprints':
            show_theater_channel_info($chat_id);
            break;

        case '/backupchannel':
            show_backup_channel_info($chat_id);
            break;

        case '/requestgroup':
        case '/group':
            show_request_group_info($chat_id);
            break;

        case '/pendingrequests':
        case '/pending':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Admin only command.", null, 'HTML');
                return;
            }
            show_pending_requests($chat_id);
            break;

        case '/bulkapprove':
        case '/approveall':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Admin only command.", null, 'HTML');
                return;
            }
            bulk_approve_requests($chat_id);
            break;

        default:
            sendMessage($chat_id, "❌ Unknown command. Use /help", null, 'HTML');
    }
}

// ==============================
// USER REQUESTS FUNCTIONS
// ==============================

function show_user_requests($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests = $users_data['users'][$user_id]['requests'] ?? [];
    
    if (empty($requests)) {
        sendMessage($chat_id, "📭 No requests yet!\n\nUse /request movie_name to request movies.", null, 'HTML');
        return;
    }
    
    $msg = "📝 <b>Your Requests</b>\n\n";
    $pending = 0;
    $completed = 0;
    
    foreach (array_reverse($requests) as $req) {
        if ($req['status'] == 'pending') {
            $status = "⏳ Pending";
            $pending++;
        } else {
            $status = "✅ Completed";
            $completed++;
        }
        $msg .= "🎬 <b>" . htmlspecialchars($req['movie_name']) . "</b>\n";
        $msg .= "   📅 Date: " . $req['date'] . " | " . $status . "\n\n";
    }
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📊 <b>Summary:</b>\n";
    $msg .= "• ⏳ Pending: " . $pending . "\n";
    $msg .= "• ✅ Completed: " . $completed . "\n";
    $msg .= "• 📋 Total: " . count($requests);
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function add_movie_request($user_id, $movie_name) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $today = date('Y-m-d');
    
    // Initialize user if not exists
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => '',
            'username' => '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'request_count' => 0,
            'requests' => []
        ];
    }
    
    // Check daily limit
    $today_requests = 0;
    if (isset($users_data['users'][$user_id]['requests'])) {
        foreach ($users_data['users'][$user_id]['requests'] as $req) {
            if ($req['date'] == $today) $today_requests++;
        }
    }
    
    if ($today_requests >= DAILY_REQUEST_LIMIT) {
        return false;
    }
    
    // Add request
    $request = [
        'id' => uniqid(),
        'movie_name' => $movie_name,
        'date' => $today,
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    
    if (!isset($users_data['users'][$user_id]['requests'])) {
        $users_data['users'][$user_id]['requests'] = [];
    }
    
    $users_data['users'][$user_id]['requests'][] = $request;
    $users_data['users'][$user_id]['request_count'] = ($users_data['users'][$user_id]['request_count'] ?? 0) + 1;
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    // Notify admin
    $username = $users_data['users'][$user_id]['username'] ?? $users_data['users'][$user_id]['first_name'] ?? $user_id;
    $admin_msg = "🎯 <b>New Movie Request</b>\n\n";
    $admin_msg .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie_name) . "\n";
    $admin_msg .= "👤 <b>User:</b> @{$username}\n";
    $admin_msg .= "🆔 <b>User ID:</b> <code>{$user_id}</code>\n";
    $admin_msg .= "📅 <b>Date:</b> {$today}\n\n";
    $admin_msg .= "💡 Use /pendingrequests to approve/reject";
    
    sendMessage(ADMIN_ID, $admin_msg, null, 'HTML');
    bot_log("Movie request: $movie_name by $user_id");
    
    return true;
}

// ==============================
// ADMIN PENDING REQUESTS FUNCTIONS
// ==============================

function show_pending_requests($chat_id, $page = 1) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $all_pending = [];
    
    foreach ($users_data['users'] as $uid => $user) {
        if (isset($user['requests'])) {
            foreach ($user['requests'] as $index => $req) {
                if ($req['status'] == 'pending') {
                    $all_pending[] = [
                        'user_id' => $uid,
                        'username' => $user['username'] ?? $user['first_name'] ?? 'Unknown',
                        'request_id' => $req['id'],
                        'request_index' => $index,
                        'movie_name' => $req['movie_name'],
                        'date' => $req['date'],
                        'time' => $req['time']
                    ];
                }
            }
        }
    }
    
    if (empty($all_pending)) {
        $empty_msg = "📭 <b>No Pending Requests</b>\n\n✅ All requests have been processed!";
        $empty_keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Refresh', 'callback_data' => 'refresh_pending']],
                [['text' => '❌ Close', 'callback_data' => 'close_pending']]
            ]
        ];
        sendMessage($chat_id, $empty_msg, $empty_keyboard, 'HTML');
        return;
    }
    
    $per_page = 4;
    $total = count($all_pending);
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * $per_page;
    $items = array_slice($all_pending, $start, $per_page);
    
    $message = "═══════════════════════════════\n";
    $message .= "📋 <b>PENDING REQUESTS</b>\n";
    $message .= "═══════════════════════════════\n\n";
    $message .= "📊 Total: <b>{$total}</b> | Page: <b>{$page}/{$total_pages}</b>\n\n";
    
    $counter = $start + 1;
    foreach ($items as $req) {
        $username = $req['username'];
        if (is_numeric($username) || empty($username)) {
            $user_info = get_user_info_simple($req['user_id']);
            $username = $user_info['first_name'] ?? "User";
        }
        
        $message .= "┌─────────────────────────────┐\n";
        $message .= "│ 🎬 <b>#" . $counter . " " . htmlspecialchars($req['movie_name']) . "</b>\n";
        $message .= "├─────────────────────────────┤\n";
        $message .= "│ 👤 User: @{$username}\n";
        $message .= "│ 🆔 ID: <code>{$req['user_id']}</code>\n";
        $message .= "│ 📅 Date: {$req['date']} at {$req['time']}\n";
        $message .= "└─────────────────────────────┘\n\n";
        $counter++;
    }
    
    $keyboard = ['inline_keyboard' => []];
    
    // Approve/Reject buttons for each request
    $row = [];
    foreach ($items as $req) {
        $short_name = strlen($req['movie_name']) > 18 ? substr($req['movie_name'], 0, 15) . '...' : $req['movie_name'];
        $row[] = ['text' => "✅ " . $short_name, 'callback_data' => "approve_req_{$req['user_id']}_{$req['request_index']}"];
        $row[] = ['text' => "❌ " . $short_name, 'callback_data' => "reject_req_{$req['user_id']}_{$req['request_index']}"];
        
        if (count($row) == 4) {
            $keyboard['inline_keyboard'][] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $keyboard['inline_keyboard'][] = $row;
    }
    
    $keyboard['inline_keyboard'][] = [['text' => '─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─', 'callback_data' => 'noop']];
    
    // Pagination
    $nav_row = [];
    if ($page > 1) $nav_row[] = ['text' => '◀ ◀ PREV', 'callback_data' => "pending_page_" . ($page - 1)];
    $nav_row[] = ['text' => "📄 {$page}/{$total_pages}", 'callback_data' => 'noop'];
    if ($page < $total_pages) $nav_row[] = ['text' => 'NEXT ▶ ▶', 'callback_data' => "pending_page_" . ($page + 1)];
    $keyboard['inline_keyboard'][] = $nav_row;
    
    // Bulk actions
    $keyboard['inline_keyboard'][] = [
        ['text' => '📦 BULK APPROVE ALL', 'callback_data' => 'bulk_approve_all'],
        ['text' => '🗑️ BULK REJECT ALL', 'callback_data' => 'bulk_reject_all']
    ];
    
    // Action buttons
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔄 REFRESH', 'callback_data' => 'refresh_pending'],
        ['text' => '📊 STATS', 'callback_data' => 'pending_stats'],
        ['text' => '❌ CLOSE', 'callback_data' => 'close_pending']
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function approve_single_request($chat_id, $user_id, $request_index) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id]['requests'][$request_index])) {
        sendMessage($chat_id, "❌ Request not found!", null, 'HTML');
        return false;
    }
    
    $req = &$users_data['users'][$user_id]['requests'][$request_index];
    
    if ($req['status'] == 'pending') {
        $movie_name = $req['movie_name'];
        $req['status'] = 'completed';
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
        
        // Notify user
        $notify_msg = "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $notify_msg .= "✅ <b>REQUEST APPROVED!</b>\n";
        $notify_msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $notify_msg .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie_name) . "\n";
        $notify_msg .= "📅 <b>Requested on:</b> {$req['date']}\n\n";
        $notify_msg .= "📢 Movie will be added to our database soon!\n";
        $notify_msg .= "🍿 Join: " . MAIN_CHANNEL . "\n";
        $notify_msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        
        sendMessage($user_id, $notify_msg, null, 'HTML');
        
        // Admin confirmation
        sendMessage($chat_id, "✅ Approved: " . htmlspecialchars($movie_name), null, 'HTML');
        bot_log("Request approved: {$movie_name} for user $user_id");
        return true;
    }
    
    sendMessage($chat_id, "❌ Request already processed!", null, 'HTML');
    return false;
}

function reject_single_request($chat_id, $user_id, $request_index) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id]['requests'][$request_index])) {
        sendMessage($chat_id, "❌ Request not found!", null, 'HTML');
        return false;
    }
    
    $req = &$users_data['users'][$user_id]['requests'][$request_index];
    
    if ($req['status'] == 'pending') {
        $movie_name = $req['movie_name'];
        array_splice($users_data['users'][$user_id]['requests'], $request_index, 1);
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
        
        // Notify user
        $notify_msg = "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $notify_msg .= "❌ <b>REQUEST REJECTED</b>\n";
        $notify_msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $notify_msg .= "🎬 <b>Movie:</b> " . htmlspecialchars($movie_name) . "\n";
        $notify_msg .= "📅 <b>Requested on:</b> {$req['date']}\n\n";
        $notify_msg .= "📝 Try searching with different keywords!\n";
        $notify_msg .= "🔍 Use /search to find movies\n";
        $notify_msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        
        sendMessage($user_id, $notify_msg, null, 'HTML');
        
        // Admin confirmation
        sendMessage($chat_id, "❌ Rejected: " . htmlspecialchars($movie_name), null, 'HTML');
        bot_log("Request rejected: $movie_name for user $user_id");
        return true;
    }
    
    sendMessage($chat_id, "❌ Request already processed!", null, 'HTML');
    return false;
}

function bulk_approve_requests($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $approved_count = 0;
    $notified_users = [];
    
    $progress_msg = sendMessage($chat_id, "📦 Bulk approve in progress...\n0% complete", null, 'HTML', false);
    $progress_id = $progress_msg['result']['message_id'];
    
    $total_pending = 0;
    foreach ($users_data['users'] as $uid => $user) {
        if (isset($user['requests'])) {
            foreach ($user['requests'] as $req) {
                if ($req['status'] == 'pending') $total_pending++;
            }
        }
    }
    
    $processed = 0;
    foreach ($users_data['users'] as $uid => &$user) {
        if (isset($user['requests'])) {
            foreach ($user['requests'] as $index => &$req) {
                if ($req['status'] == 'pending') {
                    $req['status'] = 'completed';
                    $approved_count++;
                    
                    if (!in_array($uid, $notified_users)) {
                        $notified_users[] = $uid;
                        $notify_msg = "✅ <b>Request Approved!</b>\n\n🎬 Movie: " . htmlspecialchars($req['movie_name']) . "\n\n📢 Join: " . MAIN_CHANNEL;
                        sendMessage($uid, $notify_msg, null, 'HTML');
                    }
                    
                    $processed++;
                    if ($processed % 3 == 0 || $processed == $total_pending) {
                        $percent = round(($processed / $total_pending) * 100);
                        editMessage($chat_id, $progress_id, "📦 Bulk approve in progress...\n{$percent}% complete\n✅ Approved: {$approved_count}", null, true);
                    }
                }
            }
        }
    }
    
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    $completion_msg = "✅ <b>Bulk Approve Complete!</b>\n\n✅ Approved: {$approved_count} requests\n👥 Users Notified: " . count($notified_users);
    editMessage($chat_id, $progress_id, $completion_msg, null, true);
    bot_log("Bulk approved $approved_count requests");
}

function bulk_reject_requests($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $rejected_count = 0;
    $notified_users = [];
    
    $progress_msg = sendMessage($chat_id, "🗑️ Bulk reject in progress...\n0% complete", null, 'HTML', false);
    $progress_id = $progress_msg['result']['message_id'];
    
    $total_pending = 0;
    foreach ($users_data['users'] as $uid => $user) {
        if (isset($user['requests'])) {
            foreach ($user['requests'] as $req) {
                if ($req['status'] == 'pending') $total_pending++;
            }
        }
    }
    
    $processed = 0;
    foreach ($users_data['users'] as $uid => &$user) {
        if (isset($user['requests'])) {
            $new_requests = [];
            foreach ($user['requests'] as $req) {
                if ($req['status'] == 'pending') {
                    $rejected_count++;
                    
                    if (!in_array($uid, $notified_users)) {
                        $notified_users[] = $uid;
                        $notify_msg = "❌ <b>Request Rejected</b>\n\n🎬 Movie: " . htmlspecialchars($req['movie_name']) . "\n\n📝 Try searching with different keywords!";
                        sendMessage($uid, $notify_msg, null, 'HTML');
                    }
                    
                    $processed++;
                    if ($processed % 3 == 0 || $processed == $total_pending) {
                        $percent = round(($processed / $total_pending) * 100);
                        editMessage($chat_id, $progress_id, "🗑️ Bulk reject in progress...\n{$percent}% complete\n❌ Rejected: {$rejected_count}", null, true);
                    }
                } else {
                    $new_requests[] = $req;
                }
            }
            $user['requests'] = $new_requests;
        }
    }
    
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    $completion_msg = "✅ <b>Bulk Reject Complete!</b>\n\n❌ Rejected: {$rejected_count} requests\n👥 Users Notified: " . count($notified_users);
    editMessage($chat_id, $progress_id, $completion_msg, null, true);
    bot_log("Bulk rejected $rejected_count requests");
}

function show_pending_stats($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $pending_count = 0;
    $completed_count = 0;
    $total_requests = 0;
    $top_requesters = [];
    
    foreach ($users_data['users'] as $uid => $user) {
        if (isset($user['requests'])) {
            $user_pending = 0;
            foreach ($user['requests'] as $req) {
                $total_requests++;
                if ($req['status'] == 'pending') {
                    $pending_count++;
                    $user_pending++;
                } else {
                    $completed_count++;
                }
            }
            if ($user_pending > 0) {
                $username = $user['username'] ?? $user['first_name'] ?? "User";
                $top_requesters[] = ['name' => $username, 'count' => $user_pending];
            }
        }
    }
    
    usort($top_requesters, function($a, $b) { return $b['count'] - $a['count']; });
    $top_requesters = array_slice($top_requesters, 0, 5);
    
    $message = "═══════════════════════════════\n";
    $message .= "📊 <b>REQUEST STATISTICS</b>\n";
    $message .= "═══════════════════════════════\n\n";
    $message .= "📈 <b>Overall Stats:</b>\n";
    $message .= "├─ 📝 Total Requests: <b>{$total_requests}</b>\n";
    $message .= "├─ ⏳ Pending: <b>{$pending_count}</b>\n";
    $message .= "└─ ✅ Completed: <b>{$completed_count}</b>\n\n";
    
    if ($total_requests > 0) {
        $completion_rate = round(($completed_count / $total_requests) * 100, 1);
        $filled = round($completion_rate / 10);
        $empty = 10 - $filled;
        $message .= "📊 <b>Completion Rate:</b> {$completion_rate}%\n";
        $message .= "   █" . str_repeat('█', $filled) . str_repeat('░', $empty) . "\n\n";
    }
    
    if (!empty($top_requesters)) {
        $message .= "🏆 <b>Top Requesters:</b>\n";
        foreach ($top_requesters as $idx => $req) {
            $medal = $idx == 0 ? "🥇" : ($idx == 1 ? "🥈" : ($idx == 2 ? "🥉" : "📌"));
            $message .= "├─ {$medal} @{$req['name']} - {$req['count']} requests\n";
        }
    }
    
    $message .= "\n═══════════════════════════════";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📋 VIEW PENDING', 'callback_data' => 'view_pending']],
            [['text' => '❌ CLOSE', 'callback_data' => 'close_pending']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function get_user_info_simple($user_id) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getChat";
    $data = ['chat_id' => $user_id];
    
    $options = ['http' => ['method' => 'POST', 'content' => http_build_query($data)]];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result) {
        $info = json_decode($result, true);
        if ($info && isset($info['result'])) {
            return [
                'first_name' => $info['result']['first_name'] ?? '',
                'last_name' => $info['result']['last_name'] ?? '',
                'username' => $info['result']['username'] ?? ''
            ];
        }
    }
    return ['first_name' => 'User', 'username' => '', 'last_name' => ''];
}
?>
