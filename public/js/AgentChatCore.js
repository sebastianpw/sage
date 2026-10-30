/**
 * AgentChatCore.js
 * Universal reusable engine for AI-to-Database Agent modules.
 * Handles chat rendering, file attachments, async background polling,
 * session management, operations log parsing, and UI state syncing.
 */
class AgentChatCore {
    constructor(config = {}) {
        this.modulePrefix = config.modulePrefix || 'agent';
        this.apiEndpoint = config.apiEndpoint || `${this.modulePrefix}_api.php`;
        this.onStateChange = config.onStateChange || function () {};
        this.onSessionsChange = config.onSessionsChange || function () {};

        this.lsSessionKey = `${this.modulePrefix}_active_session`;
        this.lsModelKey = `${this.modulePrefix}_last_model`;
        this.sessionId = null;
        this.sessions = [];
        this.messages = [];
        this.attachedFiles = [];
        this.isProcessing = false;
        this.pollTimer = null;
        this.modelCatalog = {};
        this.selectedModel = 'openai';

        // DOM Element IDs (can be overridden)
        this.els = {
            chatWindow: config.chatWindowId || 'chatWindow',
            chatInput: config.chatInputId || 'chatInput',
            btnSend: config.btnSendId || 'btnSend',
            sessionsList: config.sessionsListId || 'sessionsList',
            modelSelect: config.modelSelectId || 'setModel',
            loreInput: config.loreInputId || 'setLore',
            fileInput: config.fileInputId || 'fileInput',
            attachedFiles: config.attachedFilesId || 'attachedFilesContainer',
            toastContainer: config.toastContainerId || 'toastContainer',
            backdrop: config.backdropId || 'flyoutBackdrop',
            leftFlyout: config.leftFlyoutId || 'flyoutLeft',
            rightFlyout: config.rightFlyoutId || 'flyoutRight',
            sessionNameBanner: config.sessionNameBannerId || 'sessionNameBanner'
        };
    }

    async init() {
        this.sessionId = this.getPersistedSessionId();
        this.bindEvents();
        await this.loadState(this.sessionId);
    }

    // ── Persistence & Helpers ─────────────────────────────────────────────
    getPersistedSessionId() {
        try { return localStorage.getItem(this.lsSessionKey); } catch (e) { return null; }
    }

    setSessionId(id) {
        this.sessionId = id || null;
        try {
            if (id) localStorage.setItem(this.lsSessionKey, id);
            else localStorage.removeItem(this.lsSessionKey);
        } catch (e) {}
        this.updateSessionBanner();
    }

    async api(payload, method = 'POST') {
        const bodyData = Object.assign({}, payload);
        if (!('session_id' in bodyData) && this.sessionId) {
            bodyData.session_id = this.sessionId;
        }

        let url = this.apiEndpoint;
        const opts = { method, headers: { 'Content-Type': 'application/json' } };

        if (method === 'GET') {
            url += '?' + new URLSearchParams(bodyData).toString();
        } else {
            opts.body = JSON.stringify(bodyData);
        }

        const res = await fetch(url, opts);
        const data = await res.json();
        if (!data.success && data.error) throw new Error(data.error);
        return data;
    }

    // ── Event Bindings ───────────────────────────────────────────────────
    bindEvents() {
        const input = document.getElementById(this.els.chatInput);
        if (input) {
            input.addEventListener('input', () => this.autoResizeTextarea(input));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        const fileInp = document.getElementById(this.els.fileInput);
        if (fileInp) {
            fileInp.addEventListener('change', (e) => {
                for (let f of e.target.files) this.attachFile(f);
                e.target.value = '';
            });
        }

        const btnSend = document.getElementById(this.els.btnSend);
        if (btnSend) {
            btnSend.addEventListener('click', () => this.sendMessage());
        }

        const backdrop = document.getElementById(this.els.backdrop);
        if (backdrop) {
            backdrop.addEventListener('click', () => this.closeFlyouts());
        }

        const modelSelect = document.getElementById(this.els.modelSelect);
        if (modelSelect) {
            modelSelect.addEventListener('change', (e) => {
                this.selectedModel = e.target.value;
                try { localStorage.setItem(this.lsModelKey, e.target.value); } catch(err) {}
            });
        }
    }

    autoResizeTextarea(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 180) + 'px';
    }

