<?php
// AI assistant endpoint: POST JSON {messages: [{role, content}, ...]} -> {reply, source}
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Method not allowed.']);
}

if (!hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    respond(419, ['error' => 'Your session expired. Please refresh the page.']);
}

// Simple per-session rate limit
$now = time();
$hits = array_filter($_SESSION['chat_hits'] ?? [], fn ($t) => $t > $now - CHAT_RATE_WINDOW);
if (count($hits) >= CHAT_RATE_LIMIT) {
    respond(429, ['error' => "You're sending messages too quickly. Please wait a moment."]);
}
$hits[] = $now;
$_SESSION['chat_hits'] = array_values($hits);

$input = json_decode(file_get_contents('php://input'), true);
$messages = sanitize_chat_history($input['messages'] ?? null);
if (!$messages) {
    respond(422, ['error' => 'Please type a message.']);
}

// Release the session lock so other pages don't wait on the AI response
session_write_close();

$question = end($messages)['content'];

if (ANTHROPIC_API_KEY === '') {
    respond(200, ['reply' => offline_reply($question), 'source' => 'offline']);
}

try {
    respond(200, ['reply' => ask_claude($messages), 'source' => 'ai']);
} catch (\Anthropic\Core\Exceptions\AuthenticationException $ex) {
    error_log('Chat AI: invalid ANTHROPIC_API_KEY - ' . $ex->getMessage());
} catch (\Anthropic\Core\Exceptions\RateLimitException $ex) {
    error_log('Chat AI: rate limited - ' . $ex->getMessage());
} catch (\Anthropic\Core\Exceptions\APIStatusException $ex) {
    error_log('Chat AI: API error - ' . $ex->getMessage());
} catch (\Anthropic\Core\Exceptions\APIConnectionException $ex) {
    error_log('Chat AI: connection failed - ' . $ex->getMessage());
} catch (RuntimeException $ex) {
    error_log('Chat AI: ' . $ex->getMessage());
}

// Any AI failure falls back to the built-in answers so the user still gets help
respond(200, ['reply' => offline_reply($question), 'source' => 'offline']);
