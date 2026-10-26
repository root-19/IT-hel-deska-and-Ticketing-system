<?php
// AI assistant helpers: history validation, Claude API call, offline answers

require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/knowledge.php';

const AI_SYSTEM_PROMPT = <<<PROMPT
You are the ICCT AI Assistant, the virtual IT help desk agent for ICCT Colleges in the Philippines. You help students, faculty, and staff with IT issues: Wi-Fi and network, school portal and email accounts, passwords, printers, projectors and classroom AV, computers and laptops, and software.

How to respond:
- Keep answers short and practical: a one-line read of the problem, then up to 5 numbered steps to try.
- Write plain text only. No markdown: no asterisks, headings, or tables.
- Reply in the user's language. If they write in Tagalog, Filipino, or Taglish, answer in natural Taglish; if they write in English, answer in English.
- When ICCT knowledge base articles are provided below, base your answer on them first: they are the official ICCT procedures. Translate them to the user's language as needed, and only add general IT knowledge where the articles don't cover the problem.
- If the steps don't fix it, or it needs hands-on work, access changes, or hardware repair, tell them to submit a ticket from their dashboard on this site.
- Accounts: students register themselves on this site. Computer Laboratory, Faculty & Staff, and IT Technician accounts are created by the IT Admin, so direct those users to the IT Department.
- IT Technicians and IT Admins may ask for troubleshooting help on behalf of others; give them technical depth.
- Never ask for or accept passwords, OTPs, or other secrets. If someone shares one, tell them to change it right away.
- You can't see their device, the ticket system, or account records, so never claim to have checked, reset, or created anything.
- For questions unrelated to IT support, say briefly that you can only help with IT concerns.
PROMPT;

/**
 * Validates chat history from the browser: user/assistant roles only, alternating,
 * trimmed to the configured limits, starting and ending with a user message.
 */
function sanitize_chat_history(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $clean = [];
    foreach (array_slice($raw, -CHAT_MAX_HISTORY) as $message) {
        $role = is_array($message) ? ($message['role'] ?? '') : '';
        $content = is_array($message) ? ($message['content'] ?? '') : '';
        if (!in_array($role, ['user', 'assistant'], true) || !is_string($content)) {
            continue;
        }

        $content = trim(mb_substr($content, 0, CHAT_MAX_LENGTH));
        if ($content === '') {
            continue;
        }

        // Merge consecutive messages from the same role so roles always alternate
        if ($clean && $clean[count($clean) - 1]['role'] === $role) {
            $clean[count($clean) - 1]['content'] .= "\n\n" . $content;
            continue;
        }
        $clean[] = ['role' => $role, 'content' => $content];
    }

    while ($clean && $clean[0]['role'] !== 'user') {
        array_shift($clean);
    }

    return ($clean && end($clean)['role'] === 'user') ? $clean : [];
}

function ask_claude(array $messages): string
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $user = current_user();
    $system = AI_SYSTEM_PROMPT . "\n\n" . ($user
        ? "The user is logged in as {$user['first_name']} (account type: " . role_label($user['role']) . ', portal: ' . ROLES[$user['role']]['portal'] . ').'
        : 'The user is not logged in yet.');

    // Search the knowledge base with the user's latest messages, so short follow-ups keep their context
    $userMessages = array_column(array_filter($messages, fn ($m) => $m['role'] === 'user'), 'content');
    $articles = kb_search(implode("\n", array_slice($userMessages, -2)));
    $system .= $articles
        ? "\n\nICCT knowledge base articles that match this conversation:\n\n" . kb_context($articles)
        : "\n\nNo ICCT knowledge base article matched this conversation; use general IT troubleshooting knowledge.";

    $client = new \Anthropic\Client(apiKey: ANTHROPIC_API_KEY);
    $message = $client->beta->messages->create(
        maxTokens: 4000,
        messages: $messages,
        model: AI_MODEL,
        system: $system,
        outputConfig: ['effort' => 'low'],
        // If a safety classifier declines, the API retries on a suitable fallback model
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );

    if ($message->stopReason === 'refusal') {
        return "Sorry, I can't help with that request. For IT concerns, please submit a ticket from your dashboard.";
    }

    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }

    $text = trim($text);
    if ($text === '') {
        throw new RuntimeException('Claude returned no text (stop reason: ' . $message->stopReason . ')');
    }

    return $text;
}

