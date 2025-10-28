<?php

namespace App\Core;

use App\Core\SpwBase;

class ChatUI
{
    protected int $userId;
    protected SpwBase $spw;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->spw = SpwBase::getInstance();
    }

    public function render(): string
    {
        return $this->getStyles() . $this->getHTML() . $this->getScripts();
    }

    private function getStyles(): string
    {
        return <<<'CSS'
<style>
/* Embedded Chat UI Styles */
:root {
  --accent: #1e88e5;
  --accent-700: #1976d2;
  --bg: #fbfdff;
  --muted: #8a8f98;
  --user-bg: #4caf50;
  --assistant-bg: #eef6ff;
  --radius: 12px;
  --max-width: 820px;
}

* { box-sizing: border-box; }

.chat-shell {
  width: 100%;
  max-width: var(--max-width);
  margin: 0 auto;
  height: calc(100vh - 100px);
  background: var(--bg);
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(20, 30, 50, 0.08);
  display: flex;
  overflow: hidden;
}

/* Sidebar */
.sidebar {
  width: 280px;
  min-width: 220px;
  background: #ffffff;
  border-right: 1px solid rgba(15, 20, 30, 0.06);
  display: flex;
  flex-direction: column;
  padding: 12px;
  gap: 10px;
}

.sidebar .top {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: stretch;
}

.model-selector-wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.model-selector-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

#model-selector {
  width: 100%;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid rgba(15, 20, 30, 0.1);
  background: linear-gradient(180deg, #fff, #fcfeff);
  font-size: 13px;
  color: #0f1720;
  cursor: pointer;
  outline: none;
}

#model-selector:hover {
  border-color: var(--accent);
  background: #f8fcff;
}

#model-selector:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(30, 136, 229, 0.1);
}

.new-chat-btn {
  display: block;
  width: 100%;
  background: var(--accent);
  color: white;
  border: none;
  padding: 10px 12px;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
}

.new-chat-btn:hover {
  background: var(--accent-700);
}

#sessions {
  padding: 10px 0;
  flex: 1;
  overflow-y: auto;
}

#chat-list {
  max-height: 100%;
  overflow: auto;
}

.sessions {
  overflow: auto;
  flex: 1;
  padding-top: 6px;
}

.session-item {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: 10px;
  background: linear-gradient(180deg, #fff, #fcfeff);
  border: 1px solid rgba(15, 20, 30, 0.03);
  margin-bottom: 8px;
  cursor: pointer;
  word-break: break-word;
}

.session-item:hover {
  background: #f6fbff;
}

.session-title {
  flex: 1;
  font-size: 13px;
  color: #0f1720;
}

.session-controls {
  display: flex;
  gap: 6px;
}

.session-controls button {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
}

.session-controls button:hover {
  background: rgba(10, 20, 40, 0.04);
}

/* Main area */
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* Header */
.header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(15, 20, 30, 0.04);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0.3));
}

.header h2 {
  margin: 0;
  font-size: 15px;
}

.header .controls {
  margin-left: auto;
  display: flex;
  gap: 8px;
  align-items: center;
}

.icon-btn {
  border: 0;
  background: transparent;
  padding: 8px;
  border-radius: 8px;
  cursor: pointer;
}

.icon-btn:hover {
  background: rgba(10, 20, 40, 0.04);
}

/* Messages area */
#messages {
  flex: 1;
  overflow-y: auto;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: linear-gradient(180deg, #fbfdff, #f8fbff);
}

.message {
  max-width: 78%;
  padding: 12px 14px;
  border-radius: 12px;
  word-wrap: break-word;
  position: relative;
  box-shadow: 0 3px 8px rgba(12, 18, 28, 0.03);
  font-size: 14px;
  line-height: 1.45;
  white-space: pre-wrap;
}

/* Fix message delete button visibility (desktop + mobile) */
.message {
  position: relative;
  overflow: visible !important;
}

