<?php
// Knowledge base search for the AI assistant (retrieval step of RAG).
// Articles carry English and Tagalog keywords, so a question in either language finds them.

const KB_STOPWORDS = [
    // English
    'a', 'an', 'and', 'are', 'at', 'be', 'but', 'can', 'cant', 'do', 'does', 'dont', 'for', 'from', 'get', 'has', 'have',
    'help', 'how', 'i', 'im', 'in', 'is', 'it', 'its', 'me', 'my', 'not', 'of', 'on', 'or', 'please', 'pls', 'so', 'the',
    'there', 'this', 'that', 'to', 'up', 'was', 'what', 'when', 'why', 'with', 'wont', 'you', 'your', 'still', 'again',
    // Tagalog / Taglish
    'ako', 'ang', 'ano', 'ay', 'ba', 'bakit', 'din', 'dito', 'doon', 'ito', 'iyan', 'ka', 'kasi', 'ko', 'lang', 'mag',
    'maka', 'may', 'mga', 'mo', 'na', 'naka', 'nag', 'naman', 'ng', 'ni', 'pa', 'paano', 'pano', 'po', 'rin', 'sa', 'si',
    'tulong', 'ung', 'yan', 'yung', 'hindi', 'di', 'wala', 'walang', 'ayaw', 'sana', 'kayo', 'nyo', 'niyo',
];

// Tagalog words and how sure each one makes us: 2 = Tagalog on its own, 1 = needs another marker
const KB_TAGALOG_MARKERS = [
    'ng' => 2, 'mga' => 2, 'po' => 2, 'yung' => 2, 'ung' => 2, 'hindi' => 2, 'wala' => 2, 'walang' => 2, 'ayaw' => 2,
    'paano' => 2, 'pano' => 2, 'bakit' => 2, 'naman' => 2, 'kasi' => 2, 'nag' => 2, 'ata' => 2, 'salamat' => 2,
    'sira' => 2, 'nasira' => 2, 'nawala' => 2, 'paki' => 2, 'gumagana' => 2, 'kailangan' => 2, 'pwede' => 2, 'puwede' => 2,
    'ang' => 1, 'sa' => 1, 'ako' => 1, 'ko' => 1, 'mo' => 1, 'ba' => 1, 'lang' => 1, 'din' => 1, 'rin' => 1,
    'pa' => 1, 'na' => 1, 'ano' => 1, 'sana' => 1,
];

// Lowercases and evens out spellings so "Wi-Fi", "wi fi" and "wifi" match
function kb_normalize(string $text): string
{
    $text = mb_strtolower($text);
    $text = preg_replace(['/\bwi[\s-]?fi\b/u', '/\be-?mail\b/u', '/\blog[\s-]?in\b/u', '/\bpop[\s-]?ups?\b/u'], ['wifi', 'email', 'login', 'popup'], $text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

// Meaningful words of a text, with a light plural strip ("printers" -> "printer")
function kb_tokens(string $text): array
{
    $tokens = [];
    foreach (explode(' ', kb_normalize($text)) as $word) {
        if (mb_strlen($word) < 2 || in_array($word, KB_STOPWORDS, true)) {
            continue;
        }
        if (mb_strlen($word) > 4 && str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            $word = mb_substr($word, 0, -1);
        }
        $tokens[$word] = true;
    }
    return array_keys($tokens);
}

function looks_tagalog(string $text): bool
{
    $weight = 0;
    foreach (explode(' ', kb_normalize($text)) as $word) {
        $weight += KB_TAGALOG_MARKERS[$word] ?? 0;
    }
    return $weight >= 2;
}

/**
 * Best-matching active articles for a question, highest score first.
 * Keyword hits weigh most, then title, then body text. Returns [] if the table is missing.
 */
function kb_search(string $query, int $limit = 3, bool $activeOnly = true): array
{
    $queryTokens = kb_tokens($query);
    if (!$queryTokens) {
        return [];
    }
    $normalizedQuery = ' ' . kb_normalize($query) . ' ';

    try {
        $articles = db()->query('SELECT * FROM kb_articles' . ($activeOnly ? ' WHERE is_active = 1' : ''))->fetchAll();
    } catch (PDOException $ex) {
        error_log('Knowledge base unavailable (run database/migrations/004_knowledge_base.sql): ' . $ex->getMessage());
        return [];
    }

    $results = [];
    foreach ($articles as $article) {
        $keywordTokens = kb_tokens($article['keywords']);
        $titleTokens = kb_tokens($article['title']);
        $bodyTokens = kb_tokens($article['content'] . ' ' . $article['content_tl']);

        $score = 0;
        foreach ($queryTokens as $token) {
            $score += match (true) {
                in_array($token, $keywordTokens, true) => 3,
                in_array($token, $titleTokens, true)   => 2,
                in_array($token, $bodyTokens, true)    => 1,
                default                                => 0,
            };
        }
        // Whole multi-word phrases, e.g. "paper jam" or "walang internet"
        foreach (explode(',', $article['keywords']) as $phrase) {
            $phrase = kb_normalize($phrase);
            if (str_contains($phrase, ' ') && str_contains($normalizedQuery, " $phrase ")) {
                $score += 4;
            }
        }

        if ($score >= 3) {
            $results[] = $article + ['score' => $score];
        }
    }

    usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($results, 0, $limit);
}

// Articles formatted for the AI's system prompt
function kb_context(array $articles): string
{
    $blocks = [];
    foreach ($articles as $article) {
        $block = "### {$article['title']}\n{$article['content']}";
        if (!empty($article['content_tl'])) {
            $block .= "\n\nTagalog version:\n{$article['content_tl']}";
        }
        $blocks[] = $block;
    }
    return implode("\n\n", $blocks);
}

// Answer straight from the best article, for when the AI is not available
function kb_offline_reply(string $question): ?string
{
    $articles = kb_search($question, 2);
    if (!$articles) {
        return null;
    }

    $tagalog = looks_tagalog($question);
    $best = $articles[0];
    $reply = $best['title'] . "\n\n" . ($tagalog && !empty($best['content_tl']) ? $best['content_tl'] : $best['content']);
    if (isset($articles[1])) {
        $reply .= "\n\n" . ($tagalog ? 'Baka makatulong din: ' : 'You might also check: ') . $articles[1]['title'];
    }
    return $reply;
}
