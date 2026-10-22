<?php
// AI assistant settings
// Set the ANTHROPIC_API_KEY environment variable, or paste your key between the quotes below.
// Without a key, the chat still works using built-in IT help answers (offline mode).
define('ANTHROPIC_API_KEY', function_exists('db_env') ? db_env('ANTHROPIC_API_KEY') : (getenv('ANTHROPIC_API_KEY') ?: ''));
define('AI_MODEL', 'claude-opus-5-5');

define('CHAT_MAX_HISTORY', 12);   // messages sent to the AI per request
define('CHAT_MAX_LENGTH', 1000);  // characters per message
define('CHAT_RATE_LIMIT', 15);    // messages allowed per window, per session
define('CHAT_RATE_WINDOW', 300);  // seconds
define('CHAT_REQUIRE_LOGIN', filter_var(function_exists('db_env') ? db_env('CHAT_REQUIRE_LOGIN', 'true') : (getenv('CHAT_REQUIRE_LOGIN') ?: 'true'), FILTER_VALIDATE_BOOL));
