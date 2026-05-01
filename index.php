<?php
// ==============================
// MAIN ENTRY POINT
// ==============================

require_once 'config.php';
require_once 'channel_functions.php';
require_once 'telegram_api.php';
require_once 'file_management.php';
require_once 'search_system.php';
require_once 'pagination.php';
require_once 'pagination_search.php';
require_once 'user_attribution.php';
require_once 'commands.php';

initialize_files();
get_cached_movies();

global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;

function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    if (strpos($text, '/') === 0) return true;
    if (strlen($text) < 3) return false;
    $invalid = ['good morning', 'good night', 'hello', 'hi', 'hey', 'thank you', 'thanks', 'welcome', 'bye'];
    foreach ($invalid as $phrase) if (strpos($text, $phrase) !== false) return false;
    return true;
}

$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        sendMessage($update['message']['chat']['id'], $MAINTENANCE_MESSAGE, null, 'HTML');
        exit;
    }

    // Channel posts auto-add
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        
        $valid_channels = [MAIN_CHANNEL_ID, SERIAL_CHANNEL_ID, THEATER_CHANNEL_ID, BACKUP_CHANNEL_ID, PRIVATE_CHANNEL_1_ID, PRIVATE_CHANNEL_2_ID];
        if (!in_array($chat_id, $valid_channels)) exit;
        
        $text = ''; $quality = 'Unknown'; $size = 'Unknown'; $language = 'Hindi';
        if (isset($message['caption'])) $text = $message['caption'];
        elseif (isset($message['text'])) $text = $message['text'];
        elseif (isset($message['document'])) { $text = $message['document']['file_name']; $size = round($message['document']['file_size'] / (1024 * 1024), 2) . ' MB'; }
        else $text = 'Uploaded Media';
        
        if (!empty(trim($text))) add_movie($text, $message_id, $chat_id, '', $quality, $size, $language);
    }
    
    // Messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        $user_info = ['first_name' => $message['from']['first_name'] ?? '', 'username' => $message['from']['username'] ?? ''];
        update_user_data($user_id, $user_info);
        
        if ($chat_type !== 'private' && strpos($text, '/') !== 0 && !is_valid_movie_query($text)) return;
        
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            handle_command($chat_id, $user_id, $command, $params);
        } elseif (!empty(trim($text))) {
            advanced_search_with_pagination($chat_id, $text, $user_id);
        }
    }
    
    // Callbacks
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        
        global $movie_messages, $attribution;
        
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            foreach ($movie_messages[$movie_lower] as $entry) deliver_item_to_chat($chat_id, $entry, $user_id);
            sendMessage($chat_id, "✅ '$data' info sent!\n\n📢 Join: " . MAIN_CHANNEL);
            answerCallbackQuery($query['id'], "Info sent!");
        }
        elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            if ($parts[1] == 'prev') totalupload_controller($chat_id, max(1, intval($parts[2]) - 1));
            elseif ($parts[1] == 'next') totalupload_controller($chat_id, intval($parts[2]) + 1);
            answerCallbackQuery($query['id'], "Page changed");
        }
        elseif (strpos($data, 'send_') === 0) {
            $page = intval(explode('_', $data)[1]);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page, $user_id);
            answerCallbackQuery($query['id'], "Batch sent");
        }
        elseif (strpos($data, 'get_') === 0) {
            $msg_id = str_replace('get_', '', $data);
            foreach (get_all_movies_list() as $movie) {
                if (($movie['message_id'] ?? '') == $msg_id || ($movie['message_id_raw'] ?? '') == $msg_id) {
                    deliver_item_to_chat($chat_id, $movie, $user_id);
                    answerCallbackQuery($query['id'], "Movie sent!");
                    break;
                }
            }
        }
        elseif (strpos($data, 'all_') === 0) {
            $query_text = base64_decode(str_replace('all_', '', $data));
            $results = search_movies($query_text, get_all_movies_list());
            safe_deliver_movies($chat_id, $results, $user_id);
            answerCallbackQuery($query['id'], "Sending " . count($results) . " movies");
        }
        elseif (strpos($data, 'page_') === 0) {
            handle_pagination_callback($data, $chat_id, $message['message_id'], $user_id);
            answerCallbackQuery($query['id'], "Loading page");
        }
        elseif ($data == 'close_' || strpos($data, 'close_') === 0 || $data == 'close_search') {
            deleteMessage($chat_id, $message['message_id']);
            answerCallbackQuery($query['id'], "Closed");
        }
        elseif ($data == 'help_command') {
            handle_command($chat_id, $user_id, '/help', []);
            answerCallbackQuery($query['id'], "Help menu");
        }
        else {
            answerCallbackQuery($query['id'], "Not available", true);
        }
    }
}

if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    echo "<h1>Webhook Setup</h1><p>Result: " . htmlspecialchars($result) . "</p>";
    exit;
}

if (!isset($update) || !$update) {
    $stats = get_stats();
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><a href='?setwebhook=1'>Set Webhook</a></p>";
}
?>
