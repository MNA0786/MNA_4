<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

$port = getenv('PORT') ?: '80';
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai.");
}

define('BOT_TOKEN', getenv('BOT_TOKEN'));

// ==============================
// PUBLIC CHANNELS (4 Channels)
// ==============================

define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003181705395');

define('SERIAL_CHANNEL', '@Entertainment_Tadka_Serial_786');
define('SERIAL_CHANNEL_ID', '-1003614546520');

define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1002831605258');

define('BACKUP_CHANNEL', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002964109368');

// ==============================
// PRIVATE CHANNELS (2 Channels)
// ==============================

define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');

// ==============================
// REQUEST GROUP (Group, not channel)
// ==============================

define('REQUEST_GROUP_USERNAME', '@EntertainmentTadka7860');
define('REQUEST_GROUP_ID', '-1003083386043');

// ==============================
// ADMIN CONFIGURATION
// ==============================

define('ADMIN_ID', (int)getenv('ADMIN_ID'));

if (!MAIN_CHANNEL_ID || !THEATER_CHANNEL_ID || !BACKUP_CHANNEL_ID) {
    die("❌ Essential channel IDs set nahi hain.");
}

// ==============================
// FILE PATHS
// ==============================

define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('LOG_FILE', 'bot_activity.log');

// ==============================
// BOT CONSTANTS
// ==============================

define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);

// CSV locked format header
define('CSV_HEADER', "movie_name,message_id,channel_id,video_path,quality,size,language\n");

// Maintenance mode
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable.\nWill be back in few days!\n\nThanks for patience 🙏";

// Global variables
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_pagination_sessions = array();
$last_search_message_id = array();
$user_search_sessions = array();
?>
