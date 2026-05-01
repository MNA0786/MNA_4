<?php
// ==============================
// CHANNEL MAPPING FUNCTIONS
// ==============================

function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == SERIAL_CHANNEL_ID) return 'serial';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == PRIVATE_CHANNEL_1_ID) return 'private1';
    if ($channel_id == PRIVATE_CHANNEL_2_ID) return 'private2';
    if ($channel_id == REQUEST_GROUP_ID) return 'request_group';
    
    return 'other';
}

function get_channel_display_name($channel_id) {
    $channel_id = strval($channel_id);
    
    $names = [
        MAIN_CHANNEL_ID => '🍿 Main Channel',
        SERIAL_CHANNEL_ID => '📺 Serial Channel',
        THEATER_CHANNEL_ID => '🎭 Theater Prints',
        BACKUP_CHANNEL_ID => '🔒 Backup Channel',
        PRIVATE_CHANNEL_1_ID => '🔐 Private Channel 1',
        PRIVATE_CHANNEL_2_ID => '🔐 Private Channel 2',
        REQUEST_GROUP_ID => '📥 Request Group'
    ];
    
    return $names[$channel_id] ?? '📢 Other Channel';
}

function get_channel_username($channel_id) {
    $channel_id = strval($channel_id);
    
    $usernames = [
        MAIN_CHANNEL_ID => MAIN_CHANNEL,
        SERIAL_CHANNEL_ID => SERIAL_CHANNEL,
        THEATER_CHANNEL_ID => THEATER_CHANNEL,
        BACKUP_CHANNEL_ID => BACKUP_CHANNEL,
        REQUEST_GROUP_ID => REQUEST_GROUP_USERNAME
    ];
    
    return $usernames[$channel_id] ?? null;
}

function get_direct_channel_link($message_id, $channel_id) {
    if (empty($channel_id)) return "Channel ID not available";
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function get_channel_username_link($channel_id) {
    switch ($channel_id) {
        case MAIN_CHANNEL_ID:
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case SERIAL_CHANNEL_ID:
            return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case THEATER_CHANNEL_ID:
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case BACKUP_CHANNEL_ID:
            return "https://t.me/" . ltrim(BACKUP_CHANNEL, '@');
        case REQUEST_GROUP_ID:
            return "https://t.me/" . ltrim(REQUEST_GROUP_USERNAME, '@');
        default:
            return "https://t.me/EntertainmentTadka786";
    }
}

function show_channel_info($chat_id) {
    $message = "📢 <b>Our Channels & Groups</b>\n\n";
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "📺 <b>Serial Channel:</b> " . SERIAL_CHANNEL . "\n";
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "📂 <b>Backup Channel:</b> " . BACKUP_CHANNEL . "\n";
    $message .= "📥 <b>Request Group:</b> " . REQUEST_GROUP_USERNAME . "\n\n";
    $message .= "🔔 Join all channels for updates!";

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'],
             ['text' => '📺 Serial', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
            [['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'],
             ['text' => '📂 Backup', 'url' => 'https://t.me/ETBackup']],
            [['text' => '📥 Request', 'url' => 'https://t.me/EntertainmentTadka7860']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_main_channel_info($chat_id) {
    $message = "🍿 <b>Main Channel</b>\n\n";
    $message .= "📢 Username: " . MAIN_CHANNEL . "\n";
    $message .= "🆔 Channel ID: <code>" . MAIN_CHANNEL_ID . "</code>\n\n";
    $message .= "🔗 <a href='https://t.me/EntertainmentTadka786'>Click to Join</a>";
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_serial_channel_info($chat_id) {
    $message = "📺 <b>Serial Channel</b>\n\n";
    $message .= "📢 Username: " . SERIAL_CHANNEL . "\n";
    $message .= "🆔 Channel ID: <code>" . SERIAL_CHANNEL_ID . "</code>\n\n";
    $message .= "🔗 <a href='https://t.me/Entertainment_Tadka_Serial_786'>Click to Join</a>";
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_theater_channel_info($chat_id) {
    $message = "🎭 <b>Theater Prints Channel</b>\n\n";
    $message .= "📢 Username: " . THEATER_CHANNEL . "\n";
    $message .= "🆔 Channel ID: <code>" . THEATER_CHANNEL_ID . "</code>\n\n";
    $message .= "🔗 <a href='https://t.me/threater_print_movies'>Click to Join</a>";
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_backup_channel_info($chat_id) {
    $message = "🔒 <b>Backup Channel</b>\n\n";
    $message .= "📢 Username: " . BACKUP_CHANNEL . "\n";
    $message .= "🆔 Channel ID: <code>" . BACKUP_CHANNEL_ID . "</code>\n\n";
    
    if ($chat_id == ADMIN_ID) {
        $message .= "🔗 <a href='https://t.me/ETBackup'>Click to Join</a>";
        sendMessage($chat_id, $message, null, 'HTML');
    } else {
        sendMessage($chat_id, "🔒 Backup channel is admin only.", null, 'HTML');
    }
}

function show_request_group_info($chat_id) {
    $message = "📥 <b>Request Group</b>\n\n";
    $message .= "📢 Username: " . REQUEST_GROUP_USERNAME . "\n";
    $message .= "🆔 Group ID: <code>" . REQUEST_GROUP_ID . "</code>\n\n";
    $message .= "🎯 Purpose: Movie requests, Support & help\n\n";
    $message .= "🔗 <a href='https://t.me/EntertainmentTadka7860'>Click to Join</a>";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📥 Join Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}
?>
