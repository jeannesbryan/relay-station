# 🛡️ Relay Station: Official WAF Defense Guide

> **CLASSIFIED DOCUMENT:** Optimal Cloudflare Configuration for Relay Nodes.

If you are hosting your Relay Station on a Shared Hosting environment or a low-resource VPS, you are vulnerable to Layer 7 DDoS attacks or connection flooding. Implementing a Web Application Firewall (WAF) like **Cloudflare (Free Plan)** is highly recommended. 

However, Cloudflare's default strict security settings are known to block legitimate organic P2P API requests (such as Lighthouse pings and E2E Laser Links). 

Follow these 3 tactical rules to set up the perfect Cloudflare shield for your Relay Station using a completely free account.

---

## 🟢 1. The Bypass Rule (Allow P2P Traffic)
You must instruct Cloudflare to ignore its strict browser-checking for API endpoints. Relay Stations communicate server-to-server via cURL, which Cloudflare often mistakes for a malicious bot.

**Action:**
1. Go to your Cloudflare Dashboard > **Security** > **WAF** > **Custom Rules**.
2. Click **Create rule**.
3. Name it: `Relay P2P API Allow`
4. Under **If incoming requests match**, select **Custom filter expression** and use this logic:
   * Field: `URI Path`
   * Operator: `contains`
   * Value: `/api_`
5. Under **Then... (Choose action)**, select **Skip**.
6. Check the boxes to skip: `All remaining custom rules`, `Rate limiting rules`, and `Super Bot Fight Mode` (if available).
   *(⚠️ Do NOT skip "All managed rules" to ensure you are still protected against standard SQLi/XSS attacks).*
7. Deploy the rule.

---

## 🟡 2. The Rate Limiting Rule (Protect Core Memory)
To prevent your SQLite database from locking up due to heavy bombardment or brute-force login attempts, you must limit how many requests a single IP can make to your control room. 

*(Note: Cloudflare Free Plan limits this action to a 10-second block. This is perfectly fine, as Relay Station V6.1 has a built-in PHP lockout that will freeze the attacker for 15 minutes if they fail 5 times).*

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
   * Requests: `10`
   * Period: `10 seconds`
7. Under **Then... (Choose action)**, select **Block** (Duration: 10 seconds).
8. Deploy the rule.

---

## 🔴 3. Bot Fight Mode & Security Level
Cloudflare's Bot Fight Mode throws JS challenges to suspicious visitors. If the global security level is too aggressive, your human friends won't be able to load your Public Hologram (Timeline) without getting stuck on a loading screen.

**Action:**
1. Go to **Security** > **Bots**.
2. Ensure **Bot Fight Mode** is toggled **ON** to block actual malicious scrapers.
3. Then, go to **Security** > **Settings** on the left sidebar.
4. Ensure your **Security Level** is set to **Medium**. *(Do not use "I'm Under Attack!" unless you are actively being targeted, as it will completely sever P2P machine communications).*

---

> By deploying these 3 rules, your Relay Station will have an impenetrable energy shield that blocks attackers while keeping the E2E Laser Links wide open for allied nodes. Good luck, Commander.
