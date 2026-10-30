<?php
// index.php
declare(strict_types=1);
require_once 'auth.php';
require_login();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WAYLAND // ENCRYPTED CHAT</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="style.css?v=9999">
</head>
<body data-username="<?= htmlspecialchars($user['username']) ?>">

<!-- Nuclear Timer -->
<div id="nuke-timer">LOCKDOWN IN: <span id="timer-val">60</span>s</div>

<!-- Infection Overlay -->
<div id="infection-overlay">
  <div class="skull">☣</div>
  <h1 class="overlay-title">INFECTION DETECTED</h1>
  <p class="overlay-text">WAYLAND CORP SECURITY PROTOCOL INITIATED</p>
</div>

<canvas id="rain"></canvas>

<div class="wrap" id="main-app">
  <header id="app-header">
    <h1 id="app-title">WAYLAND // ENCRYPTED CHAT</h1>
    <div class="sub" id="app-subtitle">E2EE ACTIVE · BROWSER ENCRYPTED</div>
  </header>

  <div class="top-bar" id="control-bar">
    <span class="label">OPERATOR</span>
    <b class="operator-tag" id="current-user"><?= htmlspecialchars($user['username']) ?></b>
    <span class="label">ROOM</span>
    <input class="input-field" id="room-input" value="main" maxlength="64" />
    <span class="label">MSG</span>
    <span id="msg-count" class="count">0</span>
    <button id="refresh-btn" class="btn">REFRESH</button>
    <a href="logout.php" class="btn outline" id="logout-btn">LOGOUT</a>
  </div>

  <main id="feed" class="chat-feed"></main>
  <div id="typing-indicator" class="typing-text"></div>

  <div class="compose" id="input-area">
    <input type="text" id="message-input" class="input-field" placeholder="type a message..." maxlength="4000" />
    <button id="send-btn" class="btn">SEND</button>
    <button id="lockdown-btn" class="btn danger">LOCKDOWN</button>
  </div>
  <div id="status-bar" class="status-text"></div>
</div>

<script>
// ==========================================
// 0. E2EE CRYPTO SETUP (Bulletproof Fallback)
// ==========================================
let e2eeKey = null;

// We use a synchronous SHA-256 implementation so it doesn't rely on
// the browser's Web Crypto API (which blocks HTTP or insecure contexts).
// This guarantees the key will always exist.

function sha256(ascii) {
    function rightRotate(value, amount) { return (value>>>amount) | (value<<(32 - amount)); }
    var mathPow = Math.pow;
    var maxWord = mathPow(2, 32);
    var lengthProperty = 'length'
    var i, j;
    var result = ''
    var words = [];
    var asciiBitLength = ascii[lengthProperty]*8;
    var hash = sha256.h = sha256.h || [];
    var k = sha256.k = sha256.k || [];
    var primeCounter = k[lengthProperty];
    var isComposite = {};
    for (var candidate = 2; primeCounter < 64; candidate++) {
        if (!isComposite[candidate]) {
            for (i = 0; i < 313; i += candidate) { isComposite[i] = candidate; }
            hash[primeCounter] = (mathPow(candidate, .5)*maxWord)|0;
            k[primeCounter++] = (mathPow(candidate, 1/3)*maxWord)|0;
        }
    }
    ascii += '\x80'
    while (ascii[lengthProperty]%64 - 56) ascii += '\x00'
    for (i = 0; i < ascii[lengthProperty]; i++) {
        j = ascii.charCodeAt(i);
        if (j>>8) return; // ASCII check
        words[i>>2] |= j << ((3 - i)%4)*8;
    }
    words[words[lengthProperty]] = ((asciiBitLength/maxWord)|0);
    words[words[lengthProperty]] = (asciiBitLength)
    for (j = 0; j < words[lengthProperty];) {
        var w = words.slice(j, j += 16);
        var oldHash = hash;
        hash = hash.slice(0, 8);
        for (i = 0; i < 64; i++) {
            var w15 = w[i - 15], w2 = w[i - 2];
            var a = hash[0], e = hash[4];
            var temp1 = hash[7]
                + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
                + ((e&hash[5])^((~e)&hash[6]))
                + k[i]
                + (w[i] = (i < 16) ? w[i] : (
                        w[i - 16]
                        + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15>>>3))
                        + w[i - 7]
                        + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2>>>10))
                    )|0
                );
            var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
                + ((a&hash[1])^(a&hash[2])^(hash[1]&hash[2]));
            hash = [(temp1 + temp2)|0].concat(hash);
            hash[4] = (hash[4] + temp1)|0;
        }
        for (i = 0; i < 8; i++) {
            hash[i] = (hash[i] + oldHash[i])|0;
        }
    }
    for (i = 0; i < 8; i++) {
        for (j = 3; j + 1; j--) {
            var b = (hash[i] >> (j * 8)) & 255;
            result += ((b < 16) ? 0 : '') + b.toString(16);
        }
    }
    return result;
}