// Answers used when no API key is set or the AI is unreachable:
// the best knowledge base article first, then built-in keyword answers
function offline_reply(string $question): string
{
    $fromKnowledgeBase = kb_offline_reply($question);
    if ($fromKnowledgeBase !== null) {
        return $fromKnowledgeBase;
    }

    $answers = [
        '/\b(hi|hello|hey|good (morning|afternoon|evening)|kumusta|musta)\b/i' =>
            "Hello! I'm the ICCT IT Assistant. Tell me what's wrong, for example Wi-Fi, accounts, printers, projectors, or software, and I'll walk you through it.",
        '/wi-?fi|internet|network|connect|lan|signal|slow/i' =>
            "Let's fix your connection:\n1. Turn Wi-Fi off and on, or restart your device.\n2. Forget the campus network, then reconnect and log in again.\n3. Check if others nearby are also offline. If so, it may be an outage.\n4. Try moving closer to the access point.\nStill no luck? Submit a ticket with your room number so a technician can check the access point.",
        '/password|forgot|reset|locked|log ?in|account|portal|lms/i' =>
            "For account or password problems:\n1. Make sure Caps Lock is off and you're using your ICCT ID number or school email.\n2. Clear your browser cache or try a private window.\n3. If you're locked out, wait 5 minutes before trying again.\nIf you still can't get in, submit a ticket or visit the IT office with your school ID so we can verify and reset your account. Never share your password with anyone, including IT staff.",
        '/print|printer|paper jam|toner|ink/i' =>
            "For printer issues:\n1. Check that the printer is on and shows no error lights.\n2. Open the tray and remove any jammed paper carefully.\n3. Make sure you selected the right printer and it isn't paused.\n4. Restart the printer and your computer.\nIf it still won't print, submit a ticket with the printer's location.",
        '/projector|hdmi|tv|display|screen|speaker|mic|audio|sound/i' =>
            "For classroom AV issues:\n1. Check that the HDMI or VGA cable is firmly connected on both ends.\n2. Select the correct input source on the projector or TV.\n3. On Windows, press Win + P and choose Duplicate.\n4. For audio, check the volume and the selected output device.\nIf it's still not working, submit a ticket with the room number so a technician can come by.",
        '/email|gmail|outlook|mail/i' =>
            "For school email problems:\n1. Sign in at the official email page using your full school email address.\n2. Check your spam or junk folder for missing messages.\n3. Clear your browser cache or try another browser.\nIf you can't access your account at all, submit a ticket and IT will verify your identity.",
        '/install|software|app|license|office|windows|update|virus|error/i' =>
            "For software concerns:\n1. Restart the computer and try again.\n2. Note the exact error message, or take a screenshot.\n3. School computers need IT approval to install software.\nSubmit a ticket with the software name, the computer or room, and the error message so we can help.",
        '/ticket|submit|request|report|track|status/i' =>
            "To submit or track a ticket:\n1. Register for an account using your ICCT ID number, or log in if you already have one.\n2. Go to your dashboard to create a ticket and describe the issue.\n3. You can track its status there: Open, In Progress, or Resolved.",
        '/register|sign ?up|create account/i' =>
            "To create an account, click Register at the top of the page and fill in your name, ICCT ID number, email, and a password with at least 8 characters, including uppercase, lowercase, and a number.",
        '/thank|salamat|ty\b/i' =>
            "You're welcome! If anything else comes up, just ask or submit a ticket.",
    ];

    foreach ($answers as $pattern => $answer) {
        if (preg_match($pattern, $question)) {
            return $answer;
        }
    }

    if (looks_tagalog($question)) {
        return 'Matutulungan kita sa Wi-Fi at internet, account at password, printer, projector at audio, email, at software. Puwede mo bang ikuwento nang mas detalyado ang problema? Kung urgent, mag-submit ng ticket sa iyong dashboard.';
    }

    return "I can help with Wi-Fi and network, accounts and passwords, printers, classroom AV, email, and software issues. Could you describe the problem in a bit more detail? For anything urgent, please submit a ticket from your dashboard.";
}
