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
                sendMessage($chat_id, "❌ Usage: /search movie_name", null, 'HTML');
                return;
            }
            advanced_search_with_pagination($chat_id, $movie_name, $user_id);
            break;

        case '/totaluploads':
        case '/allmovies':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;

        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request movie_name", null, 'HTML');
                return;
            }
            if (add_movie_request($user_id, $movie_name)) {
                sendMessage($chat_id, "✅ Request submitted! We'll add it soon.", null, 'HTML');
            } else {
                sendMessage($chat_id, "❌ Daily limit reached! Max " . DAILY_REQUEST_LIMIT . " requests per day.", null, 'HTML');
            }
            break;

        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;

        case '/channel':
            show_channel_info($chat_id);
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

function show_user_requests($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests = $users_data['users'][$user_id]['requests'] ?? [];
    if (empty($requests)) {
        sendMessage($chat_id, "📭 No requests yet!", null, 'HTML');
        return;
    }
    $msg = "📝 <b>Your Requests</b>\n\n";
    $pending = 0;
    foreach (array_reverse($requests) as $req) {
        $status = $req['status'] == 'completed' ? '✅' : '⏳';
        $msg .= "$status " . htmlspecialchars($req['movie_name']) . "\n   📅 " . $req['date'] . "\n\n";
        if ($req['status'] == 'pending') $pending++;
    }
    $msg .= "📊 Pending: $pending\n📋 Total: " . count($requests);
    sendMessage($chat_id, $msg, null, 'HTML');
}

function add_movie_request($user_id, $movie_name) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $today = date('Y-m-d');
    
    $today_requests = 0;
    if (isset($users_data['users'][$user_id]['requests'])) {
        foreach ($users_data['users'][$user_id]['requests'] as $req) {
            if ($req['date'] == $today) $today_requests++;
        }
    }
    if ($today_requests >= DAILY_REQUEST_LIMIT) return false;
    
    $request = ['id' => uniqid(), 'movie_name' => $movie_name, 'date' => $today, 'time' => date('H:i:s'), 'status' => 'pending'];
    if (!isset($users_data['users'][$user_id]['requests'])) $users_data['users'][$user_id]['requests'] = [];
    $users_data['users'][$user_id]['requests'][] = $request;
    $users_data['users'][$user_id]['request_count'] = ($users_data['users'][$user_id]['request_count'] ?? 0) + 1;
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    $username = $users_data['users'][$user_id]['username'] ?? $users_data['users'][$user_id]['first_name'] ?? $user_id;
    sendMessage(ADMIN_ID, "🎯 <b>New Request</b>\n\n🎬 Movie: $movie_name\n👤 User: @$username", null, 'HTML');
    return true;
}

// Include admin functions from previous file (show_pending_requests, bulk_approve_requests, etc.)
// ... (admin functions here - too long to repeat, but they exist)
?>
