# 🛡️ Relay Station: Official WAF Defense Guide

> **CLASSIFIED DOCUMENT:** Optimal Cloudflare Configuration for Relay Nodes.

If you are hosting your Relay Station on a Shared Hosting environment or a low-resource VPS, you are vulnerable to Layer 7 DDoS attacks or connection flooding. Implementing a Web Application Firewall (WAF) like **Cloudflare** is highly recommended. 

However, Cloudflare's default "Bot Fight Mode" is known to block legitimate organic P2P API requests (such as Lighthouse pings and E2E Laser Links). 

Follow these 3 tactical rules to set up the perfect Cloudflare shield for your Relay Station.

---

## 🟢 1. The Bypass Rule (Allow P2P Traffic)
You must instruct Cloudflare to ignore its strict browser-checking for API endpoints. Relay Stations communicate server-to-server via cURL, which Cloudflare often mistakes for a bot.

**Action:**
1. Go to your Cloudflare Dashboard > **Security** > **WAF** > **Custom Rules**.
2. Click **Create rule**.
3. Name it: `Relay P2P API Allow`
4. Under **If incoming requests match**, select **Custom filter expression** and use this logic:
   * Field: `URI Path`
   * Operator: `contains`
   * Value: `/api_` (This will cover `api_register.php`, `api_inbox.php`, `api_directory.php`, etc.)
5. Under **Then... (Choose action)**, select **Skip**.
6. Check the boxes to skip: `All remaining custom rules`, `Rate limiting rules`, and `Super Bot Fight Mode` (if available).
7. Deploy the rule.

---

## 🟡 2. The Rate Limiting Rule (Protect Core Memory)
To prevent your SQLite database from locking up due to heavy bombardment or brute-force login attempts, you must limit how many requests a single IP can make.

**Action:**
1. Go to **Security** > **WAF** > **Rate Limiting rules**.
2. Click **Create rule**.
3. Name it: `Relay Shield Wall`
4. Under **If incoming requests match**, set it to:
   * Field: `URI Path`
   * Operator: `contains`
   * Value: `/console.php`
5. Under **With the same characteristics**, select `IP`.
6. Under **When rate exceeds**, set it to:
   * Requests: `30`
   * Period: `1 minute`
7. Under **Then... (Choose action)**, select **Block** (or **Managed Challenge**).
8. Deploy the rule.

*(Note: The internal `api_inbox.php` and `api_register.php` already have built-in PHP rate limiters, but adding this Cloudflare rule for `console.php` prevents brute-force botnets).*

---

## 🔴 3. Bot Fight Mode Optimization
Cloudflare's Bot Fight Mode throws JS challenges (CAPTCHA) to suspicious visitors. If it's too aggressive, your friends won't be able to load your Public Hologram (Timeline).

**Action:**
1. Go to **Security** > **Bots**.
2. Ensure **Bot Fight Mode** is toggled **ON** to block actual malicious scrapers.
3. However, go to **Security** > **Settings** and ensure your **Security Level** is set to **Medium** (Do not use "I'm Under Attack!" unless you are actively being targeted, as it will break P2P synchronization).

---

> By deploying these 3 rules, your Relay Station will have an impenetrable energy shield that blocks attackers while keeping the E2E Laser Links wide open for allied nodes. Good luck, Commander.