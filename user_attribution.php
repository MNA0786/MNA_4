<?php
// ==============================
// USER ATTRIBUTION FEATURE - SIMPLIFIED
// ==============================

class UserAttributionFeature {
    private $bot_token;
    
    public function __construct($bot_token) {
        $this->bot_token = $bot_token;
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
    
    public function logAttribution($user_id, $movie_name, $action = 'delivered') {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'movie_name' => $movie_name,
            'action' => $action
        ];
        $attribution_log = 'attribution_log.json';
        $logs = file_exists($attribution_log) ? json_decode(file_get_contents($attribution_log), true) : [];
        $logs[] = $log_entry;
        if (count($logs) > 500) $logs = array_slice($logs, -500);
        file_put_contents($attribution_log, json_encode($logs, JSON_PRETTY_PRINT));
    }
}

$attribution = new UserAttributionFeature(BOT_TOKEN);
?>