    // ── Session Management ───────────────────────────────────────────────
    async loadState(targetSessionId = null) {
        try {
            const data = await this.api({ action: 'load_state', session_id: targetSessionId }, 'GET');
            
            this.setSessionId(data.session_id);
            this.sessions = data.sessions || [];
            this.messages = data.chat || [];

            // Load model: Prefer DB settings for existing sessions. 
            // Fallback to localStorage for new sessions, then default.
            let localModel = null;
            try { localModel = localStorage.getItem(this.lsModelKey); } catch(e) {}
            
            if (!data.session_id && localModel) {
                this.selectedModel = localModel;
            } else {
                this.selectedModel = data.settings?.model || localModel || 'openai';
            }

            if (data.model_catalog) {
                this.modelCatalog = data.model_catalog;
                this.renderModelCatalog(data.model_catalog, this.selectedModel);
            }

            const loreEl = document.getElementById(this.els.loreInput);
            if (loreEl && data.settings) {
                loreEl.value = data.settings.lore || '';
            }

            this.renderSessionsList();
            this.renderChat();

            if (data.data_state) {
                this.onStateChange(data.data_state);
            }

            if (this.messages.length > 0) {
                const last = this.messages[this.messages.length - 1];
                if (last.role === 'user') {
                    this.setProcessing(true);
                    this.startPolling();
                }
            }
        } catch (e) {
            this.toast(e.message, 'error');
        }
    }

