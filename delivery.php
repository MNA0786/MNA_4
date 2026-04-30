<?php
// ==============================
// DELIVERY WITH USER ATTRIBUTION
// ==============================
function send_file_with_attribution($chat_id, $channel_id, $message_id, $original_caption, $attribution_username) {
    $file_info = getMessage($channel_id, $message_id);
    if (!$file_info['ok']) return false;
    $message = $file_info['result'];
    $new_caption = $original_caption . "\n\n━━━━━━━━━━━━━━━━━━━\n📥 REQUESTED BY : $attribution_username";
    if (isset($message['document'])) {
        return json_decode(apiRequest('sendDocument', ['chat_id' => $chat_id, 'document' => $message['document']['file_id'], 'caption' => $new_caption, 'parse_mode' => 'HTML']), true);
    } elseif (isset($message['video'])) {
        return json_decode(apiRequest('sendVideo', ['chat_id' => $chat_id, 'video' => $message['video']['file_id'], 'caption' => $new_caption, 'parse_mode' => 'HTML']), true);
    } elseif (isset($message['audio'])) {
        return json_decode(apiRequest('sendAudio', ['chat_id' => $chat_id, 'audio' => $message['audio']['file_id'], 'caption' => $new_caption, 'parse_mode' => 'HTML']), true);
    } elseif (isset($message['photo'])) {
        $photos = $message['photo'];
        $file_id = end($photos)['file_id'];
        return json_decode(apiRequest('sendPhoto', ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $new_caption, 'parse_mode' => 'HTML']), true);
    }
    return json_decode(copyMessage($chat_id, $channel_id, $message_id), true);
}

function deliver_item_to_chat($chat_id, $item, $requested_by = null) {
    $source_channel = $item['channel_id'] ?? MAIN_CHANNEL_ID;
    $attribution = $requested_by ? "@$requested_by" : null;
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $original_caption = '';
        $msg_info = getMessage($source_channel, $item['message_id']);
        if ($msg_info['ok']) {
            $msg = $msg_info['result'];
            if (isset($msg['caption'])) $original_caption = $msg['caption'];
            elseif (isset($msg['text'])) $original_caption = $msg['text'];
        }
        if ($attribution && $original_caption) {
            $res = send_file_with_attribution($chat_id, $source_channel, $item['message_id'], $original_caption, $attribution);
            if ($res && $res['ok']) {
                update_stats('total_downloads', 1);
                return true;
            }
        }
        $res = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
        if ($res && $res['ok']) {
            if ($attribution) sendMessage($chat_id, "━━━━━━━━━━━━━━━━━━━\n📥 REQUESTED BY : $attribution", null, 'HTML');
            update_stats('total_downloads', 1);
            return true;
        }
    }
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>";
    if ($attribution) $text .= "\n\n━━━━━━━━━━━━━━━━━━━\n📥 REQUESTED BY : $attribution";
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}