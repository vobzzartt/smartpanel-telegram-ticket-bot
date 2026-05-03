# SmartPanel SMM Telegram Ticket Bot

A Telegram bot that lets SmartPanel admins receive ticket updates and reply directly from Telegram without logging into the admin panel.

---

## Why This Exists

In SmartPanel, handling support tickets usually means:

- Logging into the admin dashboard
- Opening tickets one by one
- Replying manually
- Repeating this multiple times daily

This slows things down and makes support harder to manage.

This bot removes that process.

- New ticket messages are sent instantly to Telegram
- Admin sees the latest message immediately
- Admin replies directly from Telegram
- Replies are saved back into SmartPanel automatically

---

## Features

- Sends latest user ticket messages to Telegram
- Shows user name, email, subject, and message
- Reply directly from Telegram (swipe → reply)
- Saves replies into SmartPanel database
- Updates ticket status automatically
- Works with cron and Telegram webhook

---

## How It Works

1. User sends or updates a ticket in SmartPanel  
2. SmartPanel stores the message in the database  
3. Script detects new user messages  
4. Message is sent to Telegram  
5. Admin replies from Telegram  
6. Bot saves reply and updates ticket  

Everything stays in sync.

---

## Requirements

- PHP 7.4+
- MySQL / MariaDB
- SmartPanel installed
- Telegram bot
- Hosting with cron support

---

## Files

smartpanel-telegram-ticket-bot/

- ticketbot.php  
- README.md  
- telegram_ticket_map.json (auto-created)

---

## Installation

### 1. Create Telegram Bot

- Open Telegram  
- Search for @BotFather  
- Run /newbot  
- Copy your bot token  

---

### 2. Get Your Telegram ID

- Search for @userinfobot  
- Copy your numeric ID  

---

### 3. Upload Script

Upload the file to your server:

/public_html/ticketbot.php

---

### 4. Configure Script

Edit ticketbot.php and update:

$BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN';  
$ADMIN_ID  = YOUR_TELEGRAM_ID;  

If using config file, make sure your database constants are correctly set.

---

### 5. Set Webhook

Open in browser:

https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=https://yourdomain.com/ticketbot.php

If successful, you will get:
"ok": true

---

### 6. Test Script

Open:

https://yourdomain.com/ticketbot.php

Expected output:

=== Ticket Scan ===  
Run at: YYYY-MM-DD HH:MM:SS  

Tickets needing attention: X  
Alert sent for Ticket #ID  

Alerts sent: X  
Done.

---

### 7. Set Cron Job

In cPanel → Cron Jobs:

curl -s https://yourdomain.com/ticketbot.php >/dev/null 2>&1

This runs the script based on your cron schedule to check for new tickets.

---

## Security Notes

- Uses prepared SQL statements  
- No raw user input in queries  
- Prevents duplicate Telegram alerts  
- Limits replies to your Telegram ID  

---

## Who This Is For

- SmartPanel SMM owners  
- Admins handling support tickets  
- Anyone who wants faster response workflow  

---

## License

MIT

---

## Author

Victor Bodude  
https://victorbodude.com