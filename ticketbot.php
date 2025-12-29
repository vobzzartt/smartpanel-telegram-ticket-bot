<?php
/* =========================================================
   Telegram Ticket Bot for SmartPanel (PHP)
   ---------------------------------------------------------
   This script sends SmartPanel support tickets to Telegram
   and allows admins to reply directly from Telegram.
   Replies are saved back into the SmartPanel ticket system.
   ========================================================= */

/* ================== CONFIGURATION ================== */

// Telegram bot token 
$BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN';

// Telegram user ID of the admin to receive tickets
$ADMIN_ID  = YOUR_TELEGRAM_CHAT_ID;

// Database connection (SmartPanel database)
$dbHost = 'localhost';
$dbName = 'YOUR_DATABASE_NAME';
$dbUser = 'YOUR_DATABASE_USERNAME';
$dbPass = 'YOUR_DATABASE_PASSWORD';

// File used to prevent duplicate Telegram alerts
$mapFile = __DIR__ . '/telegram_ticket_map.json';

/* ================== BASIC SETUP ================== */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

/* ================== DATABASE ================== */

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Database connection failed\n");
}
$conn->set_charset('utf8mb4');

/* ================== LOAD MESSAGE MAP ================== */

$map = file_exists($mapFile)
    ? json_decode(file_get_contents($mapFile), true)
    : [];

if (!is_array($map)) {
    $map = [];
}

/* =========================================================
   1) TELEGRAM WEBHOOK MODE (ADMIN REPLY)
   ---------------------------------------------------------
   Runs when admin replies to a Telegram message.
   Saves reply into SmartPanel ticket_messages table.
   ========================================================= */

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

    // Mark all user messages as read
    $stmt = $conn->prepare("
        UPDATE ticket_messages
        SET is_read = 1
        WHERE ticket_id = ? AND support = 0
    ");
    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $stmt->close();

    // Confirmation back to Telegram
    file_get_contents(
        "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage?" .
        http_build_query([
            'chat_id' => $ADMIN_ID,
            'text' => "âœ… Reply sent to Ticket #{$ticketId}\n\n{$text}"
        ])
    );

    exit('OK');
}

/* =========================================================
   2) CRON / MANUAL MODE (SEND USER MESSAGES TO TELEGRAM)
   ---------------------------------------------------------
   Finds tickets that need admin attention and sends the
   latest user message to Telegram.
   ========================================================= */

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
        "ðŸ“¨ *New Support Ticket*\n\n" .
        "*Ticket ID:* `{$ticketId}`\n" .
        "*User:* {$row['fullname']}\n" .
        "*Email:* {$row['email']}\n" .
        "*Subject:* {$row['subject']}\n\n" .
        "*Latest Message:*\n{$latestMsg}\n\n" .
        "ðŸ‘‰ Swipe to reply";

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

file_put_contents($mapFile, json_encode($newMap, JSON_PRETTY_PRINT));

echo "\nAlerts sent: {$sent}\n";
echo "Done.\n";

$conn->close();