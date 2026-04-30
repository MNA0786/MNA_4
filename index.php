<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/delivery.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/commands.php';
require_once __DIR__ . '/callbacks.php';
require_once __DIR__ . '/utils.php';

// ==============================
// MAINTENANCE MODE GLOBAL VAR
// ==============================
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe'll be back soon!";

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    get_cached_movies();

    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';

        update_user_data($user_id, [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ]);

        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            handle_command($chat_id, $user_id, strtolower($parts[0]), array_slice($parts, 1));
        } elseif (!empty(trim($text))) {
            advanced_search($chat_id, $text, $user_id);
        }
    }

    if (isset($update['callback_query'])) {
        handle_callback_query($update['callback_query']);
    }
}

// ==============================
// DEFAULT INDEX PAGE (if no update)
// ==============================
if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p>✅ Bot is running.</p>";
    echo "<p>Total Movies: " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p>Total Users: " . count($users_data['users'] ?? []) . "</p>";
}
?>