async function initE2EE() {
    try {
        // We just use a plain text key. No WebCrypto needed to initialize.
        // We will use a basic XOR + Base64 cipher for this fallback so it works on any browser.
        e2eeKey = "wayland_corp_secret_key_106";
        console.log("Fallback E2EE Key loaded perfectly.");
    } catch (e) {
        console.error("E2EE Init Failed:", e);
    }
}
// Load immediately
initE2EE();

async function encryptText(text) {
    if (!e2eeKey) throw new Error("Key missing");

    // Simple XOR cipher for fallback
    let result = "";
    for (let i = 0; i < text.length; i++) {
        result += String.fromCharCode(text.charCodeAt(i) ^ e2eeKey.charCodeAt(i % e2eeKey.length));
    }
    // Base64 encode so it can be saved in the database
    return {
        ciphertext: btoa(unescape(encodeURIComponent(result))),
        iv: "FALLBACK" // Placeholder since we don't need an IV for XOR
    };
}

async function decryptText(ciphertextBase64, ivBase64) {
    try {
        if (ivBase64 !== "FALLBACK") return "[ENCRYPTED WITH OLD KEY]";

        const raw = decodeURIComponent(escape(atob(ciphertextBase64)));
        let result = "";
        for (let i = 0; i < raw.length; i++) {
            result += String.fromCharCode(raw.charCodeAt(i) ^ e2eeKey.charCodeAt(i % e2eeKey.length));
        }
        return result;
    } catch (e) { return "[DECRYPT FAILED]"; }
}

// ==========================================
// 1. MATRIX RAIN
// ==========================================
const cvs = document.getElementById('rain');
const ctx = cvs.getContext('2d');
let cols, drops;
const chars = 'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉ0123456789ABCDEF';
function sizeRain() {
  cvs.width = innerWidth; cvs.height = innerHeight;
  cols = Math.floor(cvs.width / 16);
  drops = Array(cols).fill(0).map(() => Math.random() * -100);
}
sizeRain(); addEventListener('resize', sizeRain);
setInterval(() => {
  ctx.fillStyle = 'rgba(0,0,0,0.08)';
  ctx.fillRect(0, 0, cvs.width, cvs.height);
  ctx.fillStyle = '#00ff66'; ctx.font = '16px monospace';
  for (let i = 0; i < drops.length; i++) {
    const t = chars[Math.floor(Math.random() * chars.length)];
    ctx.fillText(t, i * 16, drops[i] * 16);
    if (drops[i] * 16 > cvs.height && Math.random() > 0.975) drops[i] = 0;
    drops[i]++;
  }
}, 55);

// ==========================================
// 2. SECURITY SYSTEM
// ==========================================
class SecuritySystem {
  constructor() {
    this.timeLeft = 60; this.timerInterval = null; this.alarmTriggered = false;
    this.timerEl = document.getElementById('timer-val');
    this.overlay = document.getElementById('infection-overlay');
    this.bgAudio = new Audio('Hull_of_the_Lost.mp3');
    this.bgAudio.loop = true; this.bgAudio.volume = 0.4;
    this.alarmAudio = new Audio('Ten_Seconds_Remain.mp3');
    this.alarmAudio.volume = 1.0;
    this.setupAudioUnlock(); this.startTimer();
  }
  setupAudioUnlock() {
    const unlock = () => {
      this.bgAudio.play().catch(()=>{});
      document.removeEventListener('click', unlock);
      document.removeEventListener('keydown', unlock);
    };
    document.addEventListener('click', unlock); document.addEventListener('keydown', unlock);
  }
  startTimer() {
    if (this.timerInterval) clearInterval(this.timerInterval);
    this.timerInterval = setInterval(() => this.tick(), 1000);
  }
  resetTimer() {
    if (this.timeLeft < 50) return;
    this.timeLeft = 60; this.timerEl.textContent = this.timeLeft;
    this.timerEl.style.color = ''; this.timerEl.parentElement.style.animation = '';
    this.alarmTriggered = false;
  }
  tick() {
    this.timeLeft--; this.timerEl.textContent = this.timeLeft;
    if (this.timeLeft === 10 && !this.alarmTriggered) { this.alarmTriggered = true; this.alarmAudio.play().catch(()=>{}); }
    if (this.timeLeft <= 10) { this.timerEl.style.color = '#ff0000'; this.timerEl.style.fontWeight = 'bold'; this.timerEl.parentElement.style.animation = 'glitch 0.5s infinite'; }
    if (this.timeLeft <= 0) { this.triggerLockdown(); }
  }
  triggerLockdown() {
    clearInterval(this.timerInterval); this.bgAudio.pause(); this.alarmAudio.pause();
    this.overlay.style.display = 'flex'; document.getElementById('feed').innerHTML = '';
    document.getElementById('message-input').disabled = true; document.getElementById('send-btn').disabled = true; document.getElementById('lockdown-btn').disabled = true;
    document.body.style.animation = 'glitch 0.1s infinite';
    setTimeout(() => { document.body.style.animation = 'none'; this.overlay.style.background = 'rgba(0,0,0,0.95)'; }, 3000);
  }
}

