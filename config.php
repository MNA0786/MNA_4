<?php
// ==============================
// ENVIRONMENT & CONSTANTS
// ==============================
$port = getenv('PORT') ?: '80';
if (!getenv('BOT_TOKEN')) die("❌ BOT_TOKEN missing");
define('BOT_TOKEN', getenv('BOT_TOKEN'));

// PUBLIC CHANNELS
define('PUBLIC_CHANNEL_1_USERNAME', '@EntertainmentTadka786');
define('PUBLIC_CHANNEL_1_ID', '-1003181705395');
define('PUBLIC_CHANNEL_2_USERNAME', '@Entertainment_Tadka_Serial_786');
define('PUBLIC_CHANNEL_2_ID', '-1003614546520');
define('PUBLIC_CHANNEL_3_USERNAME', '@threater_print_movies');
define('PUBLIC_CHANNEL_3_ID', '-1002831605258');
define('PUBLIC_CHANNEL_4_USERNAME', '@ETBackup');
define('PUBLIC_CHANNEL_4_ID', '-1002964109368');

// PRIVATE CHANNELS (NOT LISTED PUBLICLY)
define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');

// REQUEST GROUP
define('REQUEST_GROUP_USERNAME', '@EntertainmentTadka7860');
define('REQUEST_GROUP_ID', '-1003083386043');

// BACKWARD COMPATIBILITY
define('MAIN_CHANNEL', PUBLIC_CHANNEL_1_USERNAME);
define('MAIN_CHANNEL_ID', PUBLIC_CHANNEL_1_ID);
define('THEATER_CHANNEL', PUBLIC_CHANNEL_3_USERNAME);
define('THEATER_CHANNEL_ID', PUBLIC_CHANNEL_3_ID);
define('BACKUP_CHANNEL_USERNAME', PUBLIC_CHANNEL_4_USERNAME);
define('BACKUP_CHANNEL_ID', PUBLIC_CHANNEL_4_ID);
define('BACKUP_CHANNEL_2_USERNAME', PUBLIC_CHANNEL_4_USERNAME);
define('BACKUP_CHANNEL_2_ID', PUBLIC_CHANNEL_4_ID);
define('REQUEST_CHANNEL', REQUEST_GROUP_USERNAME);
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

// FILE PATHS
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');

// CONSTANTS
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// GLOBALS
$movie_messages = [];
$movie_cache = [];