    renderModelCatalog(catalog, selected) {
        const sel = document.getElementById(this.els.modelSelect);
        if (!sel || !catalog) return;
        sel.innerHTML = '';

        for (const [groupName, models] of Object.entries(catalog)) {
            if (!Array.isArray(models) || !models.length) continue;
            const grp = document.createElement('optgroup');
            grp.label = groupName;
            models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name || m.id;
                if (m.id === selected) opt.selected = true;
                grp.appendChild(opt);
            });
            sel.appendChild(grp);
        }
    }

    renderSessionsList() {
        const list = document.getElementById(this.els.sessionsList);
        if (!list) return;

        if (!this.sessions.length) {
            list.innerHTML = '<div style="color:var(--text-dim);font-size:0.75rem;padding:8px;">No sessions yet.</div>';
            return;
        }

        list.innerHTML = this.sessions.map(s => `
            <div class="list-item ${s.id === this.sessionId ? 'active' : ''}" onclick="window.agentChat.switchSession('${s.id}')">
                <div class="item-meta">${new Date(s.updated_at).toLocaleDateString()}</div>
                <div class="item-title">${this.escHtml(s.title || 'Untitled Session')}</div>
                <button class="btn-rename" onclick="event.stopPropagation(); window.agentChat.renameSession('${s.id}')" title="Rename"><i class="bi bi-pencil"></i></button>
                <button class="btn-del" onclick="event.stopPropagation(); window.agentChat.deleteSession('${s.id}')" title="Delete"><i class="bi bi-trash"></i></button>
            </div>
        `).join('');

        this.onSessionsChange(this.sessions);
    }

    updateSessionBanner() {
        const el = document.getElementById(this.els.sessionNameBanner);
        if (!el) return;
        if (!this.sessionId) {
            el.textContent = 'New Project';
            return;
        }
        const s = this.sessions.find(x => x.id === this.sessionId);
        el.textContent = s ? (s.title || 'Untitled') : 'Active Project';
    }

    async switchSession(id) {
        if (id === this.sessionId) return;
        if (this.pollTimer) clearInterval(this.pollTimer);
        this.setProcessing(false);
        this.closeFlyouts();
        await this.loadState(id);
    }

    newSession() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        this.setProcessing(false);
        this.setSessionId(null);
        this.messages = [];
        this.attachedFiles = [];
        this.renderAttachedFiles();
        this.renderChat();
        this.closeFlyouts();
        const input = document.getElementById(this.els.chatInput);
        if (input) input.focus();
        this.onStateChange([]);
    }

    async renameSession(id) {
        const s = this.sessions.find(x => x.id === id);
        const title = prompt('Rename session:', s ? s.title : '');
        if (!title || (s && title === s.title)) return;

        try {
            const data = await this.api({ action: 'rename_session', session_id: id, title });
            this.sessions = data.sessions || this.sessions;
            this.renderSessionsList();
            this.updateSessionBanner();
            this.toast('Session renamed', 'success');
        } catch (e) {
            this.toast(e.message, 'error');
        }
    }

    async deleteSession(id) {
        if (!confirm('Permanently delete this session and all linked records?')) return;
        try {
            const data = await this.api({ action: 'delete_session', session_id: id });
            this.sessions = data.sessions || [];
            if (this.sessionId === id) {
                this.newSession();
            } else {
                this.renderSessionsList();
            }
            this.toast('Session deleted', 'info');
        } catch (e) {
            this.toast(e.message, 'error');
        }
    }

    async saveSettings() {
        const lore = document.getElementById(this.els.loreInput)?.value.trim() || '';
        const model = document.getElementById(this.els.modelSelect)?.value || 'openai';

        try {
            const data = await this.api({ action: 'save_settings', lore, model });
            this.selectedModel = model;
            try { localStorage.setItem(this.lsModelKey, model); } catch(err) {}
            if (data.session_id) this.setSessionId(data.session_id);
            this.toast('Settings saved', 'success');
        } catch (e) {
            this.toast(e.message, 'error');
        }
    }

    // ── Chatty Derived Actions (Download, Branch, Copy, Delete) ──────────
    async downloadChat() {
        if (!this.messages.length) {
            this.toast('Chat is empty', 'error');
            return;
        }
        try {
            const data = await this.api({ action: 'export_chat', session_id: this.sessionId }, 'GET');
            const blob = new Blob([data.export_text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${this.modulePrefix}_export_${this.sessionId}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            this.toast('Chat downloaded', 'success');
        } catch (e) {
            this.toast('Error: ' + e.message, 'error');
        }
    }

    async branchChat(msgId) {
        if (!confirm('Branch a new chat session from this message? (Your linked data will be copied)')) return;
        try {
            const res = await this.api({ action: 'branch_chat', msg_id: msgId, old_session_id: this.sessionId });
            this.toast('Chat branched!', 'success');
            await this.switchSession(res.new_session_id);
        } catch(e) {
            this.toast('Error: ' + e.message, 'error');
        }
    }

    async deleteMsg(msgId) {
        if (!confirm('Permanently delete this message from the history?')) return;
        try {
            await this.api({ action: 'delete_msg', msg_id: msgId });
            this.messages = this.messages.filter(m => String(m.id) !== String(msgId));
            this.renderChat();
            this.toast('Message deleted', 'info');
        } catch(e) {
            this.toast('Error: ' + e.message, 'error');
        }
    }

    copyMsg(btn) {
        const text = btn.getAttribute('data-content');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => this.toast('Copied', 'success')).catch(() => {});
        }
    }

    // ── File Attachments ─────────────────────────────────────────────────
    attachFile(file) {
        if (file.size > 5 * 1024 * 1024) {
            this.toast(`File ${file.name} is too large. Max 5MB.`, 'error');
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            this.attachedFiles.push({ name: file.name, content: e.target.result });
            this.renderAttachedFiles();
        };
        reader.onerror = () => this.toast(`Failed to read ${file.name}`, 'error');
        reader.readAsText(file);
    }

    renderAttachedFiles() {
        const container = document.getElementById(this.els.attachedFiles);
        if (!container) return;
        if (this.attachedFiles.length === 0) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }
        container.style.display = 'flex';
        container.innerHTML = this.attachedFiles.map((f, i) => `
            <div class="file-chip">
                <i class="bi bi-file-text"></i> ${this.escHtml(f.name)}
                <button type="button" onclick="window.agentChat.removeFile(${i})"><i class="bi bi-x"></i></button>
            </div>
        `).join('');
    }

    removeFile(index) {
        this.attachedFiles.splice(index, 1);
        this.renderAttachedFiles();
    }

    // ── Sending & Polling (Async Pipeline) ──────────────────────────────
    async sendMessage(overrideText = null) {
        if (this.isProcessing) return;

        const input = document.getElementById(this.els.chatInput);
        let text = overrideText !== null ? overrideText : (input ? input.value.trim() : '');

        if (!text && this.attachedFiles.length === 0) return;

        if (this.attachedFiles.length > 0) {
            const filesBlock = this.attachedFiles.map(f => `=== FILE: ${f.name} ===\n${f.content}\n====================`).join('\n\n');
            text = text ? `${text}\n\n${filesBlock}` : filesBlock;
        }

        if (input && overrideText === null) {
            input.value = '';
            this.autoResizeTextarea(input);
        }

        this.attachedFiles = [];
        this.renderAttachedFiles();

        const ts = Date.now();
        // Optimistic append
        const userMsg = { role: 'user', content: text, ts, id: 'temp' };
        this.messages.push(userMsg);
        this.appendMessageElement(userMsg);

        this.setProcessing(true);

        try {
            const data = await this.api({
                action: 'send_message',
                message: text,
                session_id: this.sessionId
            });

            if (data.session_id) this.setSessionId(data.session_id);
            if (data.sessions) {
                this.sessions = data.sessions;
                this.renderSessionsList();
            }

            this.startPolling();
        } catch (e) {
            this.setProcessing(false);
            this.toast(e.message, 'error');
        }
    }

    startPolling() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        const pollSessionId = this.sessionId;

        this.pollTimer = setInterval(async () => {
            if (this.sessionId !== pollSessionId) {
                clearInterval(this.pollTimer);
                return;
            }

            try {
                const data = await this.api({ action: 'load_chat', session_id: pollSessionId }, 'GET');
                const newMessages = data.chat || [];

                if (newMessages.length > this.messages.length || this.messages.some(m => m.id === 'temp')) {
                    const lastMsg = newMessages[newMessages.length - 1];
                    this.messages = newMessages;
                    this.renderChat();

                    if (lastMsg && (lastMsg.role === 'assistant' || lastMsg.role === 'ai')) {
                        clearInterval(this.pollTimer);
                        this.setProcessing(false);
                        this.toast('AI finished updates', 'success');

                        // Inform concrete UI about the newly synchronized database state!
                        if (data.data_state) {
                            this.onStateChange(data.data_state);
                        }
                    }
                }
            } catch (e) {
                console.warn('Poll error:', e);
            }
        }, 2500);
    }

    setProcessing(busy) {
        this.isProcessing = busy;
        const btn = document.getElementById(this.els.btnSend);
        if (btn) btn.disabled = busy;

        const typingId = 'agentChatTypingIndicator';
        const existingTyping = document.getElementById(typingId);

        if (busy && !existingTyping) {
            const win = document.getElementById(this.els.chatWindow);
            if (win) {
                const node = document.createElement('div');
                node.className = 'msg msg-ai msg-loading';
                node.id = typingId;
                node.innerHTML = `
                    <div class="msg-avatar"><i class="bi bi-robot"></i></div>
                    <div class="msg-bubble"><div class="typing"><span></span><span></span><span></span></div></div>
                `;
                win.appendChild(node);
                win.scrollTop = win.scrollHeight;
            }
        } else if (!busy && existingTyping) {
            existingTyping.remove();
        }
    }

    // ── Rendering ────────────────────────────────────────────────────────
    renderChat() {
        const win = document.getElementById(this.els.chatWindow);
        if (!win) return;
        win.innerHTML = '';

        if (!this.messages.length) {
            win.innerHTML = `
                <div class="chat-empty-state">
                    <div class="empty-icon"><i class="bi bi-chat-square-dots"></i></div>
                    <h3>${this.escHtml(this.modulePrefix.toUpperCase())} Agent</h3>
                    <p>Tell the AI what to add, query, update, or remove. All operations run directly in the database.</p>
                </div>
            `;
            return;
        }

        this.messages.forEach(m => this.appendMessageElement(m));
        if (this.isProcessing) this.setProcessing(true);
        win.scrollTop = win.scrollHeight;
    }

    appendMessageElement(msg) {
        const win = document.getElementById(this.els.chatWindow);
        if (!win) return;

        const role = msg.role === 'user' ? 'user' : 'ai';
        const msgNode = document.createElement('div');
        msgNode.className = `msg msg-${role}`;

        let opsHtml = '';
        if (msg.operations_log) {
            try {
                const ops = typeof msg.operations_log === 'string' ? JSON.parse(msg.operations_log) : msg.operations_log;
                if (Array.isArray(ops) && ops.length) {
                    opsHtml = `
                        <div class="ops-pill" onclick="this.classList.toggle('open')">
                            <i class="bi bi-database-check"></i> Executed ${ops.length} Operation${ops.length > 1 ? 's' : ''}
                            <div class="ops-details">
                                ${ops.map(o => `<div>• ${this.escHtml(o)}</div>`).join('')}
                            </div>
                        </div>
                    `;
                }
            } catch (e) {}
        }

        let formattedContent = this.formatContent(msg.content);
        
        let actionsHtml = '';
        if (msg.id && msg.id !== 'temp') {
            actionsHtml = `
                <span class="msg-actions">
                    <button onclick="window.agentChat.copyMsg(this)" data-content="${this.escAttr(msg.content)}" class="msg-action-btn" title="Copy text"><i class="bi bi-clipboard"></i></button>
                    <button onclick="window.agentChat.branchChat('${msg.id}')" class="msg-action-btn" title="Branch from here"><i class="bi bi-diagram-2"></i></button>
                    <button onclick="window.agentChat.deleteMsg('${msg.id}')" class="msg-action-btn delete-btn" title="Delete message"><i class="bi bi-trash"></i></button>
                </span>
            `;
        }

        msgNode.innerHTML = `
            <div class="msg-avatar">${role === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-robot"></i>'}</div>
            <div class="msg-bubble">
                <div class="msg-bubble-meta">
                    <span class="msg-timestamp">${new Date(msg.ts).toLocaleTimeString()}</span>
                    ${actionsHtml}
                </div>
                ${formattedContent}
                ${opsHtml}
            </div>
        `;

        win.appendChild(msgNode);
        win.scrollTop = win.scrollHeight;
    }

    formatContent(raw) {
        if (!raw) return '';
        let s = this.escHtml(raw);
        // Replace file tags
        s = s.replace(/=== FILE: (.*?) ===[\s\S]*?====================/g, '<div class="file-chip" style="margin: 4px 0;"><i class="bi bi-file-text"></i> $1 (attached)</div>');
        // Bold markdown
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Code blocks
        s = s.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        // Inline code
        s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        // Newlines
        return s.replace(/\n/g, '<br>');
    }

    // ── Flyouts & Modals ─────────────────────────────────────────────────
    toggleFlyout(side) {
        const left = document.getElementById(this.els.leftFlyout);
        const right = document.getElementById(this.els.rightFlyout);
        const backdrop = document.getElementById(this.els.backdrop);

        if (side === 'left') {
            right?.classList.remove('open');
            left?.classList.toggle('open');
        } else {
            left?.classList.remove('open');
            right?.classList.toggle('open');
        }

        const anyOpen = left?.classList.contains('open') || right?.classList.contains('open');
        if (backdrop) backdrop.classList.toggle('visible', anyOpen);
    }

    closeFlyouts() {
        document.getElementById(this.els.leftFlyout)?.classList.remove('open');
        document.getElementById(this.els.rightFlyout)?.classList.remove('open');
        document.getElementById(this.els.backdrop)?.classList.remove('visible');
    }

    // ── Utilities ────────────────────────────────────────────────────────
    toast(msg, type = 'info', duration = 3000) {
        const c = document.getElementById(this.els.toastContainer);
        if (!c) return;
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span> ${this.escHtml(msg)}`;
        c.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            setTimeout(() => t.remove(), 250);
        }, duration);
    }

    escHtml(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    
    escAttr(s) {
        return this.escHtml(s).replace(/"/g, '&quot;');
    }
}

window.AgentChatCore = AgentChatCore;