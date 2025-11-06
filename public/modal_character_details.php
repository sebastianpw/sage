<?php
/**
 * Character Details Modal
 * 
 * Include this file in any view where you want to be able to show
 * character details in a modal overlay.
 * 
 * Usage: require 'modal_character_details.php';
 * Then call: showCharacterDetailsModal(characterId);
 */
?>

<style>
.character-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    overflow-y: auto;
    padding: 20px;
}

.character-modal-overlay.active {
    display: block;
}

.character-modal-container {
    max-width: 1400px;
    margin: 0 auto;
    background: #000;
    border-radius: 8px;
    position: relative;
    min-height: 200px;
}

.character-modal-close {
    position: fixed;
    top: 30px;
    right: 30px;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10001;
}

.character-modal-close:hover {
    background: rgba(135, 206, 235, 0.2);
    border-color: #87CEEB;
    transform: scale(1.1);
}

.character-modal-content {
    padding: 20px;
}

.character-modal-loading {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.character-modal-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #333;
    border-top-color: #87CEEB;
    border-radius: 50%;
    animation: character-modal-spin 0.8s linear infinite;
    margin: 0 auto 20px;
}

@keyframes character-modal-spin {
    to { transform: rotate(360deg); }
}

.character-modal-error {
    text-align: center;
    padding: 60px 20px;
    color: #c53030;
}

.character-modal-view-full {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 12px 24px;
    background: #87CEEB;
    color: #000;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10001;
    text-decoration: none;
    display: inline-block;
}

.character-modal-view-full:hover {
    background: #6fb3d9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(135, 206, 235, 0.3);
}
</style>

<div id="characterModalOverlay" class="character-modal-overlay">
    <div class="character-modal-close" onclick="closeCharacterModal()">&times;</div>
    <div class="character-modal-container">
        <div id="characterModalContent" class="character-modal-content">
            <div class="character-modal-loading">
                <div class="character-modal-spinner"></div>
                <div>Loading character details...</div>
            </div>
        </div>
    </div>
    <a id="characterModalViewFull" class="character-modal-view-full" href="#" style="display: none;">
        View Full Page
    </a>
</div>

<script>
let currentCharacterModalId = null;

/**
 * Show character details in a modal
 * @param {number} characterId - The ID of the character to display
 */
function showCharacterDetailsModal(characterId) {
    currentCharacterModalId = characterId;
    
    const overlay = document.getElementById('characterModalOverlay');
    const content = document.getElementById('characterModalContent');
    const viewFullBtn = document.getElementById('characterModalViewFull');
    
    // Show modal with loading state
    content.innerHTML = `
        <div class="character-modal-loading">
            <div class="character-modal-spinner"></div>
            <div>Loading character details...</div>
        </div>
    `;
    overlay.classList.add('active');
    viewFullBtn.style.display = 'none';
    
    // Disable body scroll
    document.body.style.overflow = 'hidden';
    
    // Load character content via AJAX
    fetch(`view_character.php?character_id=${characterId}&view=modal`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load character');
            }
            return response.text();
        })
        .then(html => {
            // Extract just the character details content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            const characterContainer = tempDiv.querySelector('.character-details-container');
            if (characterContainer) {
                content.innerHTML = characterContainer.outerHTML;
                
                // Re-initialize any scripts that might be in the content
                if (typeof initializeCharacterDetailsScripts === 'function') {
                    initializeCharacterDetailsScripts();
                }
                
                // Show view full page button
                viewFullBtn.href = `view_character.php?character_id=${characterId}`;
                viewFullBtn.style.display = 'inline-block';
            } else {
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="character-modal-error">
                    <h2>Error Loading Character</h2>
                    <p>${error.message}</p>
                </div>
            `;
        });
}

/**
 * Close the character details modal
 */
function closeCharacterModal() {
    const overlay = document.getElementById('characterModalOverlay');
    overlay.classList.remove('active');
    currentCharacterModalId = null;
    
    // Re-enable body scroll
    document.body.style.overflow = '';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && currentCharacterModalId !== null) {
        closeCharacterModal();
    }
});

// Close modal when clicking overlay (but not the content)
document.getElementById('characterModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCharacterModal();
    }
});
</script>
