<?php
// ==============================
// USER ATTRIBUTION FEATURE
// For caption merging only
// ==============================

class UserAttributionFeature {
    private $bot_token;
    
    public function __construct($bot_token) {
        $this->bot_token = $bot_token;
    }
    
    public function getUserMention($user_id, $name = null) {
        if (!$name) {
            $user_info = $this->getUserInfo($user_id);
            $name = $user_info['full_name'] ?: $user_info['first_name'] ?: "User";
            if ($user_info['username']) $name = "@" . $user_info['username'];
        }
        return "<a href='tg://user?id={$user_id}'>" . htmlspecialchars($name) . "</a>";
    }
    
    public function getUserInfo($user_id) {
        $url = "https://api.telegram.org/bot{$this->bot_token}/getChat";
        $data = ['chat_id' => $user_id];
        $result = $this->sendRequest($url, $data);
        if ($result && isset($result['result'])) {
            $chat = $result['result'];
            return [
                'user_id' => $user_id,
                'first_name' => $chat['first_name'] ?? '',
                'last_name' => $chat['last_name'] ?? '',
                'username' => $chat['username'] ?? '',
                'full_name' => trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''))
            ];
        }
        return ['user_id' => $user_id, 'username' => null, 'first_name' => 'User'];
    }
    
    private function sendRequest($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($http_code == 200) ? json_decode($response, true) : null;
    }
    
    public function getAttributionText($user_id, $requested_by = null) {
        if ($requested_by) {
            return "\n\n👤 <b>Requested by:</b> {$requested_by}\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        } else {
            return "\n\n📥 <b>Sent to:</b> " . $this->getUserMention($user_id) . "\n⏰ <i>" . date('d-m-Y H:i:s') . "</i>";
        }
    }
    
    public function logAttribution($user_id, $movie_name, $action = 'delivered') {
        $log_entry = ['timestamp' => date('Y-m-d H:i:s'), 'user_id' => $user_id, 'movie_name' => $movie_name, 'action' => $action];
        $attribution_log = 'attribution_log.json';
        $logs = file_exists($attribution_log) ? json_decode(file_get_contents($attribution_log), true) : [];
        $logs[] = $log_entry;
        if (count($logs) > 500) $logs = array_slice($logs, -500);
        file_put_contents($attribution_log, json_encode($logs, JSON_PRETTY_PRINT));
    }
}

$attribution = new UserAttributionFeature(BOT_TOKEN);

function get_attribution_text($user_id, $requested_by = null) {
    global $attribution;
    return $attribution->getAttributionText($user_id, $requested_by);
}

function log_movie_attribution($user_id, $movie_name, $action = 'delivered') {
    global $attribution;
    return $attribution->logAttribution($user_id, $movie_name, $action);
}
?>