/* Default (desktop) */
.msg-delete {
  position: absolute;
  top: 6px;
  right: 6px;
  z-index: 10;
  font-size: 14px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s ease-in-out;
}

.message:hover .msg-delete {
  opacity: 1;
}

/* Always visible on touch/mobile devices */
@media (hover: none) and (pointer: coarse) {
  .msg-delete {
    opacity: 1 !important;
  }
}

.message .meta {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 6px;
}

/* user */
.message.user {
  margin-left: auto;
  background: var(--user-bg);
  color: white;
  border-bottom-right-radius: 6px;
}

/* assistant */
.message.assistant {
  margin-right: auto;
  background: var(--assistant-bg);
  color: #06203a;
  border-bottom-left-radius: 6px;
}

/* Loading indicator message */
.loading-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 12px;
  background: linear-gradient(90deg, #e7f1ff, #eef8ff);
  border: 1px solid rgba(30, 110, 220, 0.07);
  color: var(--accent-700);
}

.loader-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #dcedff;
  position: relative;
  box-shadow: inset 0 0 0 3px rgba(30, 110, 220, 0.06);
  animation: loader-scale 1s infinite;
}

@keyframes loader-scale {
  0% {
    transform: scale(1);
    opacity: 0.6;
  }
  50% {
    transform: scale(1.4);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 0.6;
  }
}

.loading-controls {
  margin-left: auto;
  display: flex;
  gap: 8px;
  align-items: center;
}

/* Input area - IMPROVED WITH TEXTAREA */
.input-area {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-top: 1px solid rgba(15, 20, 30, 0.04);
  background: linear-gradient(180deg, #fff, #fcfeff);
  align-items: flex-end;
}

#user-input {
  flex: 1;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid rgba(15, 20, 30, 0.06);
  outline: none;
  font-size: 15px;
  font-family: inherit;
  resize: none;
  min-height: 42px;
  max-height: 200px;
  overflow-y: auto;
  line-height: 1.4;
}

#user-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 2px rgba(30, 136, 229, 0.1);
}

#send-btn {
  background: var(--accent);
  color: white;
  border: 0;
  padding: 10px 14px;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  min-width: 70px;
  align-self: flex-end;
}

#send-btn:hover {
  background: var(--accent-700);
}

#send-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Scroll buttons */
.scroll-btn {
  display: inline-block;
  padding: 8px 16px;
  background: rgba(30, 136, 229, 0.1);
  border-radius: 8px;
  text-decoration: none;
  color: var(--accent);
  font-size: 12px;
  margin: 8px 0;
}

.scroll-btn:hover {
  background: rgba(30, 136, 229, 0.2);
}

/* responsive */
@media (max-width: 920px) {
  .sidebar {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 100;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .mobile-toggle {
    display: block !important;
  }
  
  .chat-shell {
    height: calc(100vh - 40px);
  }
}

.mobile-toggle {
  display: none;
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 99;
  background: var(--accent);
  color: white;
  border: none;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 18px;
}
</style>
CSS;
    }



