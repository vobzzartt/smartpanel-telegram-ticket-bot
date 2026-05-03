<?php

// Run from CLI only (block browser access)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}


// Load config file (update path to your config.php)
$config_path = __DIR__ . '/config.php'; // replace if needed
if (!file_exists($config_path)) {
    die("config.php not found\n");
}
require_once $config_path;


// Ensure required DB constants exist
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $const) {
    if (!defined($const)) {
        die("Missing {$const} in config.php\n");
    }
}


// Telegram bot token (replace with your own)
$BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN';

// Telegram admin chat ID (replace with your own)
$ADMIN_ID  = YOUR_TELEGRAM_CHAT_ID;


// File used to track sent messages (prevents duplicates)
$mapFile = __DIR__ . '/telegram_ticket_map.json';


// Connect to database using config values
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed\n");
}
$conn->set_charset('utf8mb4');


// Load existing message map
$map = file_exists($mapFile)
    ? json_decode(file_get_contents($mapFile), true)
    : [];

if (!is_array($map)) {
    $map = [];
}


// Handle Telegram reply 
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {

    $msg     = $update['message'];
    $chatId  = $msg['chat']['id'] ?? 0;
    $text    = trim($msg['text'] ?? '');
    $replyTo = $msg['reply_to_message']['message_id'] ?? null;

    if ($chatId != $ADMIN_ID || !$replyTo || $text === '' || !isset($map[$replyTo])) {
        exit('OK');
    }

    $ticketId = (int)$map[$replyTo];

    // Save admin reply
    $stmt = $conn->prepare("
        INSERT INTO ticket_messages
        (ticket_id, uid, author, support, message, is_read, created, changed)
        VALUES (?, 0, 'Admin', 1, ?, 1, NOW(), NOW())
    ");
    $stmt->bind_param("is", $ticketId, $text);
    $stmt->execute();
    $stmt->close();

    // Update ticket status
    $stmt = $conn->prepare("
        UPDATE tickets
        SET status = 'answered',
            admin_read = 1,
            changed = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $stmt->close();

    // Mark user messages as read
    $stmt = $conn->prepare("
        UPDATE ticket_messages
        SET is_read = 1
        WHERE ticket_id = ? AND support = 0
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $stmt->close();

    // Send confirmation back to Telegram
    file_get_contents(
        "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage?" .
        http_build_query([
            'chat_id' => $ADMIN_ID,
            'text' => "âœ… Reply sent to Ticket #{$ticketId}\n\n{$text}"
        ])
    );

    exit('OK');
}


// Scan tickets and send to Telegram 
echo "=== Ticket Scan ===\n";
echo "Run at: " . date('Y-m-d H:i:s') . "\n\n";


$sql = "
SELECT
    t.id AS ticket_id,
    t.subject,
    t.description,
    t.status,
    gu.email,
    CONCAT(gu.first_name, ' ', gu.last_name) AS fullname,
    (
        SELECT tm.message
        FROM ticket_messages tm
        WHERE tm.ticket_id = t.id AND tm.support = 0
        ORDER BY tm.id DESC
        LIMIT 1
    ) AS latest_message,
    (
        SELECT tm.id
        FROM ticket_messages tm
        WHERE tm.ticket_id = t.id AND tm.support = 0
        ORDER BY tm.id DESC
        LIMIT 1
    ) AS latest_msg_id
FROM tickets t
INNER JOIN general_users gu ON gu.id = t.uid
WHERE t.status IN ('pending', 'customer-reply')
ORDER BY t.changed DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("Query failed\n");
}

echo "Tickets needing attention: {$res->num_rows}\n\n";

$sent = 0;
$newMap = [];

while ($row = $res->fetch_assoc()) {

    $ticketId = $row['ticket_id'];
    $latestMsg = trim($row['latest_message'] ?: $row['description']);
    $latestMsgId = $row['latest_msg_id'] ?: 'initial';

    $uniqueKey = $ticketId . '_' . $latestMsgId;

    if (isset($map['alerted_' . $uniqueKey])) {
        continue;
    }

    $messageText =
        "📨 *New Support Ticket*\n\n" .
        "*Ticket ID:* `{$ticketId}`\n" .
        "*User:* {$row['fullname']}\n" .
        "*Email:* {$row['email']}\n" .
        "*Subject:* {$row['subject']}\n\n" .
        "*Latest Message:*\n{$latestMsg}\n\n" .
        "👉 Swipe to reply";

    $payload = [
        'chat_id' => $ADMIN_ID,
        'parse_mode' => 'Markdown',
        'text' => $messageText,
        'reply_markup' => json_encode(['force_reply' => true])
    ];

    $resp = file_get_contents(
        "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage",
        false,
        stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload)
            ]
        ])
    );

    $data = json_decode($resp, true);

    if (!empty($data['ok'])) {
        $tgMsgId = $data['result']['message_id'];
        $newMap[$tgMsgId] = $ticketId;
        $newMap['alerted_' . $uniqueKey] = true;
        $sent++;
        echo "Alert sent for Ticket #{$ticketId}\n";
    }
}


// Save updated map
file_put_contents($mapFile, json_encode($newMap, JSON_PRETTY_PRINT));

echo "\nAlerts sent: {$sent}\n";
echo "Done.\n";

$conn->close();