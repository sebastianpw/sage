/* js/chat.ui.js
   Minimal, dependency-light (vanilla + small jQuery compatibility),
   compatible with existing chat_ajax.php endpoints.
   Adds double-click / double-tap rename for session items (uses Toast.show)
*/
(() => {
  // Elements
  const sessionsDiv = document.getElementById('sessions');
  const messagesDiv = document.getElementById('messages');
  const newChatBtn = document.getElementById('new-chat-btn');
  const sendBtn = document.getElementById('send-btn');
  const userInput = document.getElementById('user-input');
  const chatHeader = document.getElementById('chat-header');
  const toggleBtn = document.getElementById('toggle-chat-list');
  const chatList = document.getElementById('chat-list');

  let currentSessionId = null;
  let currentController = null; // AbortController for running request
  let loadingElem = null;

  // Mobile rename double-tap tracking
  let lastSessionTouch = { id: null, time: 0 };

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

  // Rename helper (used by dblclick and double-tap)
  async function renameSession(sessionId, titleDiv) {
    const currentTitle = (titleDiv && titleDiv.textContent) ? titleDiv.textContent.trim() : '';
    const newTitle = prompt('Enter new title for chat:', currentTitle);
    if (newTitle === null) return; // cancelled
    const cleaned = newTitle.trim();
    if (cleaned === '') {
      Toast && Toast.show ? Toast.show('Title cannot be empty', 'error') : alert('Title cannot be empty');
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
        Toast && Toast.show ? Toast.show('Title updated', 'success') : null;
        // reload sessions to reflect server state
        loadSessions();
      } else {
        Toast && Toast.show ? Toast.show('Update failed', 'error') : alert('Update failed');
      }
    } catch (err) {
      console.error('renameSession error', err);
      Toast && Toast.show ? Toast.show('Update failed', 'error') : alert('Update failed');
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
      if (userInput) userInput.focus();
    }
  }

  // New chat
  async function newChat() {
    try {
      const resp = await fetch('chat_ajax.php', {
        method: 'POST',
        body: new URLSearchParams({ new_chat: '1' })
      });
      const j = await resp.json();
      if (j.chat_session_id) {
        currentSessionId = j.chat_session_id;
        if (messagesDiv) messagesDiv.innerHTML = '';
        if (chatHeader) chatHeader.textContent = 'New Chat';
        loadSessions();
      }
    } catch (err) {
      console.error('newChat error', err);
    }
  }

  // Event wiring
  if (newChatBtn) newChatBtn.addEventListener('click', newChat);
  if (sendBtn) sendBtn.addEventListener('click', sendMessage);
  if (userInput) userInput.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
  if (toggleBtn) toggleBtn.addEventListener('click', () => {
    if (!chatList) return;
    if (chatList.style.display === 'block') { chatList.style.display = 'none'; }
    else { chatList.style.display = 'block'; }
  });

  // --- Double-click & double-tap to copy (uses existing Toast) ---
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
      setTimeout(() => { msg.style.background = origBg || ''; setTimeout(() => { msg.style.transition = ''; }, 200); }, 420);
    }

    // desktop dblclick
    messagesDiv.addEventListener('dblclick', async (ev) => {
      const msg = ev.target.closest && ev.target.closest('.message');
      if (!msg) return;
      const txt = extractTextFromMessage(msg);
      const ok = await copyText(txt);
      if (ok) {
        flashMessage(msg);
        if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copied to clipboard', 'success');
      } else {
        if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copy failed', 'error');
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
          if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copied to clipboard', 'success');
        } else {
          if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copy failed', 'error');
        }
      }
    });

    // keyboard shortcut Shift/Cmd + C to copy last message
    document.addEventListener('keydown', async (ev) => {
      const isCopyCombo = (ev.key && ev.key.toLowerCase() === 'c' && (ev.shiftKey || ev.metaKey));
      if (!isCopyCombo) return;
      const msg = document.querySelector('#messages .message:last-child');
      if (!msg) return;
      ev.preventDefault();
      const txt = extractTextFromMessage(msg);
      const ok = await copyText(txt);
      if (ok) {
        flashMessage(msg);
        if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copied to clipboard', 'success');
      } else {
        if (typeof Toast !== 'undefined' && Toast.show) Toast.show('Copy failed', 'error');
      }
    });
  })();
  // --- end copy handlers ---

  // Init
  loadSessions();

  // Expose for debugging from console (optional)
  window.SPWChat = { loadSessions, loadChat, sendMessage, appendMessage };
})();
