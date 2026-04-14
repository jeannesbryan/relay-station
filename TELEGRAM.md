# 👁️ THE ORACLE: Telegram Webhooks Guide

> A Tactical Guide to Enabling Real-Time Radar Notifications to Your Smartphone.

**The Oracle** is Relay Station's Early Warning System. Instead of keeping the dashboard open in your browser, your server will automatically fire a message to your Telegram account whenever crucial activity occurs, such as:
* 🚨 **Security:** Alerts when someone (or yourself) successfully logs into the Control Room.
* ✉️ **Laser Links:** Alerts when a new E2E Direct Message docks at your station.
* 🤝 **Follower Radar:** Alerts when another station locks onto your coordinates (follows you).
* 📡 **Sonar Pulse:** Alerts when a tactical Morse signal is received.

To activate this feature in your **Control Room**, you need two keys: a **Bot Token** and a **Chat ID**. Follow the steps below to get them for free.

---

## 🛠️ PHASE 1: Creating the Courier (Bot Token)

We need to create a personal "Bot" whose sole purpose is to serve your station.

1. Open the **Telegram** app on your phone or PC.
2. In the search bar, look for **`@BotFather`** (Look for the official blue verification tick).
3. Start the chat and type the command: `/newbot`
4. BotFather will ask for a **Name** for your bot (e.g., *Relay Oracle*).
5. Next, provide a **Username**. It must not contain spaces and must end with `bot` (e.g., *MyRelayStation_bot*).
6. Done! BotFather will reply with a long message containing your **HTTP API Token**.
   * It looks like a long string of numbers and letters (Example: `1234567890:ABCDefGhIjKlMnOpQrStUvWxYz`).
   * **Save this token!** This is your `telegram_bot_token`. Do not share it with anyone.

---

## 📡 PHASE 2: Securing Your Coordinates (Chat ID)

Your bot is ready, but it doesn't know where to send the messages yet. We need to find your unique Telegram account ID.

1. In the Telegram search bar, look for a bot named **`@userinfobot`** or **`@GetIDsBot`**.
2. Click **Start** or type `/start`.
3. The bot will reply with your profile info, including a line that says **ID**.
   * It looks like a string of numbers (Example: `987654321` or `-100987654321`).
   * **Save this number!** This is your `telegram_chat_id`.

---

## 🚀 PHASE 3: Activating the Radar (Crucial!)

Telegram bots have one absolute rule: **A bot cannot send you a message unless you initiate the conversation first.**

1. Search for the **Username** of the bot you just created in Phase 1 (e.g., `@MyRelayStation_bot`).
2. Open the chat and click the **Start** button at the bottom of the screen.
3. You don't need to type anything else. The communication channel is now open.

---

## ⚙️ PHASE 4: Igniting the Engine in Relay Station

1. Open your **Relay Station** and log in with your Master Passcode.
2. Navigate to the **[ ⚙️ ] Control Room**.
3. Scroll down until you find the **[ THE ORACLE: TELEGRAM BOT ]** section.
4. Check the box to **Enable** it.
5. Paste your **Bot Token** (from Phase 1) and **Chat ID** (from Phase 2) into the designated fields.
6. Re-enter your Master Passcode for authorization, then click **[ APPLY CONFIGURATION ]**.

✅ **Mission Accomplished!** If configured correctly, The Oracle will immediately send a test transmission to your Telegram reading: `> ORACLE SYSTEM ONLINE. Radar is active.`