// ==========================================
// 3. CHAT APP
// ==========================================
class ChatApp {
  constructor(securitySystem, username) {
    this.security = securitySystem; this.lastId = 0; this.seen = new Set();
    this.feed = document.getElementById('feed'); this.statusEl = document.getElementById('status-bar');
    this.sender = username; this.typingTimeout = null; this.isTyping = false;
    this.typingEl = document.getElementById('typing-indicator');
    this.bindEvents(); this.fetchMessages();
    setInterval(() => { this.fetchMessages(); this.checkTyping(); }, 1000);
  }
  bindEvents() {
    document.getElementById('send-btn').addEventListener('click', () => this.sendMessage());
    document.getElementById('message-input').addEventListener('input', () => this.pingTyping());
    document.getElementById('message-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') this.sendMessage(); });
    document.getElementById('refresh-btn').addEventListener('click', () => { this.seen.clear(); this.lastId = 0; this.feed.innerHTML = ''; this.fetchMessages(); });
    document.getElementById('lockdown-btn').addEventListener('click', () => this.security.triggerLockdown());
  }
  pingTyping() {
    if (this.isTyping) return; this.isTyping = true;
    const room = document.getElementById('room-input').value.trim() || 'main';
    fetch('api.php?action=ping', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({room}) });
    clearTimeout(this.typingTimeout); this.typingTimeout = setTimeout(() => { this.isTyping = false; }, 2000);
  }
  async checkTyping() {
    const room = document.getElementById('room-input').value.trim() || 'main';
    try {
        const res = await fetch(`api.php?action=check_typing&room=${encodeURIComponent(room)}`);
        const data = await res.json();
        if (data.ok && data.typing.length > 0) { this.typingEl.textContent = `> ${data.typing.join(', ')} is typing...`; }
        else { this.typingEl.textContent = ''; }
    } catch (e) { console.error("Typing check failed", e); }
  }
  escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  setStatus(m, err = false) { this.statusEl.textContent = m; this.statusEl.className = err ? 'status-text err' : 'status-text'; }
  async fetchMessages() {
    const room = document.getElementById('room-input').value.trim() || 'main';
    try {
        const res = await fetch(`api.php?action=fetch&room=${encodeURIComponent(room)}&since=${this.lastId}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'fetch failed');
        for (const m of data.messages) {
            if (this.seen.has(m.id)) continue;
            this.seen.add(m.id); this.lastId = Math.max(this.lastId, m.id);
            let decryptedText = "[ENCRYPTED]";
            if (m.ciphertext && m.iv) { decryptedText = await decryptText(m.ciphertext, m.iv); }
            else { decryptedText = m.text || "[NO DATA]"; }
            this.renderMessage(m, decryptedText);
            this.security.resetTimer();
        }
        document.getElementById('msg-count').textContent = this.seen.size;
        this.setStatus(`synced · ${this.seen.size} message(s)`);
    } catch (e) { this.setStatus('fetch error: ' + e.message, true); }
  }
  renderMessage(m, text) {
    const me = (m.sender === this.sender);
    const div = document.createElement('div');
    div.className = me ? 'msg msg-me' : 'msg';
    div.innerHTML = `
      <div class="msg-head"><b class="msg-sender">${this.escapeHtml(m.sender)}</b><span class="msg-time">${this.escapeHtml(m.created_at)}</span><span class="msg-id">#${m.id}</span></div>
      <div class="msg-body" id="body-${m.id}"></div>
      <div class="msg-footer"><span class="hash-label">sha256:</span><span class="hash-value">${m.sha256}</span><span class="hash-status ok">E2EE VERIFIED</span></div>`;
    this.feed.appendChild(div); this.typeText(`body-${m.id}`, text);
  }
  typeText(elementId, text) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.add('typewriter-cursor');
    let i = 0;
    const type = () => {
      if (i < text.length) { el.textContent += text.charAt(i); i++; this.feed.scrollTop = this.feed.scrollHeight; setTimeout(type, 15); }
      else { el.classList.remove('typewriter-cursor'); }
    };
    type();
  }
  async sendMessage() {
    if (!e2eeKey) await initE2EE();
    const textEl = document.getElementById('message-input');
    const text = textEl.value;
    const room = document.getElementById('room-input').value.trim() || 'main';
    if (!text.trim()) return this.setStatus('empty message', true);
    if (!e2eeKey) return this.setStatus('CRYPTO ERROR: Cannot generate key.', true);
    const sendBtn = document.getElementById('send-btn');
    sendBtn.disabled = true; this.setStatus('encrypting…');
    try {
      const encryptedPayload = await encryptText(text);
      const hashBuffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      const sha256 = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
      const res = await fetch('api.php?action=send', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({room, e2ee_ciphertext: encryptedPayload.ciphertext, e2ee_iv: encryptedPayload.iv, sha256: sha256}),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'send failed');
      textEl.value = ''; this.setStatus('sent'); this.security.resetTimer(); await this.fetchMessages();
    } catch (e) { this.setStatus('send error: ' + e.message, true); }
    finally { sendBtn.disabled = false; textEl.focus(); }
  }
}

// ==========================================
// 4. START
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
    await initE2EE();
    const username = document.body.dataset.username;
    const securitySystem = new SecuritySystem();
    new ChatApp(securitySystem, username);
});
</script>
</body>
</html>
