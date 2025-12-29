# SmartPanel SMM Telegram Ticket Bot

A production-ready Telegram bot that allows SmartPanel administrators to receive ticket updates and reply directly from Telegram, without logging into the SmartPanel admin dashboard everytime.

This project was built to solve the problem of delayed support responses caused by repeatedly logging into admin panels just to read or reply to tickets.

---

## Why This Exists

In SmartPanel (and similar SMM panels), admins usually have to:
- Log in to the admin dashboard
- Navigate to tickets
- Open each ticket
- Reply manually
- Repeat this process multiple times daily

This becomes slow, stressful, and inefficient

This bot removes that friction completely.

With this system:
- New user ticket messages are pushed instantly to Telegram
- Receive new Ticket alert instantly
- Admin can read the *latest user message*
- Admin can reply directly from Telegram
- Ticket status updates correctly in SmartPanel

No extra logins. No delays. No missed tickets.

---

## What This Bot Does (Features)

- Sends latest user ticket messages to Telegram
- Shows user name, email, subject, and message
- Supports replying directly from Telegram
- Saves admin replies into SmartPanel correctly
- Updates ticket status to answered
- Works via:
  - Direct URL execution
  - Cron jobs
  - Telegram webhook

---

## How It Works (High Level)

1. User sends or continues a ticket on SmartPanel
2. SmartPanel stores the message in the database
3. This script:
   - Detects the user message
   - Sends it to Telegram
4. Admin replies on Telegram (swipe → reply)
5. The bot:
   - Saves the reply in ticket_messages
   - Updates the ticket status
   - Marks messages as read
   - Clears the admin notification

Everything stays perfectly in sync.

---

## Requirements

- PHP 7.4 or higher
- MySQL / MariaDB
- SmartPanel SMM script installed
- Telegram Bot
- cPanel / VPS / Hosting with cron support

---

## Folder Structure

smartpanel-telegram-ticket-bot/
│
├── ticketbot.php
├── README.md
├── telegram_ticket_map.json   (auto-created at runtime)


---

## Installation Guide (Step-by-Step)

### 1. Create a Telegram Bot

1. Open Telegram
2. Search for @BotFather
3. Run: /start or /newbot

4. Copy the Bot Token (keep it secret)

---

### 2. Get Your Telegram Admin ID

1. Open Telegram
2. Search for @userinfobot
3. Copy your numeric Telegram ID

---

### 3. Upload the Script

Upload ticketbot.php to your server, for example: /public_html/ticketbot.php // or file path

---

### 4. Configure the Script

Edit ticketbot.php and set:

```php
$BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN';
$ADMIN_ID  = YOUR_TELEGRAM_ID;

$dbHost = 'localhost';
$dbName = 'DATABASE_NAME';
$dbUser = 'DATABASE_USER';
$dbPass = 'DATABASE_PASSWORD';

5. Set Telegram Webhook (Required for Replies)

Open your browser and visit:  https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=https://yourdomain.com/ticketbot.php
If successful, Browser replies with "ok": true.

This enables swipe-to-reply from Telegram.

6. Test URL Mode (Manual)
Open: https://yourdomain.com/ticketbot.php
You should see output like:
=== Ticket Scan ===
Run at: 2025-12-26 12:00:00

Tickets needing attention: 
Alert sent for Ticket #

Alerts sent: 
Done.

7. Set Up Cron Job (Recommended)

In cPanel → Cron Jobs: curl -s https://yourdomain.com/ticketbot.php >/dev/null 2>&1 
This check for new pending ticket per each time you set it to run.

////////////

Security Notes
 • This Script Uses prepared SQL statements
 • No raw user input in queries
 • Resistant to SQL injection
 • Telegram message mapping prevents replay attacks

⸻

Who This Is For
 • SmartPanel SMM owners
 • SMM panel admins
 • Support teams handling many tickets
 • Anyone who wants one time faster support responses

⸻

License

This project is open-source.
You are free to use, modify, and improve it.

⸻

This project was created after real production debugging,
real database analysis, and real SmartPanel behavior testing.

Author

Built by Victor Bodude
https://victorbodude.name.ng
Happy Automating! 🎉

