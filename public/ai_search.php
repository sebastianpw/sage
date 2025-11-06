<?php
/**
 * AI-Powered Search Component
 * 
 * Include this file in any view to add intelligent database search capability.
 * Usage: require 'ai_search.php';
 * 
 * The search uses AI to understand natural language queries and intelligently
 * determine which database tables to search and how to query them.
 */

// Ensure we have access to global variables
global $spw, $pdo, $pdoSys;

if (!isset($spw)) {
    require_once __DIR__ . '/bootstrap.php';
    require __DIR__ . '/env_locals.php';
}
?>

<style>
.ai-search-container {
    position: relative;
    max-width: 700px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.ai-search-wrapper {
    position: relative;
    display: flex;
    gap: 0;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: white;
    transition: border-color 0.2s ease;
}

.ai-search-wrapper:focus-within {
    border-color: #87CEEB;
    box-shadow: 0 0 0 2px rgba(135, 206, 235, 0.1);
}

.ai-search-category {
    padding: 12px 12px 12px 16px;
    font-size: 14px;
    border: none;
    border-right: 1px solid #e0e0e0;
    background: #f9f9f9;
    color: #333;
    cursor: pointer;
    outline: none;
    border-radius: 4px 0 0 4px;
    font-weight: 500;
    min-width: 120px;
}

.ai-search-category:hover {
    background: #f0f0f0;
}

.ai-search-input-container {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
}

.ai-search-input {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: none;
    background: transparent;
    color: #333;
    outline: none;
}

.ai-search-input::placeholder {
    color: #999;
}

.ai-search-button {
    padding: 0 16px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border-radius: 0 4px 4px 0;
}

.ai-search-button:hover:not(:disabled) {
    background: #f9f9f9;
}

.ai-search-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.ai-search-icon {
    width: 20px;
    height: 20px;
}

.ai-search-icon svg {
    width: 100%;
    height: 100%;
    stroke: #666;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
}

.ai-search-button:hover:not(:disabled) .ai-search-icon svg {
    stroke: #87CEEB;
}

.ai-search-loading {
    display: none;
}

.ai-search-loading.active {
    display: block;
}

.ai-search-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e0e0e0;
    border-top-color: #87CEEB;
    border-radius: 50%;
    animation: ai-search-spin 0.8s linear infinite;
}

@keyframes ai-search-spin {
    to { transform: rotate(360deg); }
}

.ai-search-results {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    max-height: 400px;
    overflow-y: auto;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.ai-search-results.active {
    display: block;
}

.ai-search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
    transition: background 0.15s ease;
    display: flex;
    gap: 12px;
    align-items: center;
}

.ai-search-result-item:last-child {
    border-bottom: none;
}

.ai-search-result-item:hover {
    background: #f9f9f9;
}

.ai-search-result-thumbnail {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 3px;
    overflow: hidden;
    background: #f0f0f0;
    border: 1px solid #e0e0e0;
}

.ai-search-result-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ai-search-result-content {
    flex: 1;
    min-width: 0;
}

