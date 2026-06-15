<?php
/**
 * rssTea - Discord Notification Pipeline
 *
 * Compares old and new states of parsed RSS items to discover newly published
 * content and sends high-fidelity notifications as rich Discord embeds.
 */

$newFeedFile = 'feed.json';
$oldFeedFile = 'old_feed.json';

// Retrieve the webhook URL from environment variables
$webhookUrl = getenv('DISCORD_WEBHOOK');

if (empty($webhookUrl)) {
    echo "Discord webhook URL is empty. Skipping notification task.\n";
    exit(0);
}

if (!file_exists($newFeedFile)) {
    echo "No fresh feed file found ({$newFeedFile}). Exiting.\n";
    exit(0);
}

// Parse feed contents safely
$newFeed = json_decode(file_get_contents($newFeedFile), true);
if (!is_array($newFeed)) {
    echo "Error parsing fresh feed file JSON.\n";
    exit(1);
}

$oldFeed = [];
if (file_exists($oldFeedFile)) {
    $oldFeed = json_decode(file_get_contents($oldFeedFile), true);
    if (!is_array($oldFeed)) {
        $oldFeed = [];
    }
}

// Index previous URLs/Titles for fast lookup to locate new entries
$oldIndexes = [];
foreach ($oldFeed as $item) {
    if (!empty($item['link'])) {
        $oldIndexes[$item['link']] = true;
    } elseif (!empty($item['title'])) {
        $oldIndexes[$item['title']] = true;
    }
}

// Find brand-new items in chronological order (oldest first)
$newItems = [];
foreach (array_reverse($newFeed) as $item) {
    $link = $item['link'] ?? '';
    $title = $item['title'] ?? '';
    
    $uniqueKey = !empty($link) ? $link : $title;
    if (!empty($uniqueKey) && !isset($oldIndexes[$uniqueKey])) {
        $newItems[] = $item;
    }
}

// Safety threshold: If this is the first deployment or old feed is empty,
// only notify about the single newest item to avoid flooding the channel.
if (empty($oldFeed)) {
    echo "First execution or empty database detected. Restricting to latest entry to prevent flooding.\n";
    if (!empty($newItems)) {
        $newItems = [end($newItems)];
    }
}

$newItemCount = count($newItems);
if ($newItemCount === 0) {
    echo "No new updates found in subscription feeds. Keeping Discord server quiet.\n";
    exit(0);
}

echo "Detected {$newItemCount} brand-new feed updates. Generating Discord Embed payloads...\n";

// Gather context for customizable branding properties
$repo = getenv('GITHUB_REPOSITORY') ?: 'avadhesh18/rssTea';
$faviconUrl = "https://avatars.githubusercontent.com/u/153463910?v=4";

// Discord allows up to 10 rich embeds per webhook request
$chunkedItems = array_chunk($newItems, 10);

foreach ($chunkedItems as $chunk) {
    $embeds = [];
    
    foreach ($chunk as $post) {
        $title = mb_strimwidth($post['title'] ?? 'New content released', 0, 120, '...');
        $link = $post['link'] ?? '';
        $channel = mb_strimwidth($post['ch'] ?? 'Unknown Feed', 0, 50, '...');
        $dateUnix = $post['date'] ?? time();
        
        $dateFormatted = date('M d, Y • g:i A', $dateUnix);
        $isoTimestamp = date('Y-m-d\TH:i:s\Z', $dateUnix);
        $imageUrl = $post['image'] ?? '';
        
        // Differentiate formats (Audio or Video)
        $isAudio = !empty($post['audio']);
        $formatIcon = $isAudio ? '🎙️ Podcast / Audio' : '📺 Video / Article';
        
        $embed = [
            'title' => $title,
            'url' => $link,
            'description' => "🍵 Fresh content available in your subscription!",
            'color' => 5026037, // Matching rssTea default elegant theme blue (#4CB0F5)
            'fields' => [
                [
                    'name' => '📢 Creator',
                    'value' => $channel,
                    'inline' => true
                ],
                [
                    'name' => '🏷️ Format',
                    'value' => $formatIcon,
                    'inline' => true
                ],
                [
                    'name' => '📅 Released',
                    'value' => $dateFormatted,
                    'inline' => false
                ]
            ],
            'footer' => [
                'text' => 'AYT04',
                'icon_url' => $faviconUrl
            ],
            'timestamp' => $isoTimestamp
        ];
        
        // Add thumbnail if valid image exists
        if (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $embed['thumbnail'] = [
                'url' => $imageUrl
            ];
        }
        
        $embeds[] = $embed;
    }
    
    // Package JSON request payload
    $payload = json_encode([
        'username' => 'AYT04',
        'avatar_url' => $faviconUrl,
        'embeds' => $embeds
    ]);
    
    // Transport via CURL
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; rssTeaBot/1.0; +https://github.com/' . $repo . ')',
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "Curl error sending webhook request: {$curlError}\n";
    } elseif ($httpCode < 200 || $httpCode >= 300) {
        echo "Discord responded with HTTP error code: {$httpCode}. Response: {$response}\n";
    } else {
        echo "Successfully dispatched batch of notifications to Discord channel!\n";
    }
}
?>