private function getHTML(): string
{
    // Build model options from AIProvider catalog
    $catalog = \App\Core\AIProvider::getModelCatalog();
    $defaultModel = 'groq/compound';
    $modelOptionsHtml = '';
    foreach ($catalog as $groupLabel => $models) {
        $modelOptionsHtml .= '<optgroup label="' . htmlspecialchars($groupLabel, ENT_QUOTES) . "\">\n";
        foreach ($models as $m) {
            $id = htmlspecialchars($m['id'], ENT_QUOTES);
            $name = htmlspecialchars($m['name'], ENT_QUOTES);
            $sel = ($id === $defaultModel) ? ' selected' : '';
            $modelOptionsHtml .= "  <option value=\"{$id}\"{$sel}>{$name}</option>\n";
        }
        $modelOptionsHtml .= "</optgroup>\n";
    }

    // Return full HTML. Heredoc used so $modelOptionsHtml is interpolated.
    return <<<HTML
<div class="chat-shell">
  <!-- Mobile menu toggle -->
  <button id="mobile-toggle" class="mobile-toggle">☰</button>

  <!-- Sidebar with session list -->
  <div id="sidebar" class="sidebar">
    <div class="top">
      <!-- Model Selector -->
      <div class="model-selector-wrapper">
        <label class="model-selector-label" for="model-selector">AI Model</label>
        <select id="model-selector">
{$modelOptionsHtml}
        </select>
      </div>

      <!-- New Chat Button -->
      <button id="new-chat-btn" class="new-chat-btn">+ New Chat</button>
    </div>
    <div id="sessions" class="sessions">
      <!-- Session items will be loaded here by JavaScript -->
    </div>
  </div>

  <!-- Main chat area -->
  <div class="main">
    <div class="header">
      <h2 id="chat-header">Select a chat</h2>
      <div class="controls">
        <span id="current-model-display" style="font-size: 12px; color: var(--muted); font-weight: normal;"></span>
      </div>
    </div>

    <div id="messages">
      <!-- Messages will be inserted here -->
    </div>

    <div class="input-area">
      <textarea 
        id="user-input" 
        placeholder="Type a message... (Shift+Enter for new line, Enter to send)"
        rows="1"
      ></textarea>
      <button id="send-btn">Send</button>
    </div>
  </div>
</div>
HTML;
}