.ai-search-result-title {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ai-search-result-meta {
    font-size: 12px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-search-result-table {
    display: inline-block;
    padding: 2px 6px;
    background: #f0f0f0;
    border-radius: 3px;
    font-size: 11px;
    color: #555;
}

.ai-search-no-results {
    padding: 20px 16px;
    text-align: center;
    color: #999;
    font-size: 14px;
}

.ai-search-error {
    padding: 12px 16px;
    background: #fff5f5;
    color: #c53030;
    font-size: 13px;
    border-left: 3px solid #87CEEB;
}

.ai-search-thinking {
    padding: 12px 16px;
    color: #666;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-search-thinking-dots {
    display: inline-flex;
    gap: 3px;
}

.ai-search-thinking-dot {
    width: 4px;
    height: 4px;
    background: #87CEEB;
    border-radius: 50%;
    animation: ai-search-thinking 1.4s ease-in-out infinite;
}

.ai-search-thinking-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.ai-search-thinking-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes ai-search-thinking {
    0%, 60%, 100% { opacity: 0.3; transform: scale(0.8); }
    30% { opacity: 1; transform: scale(1.1); }
}
</style>

<div class="ai-search-container">
    <div class="ai-search-wrapper">
        <select class="ai-search-category" id="aiSearchCategory">
            <option value="general">General</option>
            <option value="frames">Frames</option>
            <option value="characters">Characters</option>
            <option value="locations">Locations</option>
            <option value="backgrounds">Backgrounds</option>
            <option value="sketches">Sketches</option>
            <option value="artifacts">Artifacts</option>
            <option value="vehicles">Vehicles</option>
            <option value="storyboards">Storyboards</option>
            <option value="todos">Todos</option>
            <option value="code">Code</option>
            <option value="chat">Chat</option>
            <option value="gpt">GPT</option>
        </select>
        
        <div class="ai-search-input-container">
            <input 
                type="text" 
                class="ai-search-input" 
                id="aiSearchInput" 
                placeholder="Enter your search query..."
                autocomplete="off"
            >
        </div>
        
        <button class="ai-search-button" id="aiSearchButton" type="button">
            <div class="ai-search-icon" id="aiSearchIcon">
                <svg viewBox="0 0 24 24">
                    <path d="M22 2L11 13"></path>
                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                </svg>
            </div>
            <div class="ai-search-loading" id="aiSearchLoading">
                <div class="ai-search-spinner"></div>
            </div>
        </button>
    </div>
    <div class="ai-search-results" id="aiSearchResults"></div>
</div>

<script>
(function() {
    const searchInput = document.getElementById('aiSearchInput');
    const searchButton = document.getElementById('aiSearchButton');
    const searchCategory = document.getElementById('aiSearchCategory');
    const searchResults = document.getElementById('aiSearchResults');
    const searchIcon = document.getElementById('aiSearchIcon');
    const searchLoading = document.getElementById('aiSearchLoading');
    
    let currentRequest = null;
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ai-search-container')) {
            searchResults.classList.remove('active');
        }
    });
    
    // Search on button click
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        if (query.length >= 2) {
            performSearch(query);
        }
    });
    
    // Search on Enter key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = this.value.trim();
            if (query.length >= 2) {
                performSearch(query);
            }
        } else if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            this.blur();
        }
    });
    
    function performSearch(query) {
        // Cancel previous request
        if (currentRequest) {
            currentRequest.abort();
        }
        
        const category = searchCategory.value;
        
        // Disable button during search
        searchButton.disabled = true;
        searchIcon.style.display = 'none';
        searchLoading.classList.add('active');
        
        // Show thinking message
        searchResults.innerHTML = `
            <div class="ai-search-thinking">
                <span>AI is analyzing your query</span>
                <div class="ai-search-thinking-dots">
                    <span class="ai-search-thinking-dot"></span>
                    <span class="ai-search-thinking-dot"></span>
                    <span class="ai-search-thinking-dot"></span>
                </div>
            </div>
        `;
        searchResults.classList.add('active');
        
        // Create new request
        currentRequest = new XMLHttpRequest();
        currentRequest.open('POST', 'ai_search_endpoint.php', true);
        currentRequest.setRequestHeader('Content-Type', 'application/json');
        
        currentRequest.onload = function() {
            searchButton.disabled = false;
            searchIcon.style.display = 'block';
            searchLoading.classList.remove('active');
            
            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    displayResults(response);
                } catch (e) {
                    displayError('Failed to parse search results');
                }
            } else {
                displayError('Search request failed');
            }
            
            currentRequest = null;
        };
        
        currentRequest.onerror = function() {
            searchButton.disabled = false;
            searchIcon.style.display = 'block';
            searchLoading.classList.remove('active');
            displayError('Network error occurred');
            currentRequest = null;
        };
        
        currentRequest.send(JSON.stringify({ 
            query: query,
            category: category
        }));
    }
    
    function displayResults(response) {
        if (response.error) {
            displayError(response.error);
            return;
        }
        
        if (!response.results || response.results.length === 0) {
            searchResults.innerHTML = '<div class="ai-search-no-results">No results found</div>';
            searchResults.classList.add('active');
            return;
        }
        
        let html = '';
        response.results.forEach(result => {
            // Build thumbnail HTML if result has thumbnail
            let thumbnailHtml = '';
            if (result.thumbnail) {
                thumbnailHtml = `
                    <div class="ai-search-result-thumbnail">
                        <img src="${escapeHtml(result.thumbnail)}" alt="">
                    </div>
                `;
            }
            
            html += `
                <div class="ai-search-result-item" onclick="handleResultClick(${JSON.stringify(result).replace(/"/g, '&quot;')})">
                    ${thumbnailHtml}
                    <div class="ai-search-result-content">
                        <div class="ai-search-result-title">${escapeHtml(result.title)}</div>
                        <div class="ai-search-result-meta">
                            <span class="ai-search-result-table">${escapeHtml(result.table)}</span>
                            ${result.meta ? `<span>${escapeHtml(result.meta)}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        searchResults.innerHTML = html;
        searchResults.classList.add('active');
    }
    
    function displayError(message) {
        searchResults.innerHTML = `<div class="ai-search-error">${escapeHtml(message)}</div>`;
        searchResults.classList.add('active');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Make handleResultClick global so it can be called from onclick
    window.handleResultClick = function(result) {
        searchResults.classList.remove('active');
        searchInput.value = '';
        
        // Navigate to appropriate view based on result
        if (result.url) {
            window.location.href = result.url;
        } else if (result.table === 'frames' && result.id) {
            if (typeof showFrameDetailsModal === 'function') {
                showFrameDetailsModal(result.id);
            } else {
                window.location.href = `view_frame.php?frame_id=${result.id}`;
            }
        } else if (result.table === 'characters' && result.id) {
            window.location.href = `view_character.php?character_id=${result.id}`;
        } else {
            console.log('Selected result:', result);
        }
    };
})();
</script>
