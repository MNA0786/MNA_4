<?php
// ==============================
// TELEGRAM API FUNCTIONS WITH TYPING INDICATORS
// ==============================

function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        if ($res === false) bot_log("CURL ERROR: " . curl_error($ch), 'ERROR');
        curl_close($ch);
        return $res;
    } else {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) bot_log("API Request failed for method: $method", 'ERROR');
        return $result;
    }
}

function sendTypingAction($chat_id) {
    $data = ['chat_id' => $chat_id, 'action' => 'typing'];
    return apiRequest('sendChatAction', $data);
}

function sendUploadDocumentAction($chat_id) {
    $data = ['chat_id' => $chat_id, 'action' => 'upload_document'];
    return apiRequest('sendChatAction', $data);
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null, $show_typing = true) {
    if ($show_typing) sendTypingAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null, $show_typing = true) {
    if ($show_typing) sendTypingAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = ['callback_query_id' => $callback_query_id, 'show_alert' => $show_alert];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    sendTypingAction($chat_id);
    return apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function copyMessage($chat_id, $from_chat_id, $message_id, $caption = null) {
    sendUploadDocumentAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    if ($caption) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    
    return apiRequest('copyMessage', $data);
}

function getMessage($chat_id, $message_id) {
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id];
    return apiRequest('getMessage', $data);
}

function sendPhoto($chat_id, $photo, $caption = null, $reply_markup = null) {
    sendTypingAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'photo' => $photo
    ];
    if ($caption) $data['caption'] = $caption;
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    return apiRequest('sendPhoto', $data, true);
}

function sendVideo($chat_id, $video, $caption = null, $reply_markup = null) {
    sendUploadDocumentAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'video' => $video
    ];
    if ($caption) $data['caption'] = $caption;
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    return apiRequest('sendVideo', $data, true);
}

function sendDocument($chat_id, $document, $caption = null, $reply_markup = null) {
    sendUploadDocumentAction($chat_id);
    
    $data = [
        'chat_id' => $chat_id,
        'document' => $document
    ];
    if ($caption) $data['caption'] = $caption;
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    return apiRequest('sendDocument', $data, true);
}
?>