/*

    private function getHTML(): string
    {
        return <<<'HTML'
<div class="chat-shell">
  <!-- Mobile menu toggle -->
  <button id="mobile-toggle" class="mobile-toggle">☰</button>

  <!-- Sidebar with session list -->
  <div id="sidebar" class="sidebar">
    <div class="top">
      <!-- Model Selector -->
      <div class="model-selector-wrapper">
        <label class="model-selector-label" for="model-selector">AI Model</label>
        <select id="model-selector">
          <optgroup label="Groq API">
  <option value="allam-2-7b">Allam 2 7B</option>
  <option value="groq/compound" selected>Groq Compound</option>
  <option value="groq/compound-mini">Groq Compound Mini</option>
  <option value="llama-3.1-8b-instant">Llama 3.1 8B Instant</option>
  <option value="llama-3.3-70b-versatile">Llama 3.3 70B Versatile</option>
  <option value="meta-llama/llama-4-maverick-17b-128e-instruct">Llama 4 Maverick 17B 128E Instruct</option>
  <option value="meta-llama/llama-4-scout-17b-16e-instruct">Llama 4 Scout 17B 16E Instruct</option>
  <option value="meta-llama/llama-guard-4-12b">Llama Guard 4 12B</option>
  <option value="moonshotai/kimi-k2-instruct">Moonshot Kimi K2 Instruct</option>
  <option value="moonshotai/kimi-k2-instruct-0905">Moonshot Kimi K2 Instruct 0905</option>
  <option value="openai/gpt-oss-20b">OpenAI GPT OSS 20B</option>
  <option value="openai/gpt-oss-120b">OpenAI GPT OSS 120B</option>
  <option value="qwen/qwen3-32b">Qwen 3 32B (Groq)</option>
</optgroup>
          <optgroup label="Pollinations API - Main">
            <option value="deepseek">DeepSeek V3.1</option>
            <option value="deepseek-reasoning">DeepSeek R1 (Reasoning)</option>
            <option value="mistral">Mistral Small 3.1 24B</option>
            <option value="nova-fast">Amazon Nova Micro</option>
            <option value="openai">OpenAI GPT-5 Mini</option>
            <option value="openai-fast">OpenAI GPT-5 Nano</option>
            <option value="openai-large">OpenAI GPT-5 Chat</option>
            <option value="openai-reasoning">OpenAI o4-mini (Reasoning)</option>
            <option value="openai-audio">OpenAI GPT-4o Audio</option>
            <option value="qwen-coder">Qwen 2.5 Coder 32B</option>
            <option value="roblox-rp">Llama 3.1 8B Instruct</option>
          </optgroup>
          <optgroup label="Pollinations API - Community">
            <option value="bidara">BIDARA (NASA)</option>
            <option value="chickytutor">ChickyTutor Language</option>
            <option value="midijourney">MIDIjourney</option>
            <option value="rtist">Rtist</option>
	  </optgroup>



  <optgroup label="Gemini Text / Coding Models">
    <option value="gemini-2.5-pro">Gemini 2.5 Pro (Stable)</option>
    <option value="gemini-2.5-pro-preview-03-25">Gemini 2.5 Pro Preview 03-25</option>
    <option value="gemini-2.5-pro-preview-05-06">Gemini 2.5 Pro Preview 05-06</option>
    <option value="gemini-2.5-pro-preview-06-05">Gemini 2.5 Pro Preview 06-05</option>
    <option value="gemini-2.5-flash">Gemini 2.5 Flash (Stable)</option>
    <option value="gemini-2.5-flash-preview-05-20">Gemini 2.5 Flash Preview 05-20</option>
    <option value="gemini-2.5-flash-lite">Gemini 2.5 Flash-Lite (Stable)</option>
    <option value="gemini-2.5-flash-lite-preview-06-17">Gemini 2.5 Flash-Lite Preview 06-17</option>
    <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
    <option value="gemini-2.0-flash-001">Gemini 2.0 Flash 001</option>
    <option value="gemini-2.0-flash-lite">Gemini 2.0 Flash-Lite</option>
    <option value="gemini-2.0-flash-lite-001">Gemini 2.0 Flash-Lite 001</option>
    <option value="gemini-2.0-pro-exp">Gemini 2.0 Pro Experimental</option>
    <option value="gemini-2.0-flash-live-001">Gemini 2.0 Flash 001 Live</option>
    <option value="gemini-live-2.5-flash-preview">Gemini Live 2.5 Flash Preview</option>
    <option value="gemini-2.5-flash-live-preview">Gemini 2.5 Flash Live Preview</option>
    <option value="gemini-2.5-flash-native-audio-latest">Gemini 2.5 Flash Native Audio Latest</option>
    <option value="gemini-2.5-flash-native-audio-preview-09-2025">Gemini 2.5 Flash Native Audio Preview 09-2025</option>
  </optgroup>
  <optgroup label="Gemini Embedding Models">
    <option value="gemini-embedding-001">Gemini Embedding 001</option>
    <option value="gemini-embedding-exp">Gemini Embedding Experimental</option>
    <option value="gemini-embedding-exp-03-07">Gemini Embedding Experimental 03-07</option>
    <option value="embedding-gecko-001">Embedding Gecko</option>
  </optgroup>





        </select>
      </div>
      
      <!-- New Chat Button -->
      <button id="new-chat-btn" class="new-chat-btn">+ New Chat</button>
    </div>
    <div id="sessions" class="sessions">
      <!-- Session items will be loaded here by JavaScript -->
    </div>
  </div>

  <!-- Main chat area -->
  <div class="main">
    <div class="header">
      <h2 id="chat-header">Select a chat</h2>
      <div class="controls">
        <span id="current-model-display" style="font-size: 12px; color: var(--muted); font-weight: normal;"></span>
      </div>
    </div>

    <div id="messages">
      <!-- Messages will be inserted here -->
    </div>

    <div class="input-area">
      <textarea 
        id="user-input" 
        placeholder="Type a message... (Shift+Enter for new line, Enter to send)"
        rows="1"
      ></textarea>
      <button id="send-btn">Send</button>
    </div>
  </div>
</div>
HTML;
    }

 */





    private function getScripts(): string
    {
        return <<<'JAVASCRIPT'
<script>
/* Embedded Chat UI JavaScript - Auto-expanding textarea, double-click copy, abort control */
(() => {
  // Elements
  const sessionsDiv = document.getElementById('sessions');
  const messagesDiv = document.getElementById('messages');
  const newChatBtn = document.getElementById('new-chat-btn');
  const sendBtn = document.getElementById('send-btn');
  const userInput = document.getElementById('user-input');
  const chatHeader = document.getElementById('chat-header');
  const mobileToggle = document.getElementById('mobile-toggle');
  const sidebar = document.getElementById('sidebar');
  const modelSelector = document.getElementById('model-selector');
  const currentModelDisplay = document.getElementById('current-model-display');

  let currentSessionId = null;
  let currentController = null; // AbortController for running request
  let loadingElem = null;

  // Mobile rename double-tap tracking
  let lastSessionTouch = { id: null, time: 0 };

  // Get selected model
  function getSelectedModel() {
    return modelSelector ? modelSelector.value : 'openai';
  }

  // Update model display in header
  function updateModelDisplay() {
    if (currentModelDisplay && modelSelector) {
      const selectedOption = modelSelector.options[modelSelector.selectedIndex];
      currentModelDisplay.textContent = '🤖 ' + selectedOption.text;
    }
  }

  // Utilities
  const htmlEscape = s => {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  };

  function createEl(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else el.setAttribute(k, v);
    });
    children.forEach(c => el.appendChild(c));
    return el;
  }

  // Auto-expanding textarea
  function autoExpandTextarea() {
    if (!userInput) return;
    userInput.style.height = 'auto';
    userInput.style.height = Math.min(userInput.scrollHeight, 200) + 'px';
  }

  // Rename helper (used by dblclick and double-tap)
  async function renameSession(sessionId, titleDiv) {
    const currentTitle = (titleDiv && titleDiv.textContent) ? titleDiv.textContent.trim() : '';
    const newTitle = prompt('Enter new title for chat:', currentTitle);
    if (newTitle === null) return; // cancelled
    const cleaned = newTitle.trim();
    if (cleaned === '') {
      alert('Title cannot be empty');
      return;
    }

    try {
      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: new URLSearchParams({
          chat_session_id: sessionId,
          update_title: cleaned
        })
      });
      const j = await resp.json();
      if (j.success) {
        // update UI quickly
        if (titleDiv) titleDiv.textContent = cleaned;
        if (currentSessionId === sessionId && chatHeader) chatHeader.textContent = cleaned;
        // reload sessions to reflect server state
        loadSessions();
      } else {
        alert('Update failed');
      }
    } catch (err) {
      console.error('renameSession error', err);
      alert('Update failed');
    }
  }

  // Session list
  async function loadSessions() {
    try {
      const fd = new URLSearchParams();
      fd.set('list_chats', '1');

      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: fd
      });
      const data = await resp.json();
      if (!sessionsDiv) return;
      sessionsDiv.innerHTML = '';
      if (!data.sessions) return;

      data.sessions.forEach(s => {
        const sessionId = s.id;
        const item = createEl('div', { class: 'session-item' });

        const title = createEl('div', { class: 'session-title', text: (s.title || '(untitled)') });
        item.appendChild(title);

        const controls = createEl('div', { class: 'session-controls' });
        const copyBtn = createEl('button', { class: 'icon-btn', title: 'Fork chat' });
        copyBtn.innerHTML = '⎘';
        copyBtn.onclick = e => {
          e.stopPropagation();
          fetch('chat_ajax.php', {
            method: 'POST',
            body: new URLSearchParams({ 'copy_chat': sessionId })
          }).then(r => r.json()).then(d => {
            if (d.chat_session_id) { loadSessions(); loadChat(d.chat_session_id); }
          }).catch(err => console.error('copy chat error', err));
        };

        const delBtn = createEl('button', { class: 'icon-btn', title: 'Delete chat' });
        delBtn.innerHTML = '🗑️';
        delBtn.onclick = e => {
          e.stopPropagation();
          if (!confirm('Delete this chat?')) return;
          fetch('chat_ajax.php', {
            method: 'POST',
            body: new URLSearchParams({ 'delete_chat': sessionId })
          }).then(r => r.json()).then(d => {
            if (d.success) {
              if (currentSessionId === sessionId) {
                currentSessionId = null;
                if (messagesDiv) messagesDiv.innerHTML = '';
                if (chatHeader) chatHeader.textContent = 'Select a chat';
              }
              loadSessions();
            } else {
              alert('Delete failed: ' + (d.error || 'unknown'));
            }
          }).catch(err => console.error('delete chat error', err));
        };

        controls.appendChild(copyBtn);
        controls.appendChild(delBtn);
        item.appendChild(controls);

        // click handler (supports suppression after dblclick/double-tap)
        item.addEventListener('click', function (ev) {
          if (this._suppressClick) { this._suppressClick = false; return; }
          loadChat(sessionId, s.title || '(untitled)');
        });

        // desktop dblclick -> rename
        item.addEventListener('dblclick', function (ev) {
          ev.stopPropagation();
          this._suppressClick = true; // prevent immediate click open
          renameSession(sessionId, title);
        });

        // mobile double-tap: detect two touches on same item within timeframe
        item.addEventListener('touchend', function (ev) {
          const now = Date.now();
          const prev = lastSessionTouch;
          const delta = now - prev.time;
          if (prev.id === sessionId && delta > 40 && delta < 350) {
            // treat as double-tap -> rename
            ev.preventDefault();
            this._suppressClick = true;
            renameSession(sessionId, title);
            // reset lastSessionTouch to avoid triple-trigger
            lastSessionTouch = { id: null, time: 0 };
            return;
          }
          // otherwise store this touch for potential double-tap
          lastSessionTouch = { id: sessionId, time: now };
        });

        sessionsDiv.appendChild(item);
      });
    } catch (err) {
      console.error('loadSessions error', err);
    }
  }

  // Load chat messages
  async function loadChat(sessionId, title = null) {
    try {
      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: new URLSearchParams({ load_chat: sessionId })
      });
      const data = await resp.json();
      currentSessionId = sessionId;
      if (chatHeader) chatHeader.textContent = title || `Chat: ${sessionId}`;
      if (messagesDiv) messagesDiv.innerHTML = '';
      if (data.history && messagesDiv) {
        data.history.forEach(entry => {
          appendMessage(entry.content, entry.role === 'user' ? 'user' : 'assistant', entry.id);
        });
        scrollToBottom();
      }
      
      // Update model display when loading chat
      if (data.model && modelSelector && currentModelDisplay) {
        // Find and select the model in the dropdown
        const options = modelSelector.options;
        for (let i = 0; i < options.length; i++) {
          if (options[i].value === data.model) {
            modelSelector.selectedIndex = i;
            break;
          }
        }
        updateModelDisplay();
      }
      
      loadSessions();
      if (userInput) userInput.focus();
    } catch (err) {
      console.error('loadChat error', err);
    }
  }

  // Append message
  function appendMessage(text, role = 'assistant', id = null) {
    if (!messagesDiv) return;
    const el = document.createElement('div');
    el.className = 'message ' + (role === 'user' ? 'user' : 'assistant');

    const p = document.createElement('div');
    p.innerText = text;
    el.appendChild(p);

    if (id) {
      const del = document.createElement('span');
      del.className = 'msg-delete';
      del.title = 'Delete message';
      del.innerText = '🗑️';
      del.onclick = (e) => {
        e.stopPropagation();
        if (!confirm('Delete this message?')) return;
        fetch('chat_ajax.php', {
          method: 'POST',
          body: new URLSearchParams({
            delete_message: id,
            chat_session_id: currentSessionId
          })
        })
          .then(r => r.json())
          .then(d => {
            if (d.success) el.remove();
            else alert('Delete failed: ' + (d.error || 'unknown'));
          }).catch(err => console.error('delete message error', err));
      };
      el.appendChild(del);
    }

    messagesDiv.appendChild(el);
    scrollToBottom();
  }

  // Scrolling
  function scrollToBottom() {
    if (!messagesDiv) return;
    requestAnimationFrame(() => { messagesDiv.scrollTop = messagesDiv.scrollHeight; });
  }

  // Loading indicator with cancel button
  function showLoadingIndicator() {
    if (!messagesDiv) return;
    hideLoadingIndicator();
    loadingElem = createEl('div', { class: 'message assistant loading-indicator' });
    const dot = createEl('div', { class: 'loader-dot' });
    const txt = createEl('div', { text: 'Thinking...' });
    const controls = createEl('div', { class: 'loading-controls' });
    const stopBtn = createEl('button', { class: 'icon-btn' });
    stopBtn.innerText = '⏹ Stop';
    stopBtn.onclick = () => {
      if (currentController) currentController.abort();
    };
    controls.appendChild(stopBtn);
    loadingElem.appendChild(dot);
    loadingElem.appendChild(txt);
    loadingElem.appendChild(controls);
    messagesDiv.appendChild(loadingElem);
    scrollToBottom();
  }

  function hideLoadingIndicator() {
    if (loadingElem) {
      loadingElem.remove();
      loadingElem = null;
    }
  }

  // Send message (with loading indicator, abort control)
  async function sendMessage() {
    if (!userInput) return;
    const text = userInput.value.trim();
    if (!text) return;
    if (!currentSessionId) { alert('Please create or select a chat first.'); return; }

    appendMessage(text, 'user');

    userInput.value = '';
    autoExpandTextarea(); // reset height
    userInput.disabled = true;
    if (sendBtn) sendBtn.disabled = true;
    showLoadingIndicator();

    currentController = new AbortController();
    const signal = currentController.signal;

    const payload = new URLSearchParams();
    payload.set('chat_session_id', currentSessionId);
    payload.set('message', text);

    try {
      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: payload,
        signal
      });

      if (!resp.ok) {
        const txt = await resp.text().catch(() => `HTTP ${resp.status}`);
        throw new Error('HTTP error: ' + txt);
      }

      const json = await resp.json();

      hideLoadingIndicator();
      currentController = null;
      if (userInput) userInput.disabled = false;
      if (sendBtn) sendBtn.disabled = false;

      if (json.answer) {
        appendMessage(json.answer, 'assistant');
        scrollToBottom();
      } else if (json.error) {
        appendMessage('Error: ' + json.error, 'assistant');
      } else {
        appendMessage('No answer returned.', 'assistant');
      }
    } catch (err) {
      hideLoadingIndicator();
      currentController = null;
      if (userInput) userInput.disabled = false;
      if (sendBtn) sendBtn.disabled = false;

      if (err && err.name === 'AbortError') {
        appendMessage('*Cancelled.*', 'assistant');
      } else {
        console.error('sendMessage error', err);
        appendMessage('Request failed: ' + (err && err.message ? err.message : 'unknown error'), 'assistant');
      }
      scrollToBottom();
    } finally {
      if (userInput) {
        userInput.focus();
        autoExpandTextarea();
      }
    }
  }

  // New chat
  async function newChat() {
    try {
      const selectedModel = getSelectedModel();
      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: new URLSearchParams({ 
          new_chat: '1',
          model: selectedModel
        })
      });
      const j = await resp.json();
      if (j.chat_session_id) {
        currentSessionId = j.chat_session_id;
        if (messagesDiv) messagesDiv.innerHTML = '';
        if (chatHeader) chatHeader.textContent = 'New Chat';
        updateModelDisplay();
        loadSessions();
        
        // Close sidebar on mobile after creating new chat
        if (sidebar && window.innerWidth <= 920) {
          sidebar.classList.remove('mobile-open');
        }
        
        // Focus input after sidebar animation
        setTimeout(() => {
          if (userInput) userInput.focus();
        }, 300);
      }
    } catch (err) {
      console.error('newChat error', err);
    }
  }

  // Event wiring
  if (newChatBtn) newChatBtn.addEventListener('click', newChat);
  if (sendBtn) sendBtn.addEventListener('click', sendMessage);
  
  // Model selector change
  if (modelSelector) {
    modelSelector.addEventListener('change', updateModelDisplay);
  }
  
  // Mobile sidebar toggle
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
    });
    
    // Close sidebar when selecting a chat on mobile
    document.addEventListener('click', (e) => {
      if (e.target.closest('.session-item') && window.innerWidth <= 920) {
        setTimeout(() => {
          sidebar.classList.remove('mobile-open');
        }, 300);
      }
    });
  }
  
  // Textarea: auto-expand on input
  if (userInput) {
    userInput.addEventListener('input', autoExpandTextarea);
    
    // Enter to send, Shift+Enter for new line
    userInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  // --- Double-click & double-tap to copy ---
  (function installCopyHandlers() {
    if (!messagesDiv) return;
    let lastTouch = 0;

    function extractTextFromMessage(msg) {
      const clone = msg.cloneNode(true);
      const removeSelectors = ['.msg-delete', '.loading-controls', 'button', '.icon-btn', '.session-controls'];
      removeSelectors.forEach(sel => {
        if (clone.querySelectorAll) {
          clone.querySelectorAll(sel).forEach(n => n.remove());
        }
      });
      let text = clone.innerText || clone.textContent || '';
      text = text.replace(/\s+🗑️\s*$/g, '').trim();
      text = text.replace(/\r\n|\r/g, '\n').replace(/\n{2,}/g, '\n').trim();
      return text;
    }

    async function copyText(text) {
      if (!text) return false;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text);
          return true;
        } else {
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.left = '-9999px';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
          return true;
        }
      } catch (e) {
        console.warn('Copy failed', e);
        return false;
      }
    }

    function flashMessage(msg) {
      const origBg = msg.style.background;
      msg.style.transition = 'background 180ms ease';
      msg.style.background = '#e6ffea';
      setTimeout(() => { 
        msg.style.background = origBg || ''; 
        setTimeout(() => { msg.style.transition = ''; }, 200); 
      }, 420);
    }

    // desktop dblclick
    messagesDiv.addEventListener('dblclick', async (ev) => {
      const msg = ev.target.closest && ev.target.closest('.message');
      if (!msg) return;
      const txt = extractTextFromMessage(msg);
      const ok = await copyText(txt);
      if (ok) {
        flashMessage(msg);
        console.log('Copied to clipboard');
      } else {
        console.log('Copy failed');
      }
    });

    // mobile double-tap
    messagesDiv.addEventListener('touchend', async (ev) => {
      const now = Date.now();
      const target = ev.target.closest && ev.target.closest('.message');
      if (!target) { lastTouch = now; return; }

      const delta = now - lastTouch;
      lastTouch = now;
      if (delta > 40 && delta < 350) {
        ev.preventDefault();
        const txt = extractTextFromMessage(target);
        const ok = await copyText(txt);
        if (ok) {
          flashMessage(target);
          console.log('Copied to clipboard');
        } else {
          console.log('Copy failed');
        }
      }
    });
  })();
  // --- end copy handlers ---

  // Init
  loadSessions();
  updateModelDisplay();

  // Expose for debugging from console (optional)
  window.SPWChat = { loadSessions, loadChat, sendMessage, appendMessage };
})();
</script>
JAVASCRIPT;
    }
}
