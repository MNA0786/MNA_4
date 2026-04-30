<?php
// ==============================
// COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            send_start_message($chat_id, $user_id);
            break;
        case '/help':
            send_help_message($chat_id);
            break;
        case '/search':
            $movie = implode(' ', $params);
            if (empty($movie)) sendMessage($chat_id, "❌ Usage: /search movie_name");
            else advanced_search($chat_id, $movie, $user_id);
            break;
        case '/totalupload':
            totalupload_controller($chat_id, isset($params[0]) ? intval($params[0]) : 1);
            break;
        case '/latest':
            show_latest_movies($chat_id, 10);
            break;
        case '/trending':
            show_trending_movies($chat_id);
            break;
        case '/theater':
            show_theater_movies($chat_id);
            break;
        case '/request':
            $movie = implode(' ', $params);
            if (empty($movie)) sendMessage($chat_id, "❌ Usage: /request movie_name");
            else if (add_movie_request($user_id, $movie)) sendMessage($chat_id, "✅ Request submitted!");
            else sendMessage($chat_id, "❌ Daily limit reached!");
            break;
        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;
        case '/requestlimit':
            show_request_limit($chat_id, $user_id);
            break;
        case '/stats':
            admin_stats($chat_id);
            break;
        case '/checkcsv':
            show_csv_data($chat_id, isset($params[0]) && $params[0] == 'all');
            break;
        case '/testcsv':
            test_csv($chat_id);
            break;
        case '/checkdate':
            check_date($chat_id);
            break;
        case '/pending_request':
            pending_requests($chat_id);
            break;
        case '/bulk_approve':
            bulk_approve($chat_id);
            break;
        case '/cleanup':
            perform_cleanup($chat_id);
            break;
        case '/maintenance':
            toggle_maintenance_mode($chat_id, $params[0] ?? '');
            break;
        case '/channel':
            show_all_channels_info($chat_id);
            break;
        default:
            sendMessage($chat_id, "❌ Unknown command. Use /help");
    }
}

function send_start_message($chat_id, $user_id) {
    $welcome = "🎬 Welcome to Entertainment Tadka!\n\n📢 How to use this bot:\n• Simply type any movie name\n• Use English or Hinglish\n• Partial names also work\n\n🔍 Examples:\n• Mandala Murders 2025\n• Lokah Chapter 1 Chandra 2025\n• Idli Kadai (2025)\n• IT - Welcome to Derry (2025) S01\n• hindi movie\n• kgf theater print\n\n❌ Don't type:\n• Technical questions\n• Player instructions\n• Non-movie queries\n\n📢 Our Channels:\n\n🍿 MAIN CHANNEL:\n@EntertainmentTadka786 - Latest movies & web-series\n\n📺 SERIAL CHANNELS:\n@Entertainment_Tadka_Serial_786 - All TV serials & episodes\n\n🎭 THEATER PRINTS:\n@threater_print_movies - HD theater prints\n\n🔒 BACKUP CHANNEL:\n@ETBackup - Data protection & backups\n\n📥 REQUEST GROUP:\n@EntertainmentTadka7860 - Movie requests & support\n\n💬 Need help? Use /help for all commands";
    $keyboard = ['inline_keyboard' => [
        [['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''], ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786']],
        [['text' => '📺 Serials', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786'], ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']],
        [['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup'], ['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860']],
        [['text' => '❓ Help', 'callback_data' => 'help_command']]
    ]];
    if ($user_id == ADMIN_ID) $keyboard['inline_keyboard'][] = [['text' => '🛠️ ADMIN PANEL', 'callback_data' => 'admin_panel']];
    sendMessage($chat_id, $welcome, $keyboard, 'HTML');
}

function send_help_message($chat_id) {
    $help = "🤖 Entertainment Tadka Bot - Complete Guide\n\n📢 Our Channels & Groups:\n\n🍿 MAIN: @EntertainmentTadka786 → Latest movies\n📺 SERIAL: @Entertainment_Tadka_Serial_786 → TV serials\n🎭 THEATER: @threater_print_movies → Theater prints\n🔒 BACKUP: @ETBackup → Data backups\n📥 REQUESTS: @EntertainmentTadka7860 → Support\n\n🎯 Search Commands:\n• Just type movie name\n• /search movie\n\n📁 Browse Commands:\n• /totalupload - All movies (max 7 pages)\n• /latest\n• /trending\n• /theater\n\n📝 Request Commands:\n• /request movie\n• /myrequests\n• /requestlimit\n\n🔗 Quick Commands:\n• /channel - All channels info\n\n💡 Pro Tips: Use partial names, join all channels, check spelling";
    $keyboard = ['inline_keyboard' => [
        [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'], ['text' => '📺 Serials', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
        [['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'], ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']],
        [['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🎬 Search', 'switch_inline_query_current_chat' => '']]
    ]];
    sendMessage($chat_id, $help, $keyboard, 'HTML');
}