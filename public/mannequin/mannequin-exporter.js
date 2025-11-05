// mannequin-exporter.js
// Enhanced export functionality for mannequin editor

import * as THREE from "three";

console.log('[MannequinExporter] Module loaded');

// Wait for DOM to be ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExporter);
} else {
    initExporter();
}

function initExporter() {
    console.log('[MannequinExporter] DOM ready, waiting for buttons...');
    
    // Wait for the export button to exist in the DOM
    const checkInterval = setInterval(() => {
        const exportBtn = document.getElementById('ep');
        if (exportBtn) {
            clearInterval(checkInterval);
            console.log('[MannequinExporter] Export button found, modifying UI');
            modifyUI();
            createModalStyles();
        }
    }, 100);
    
    // Safety timeout
    setTimeout(() => {
        clearInterval(checkInterval);
        console.log('[MannequinExporter] Timeout - buttons may not have been added');
    }, 10000);
}

function createModalStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .mannequin-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(3px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .mannequin-modal-overlay.active {
            display: flex;
        }
        
        .mannequin-modal {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 0;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: modalSlideIn 0.2s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .mannequin-modal h2 {
            margin: 0;
            padding: 20px 24px;
            color: #333;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 2px solid #87CEEB;
            background: #fafafa;
        }
        
        .mannequin-modal-content {
            padding: 24px;
            max-height: calc(80vh - 140px);
            overflow-y: auto;
        }
        
        .posture-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .posture-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            transition: all 0.15s;
            cursor: pointer;
        }
        
        .posture-item:hover {
            border-color: #87CEEB;
            background: #fafafa;
            transform: translateX(2px);
        }
        
        .posture-item-info {
            flex: 1;
        }
        
        .posture-item-name {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .posture-item-date {
            font-size: 11px;
            color: #999;
            font-family: monospace;
        }
        
        .posture-item-actions {
            display: flex;
            gap: 8px;
        }
        
        .posture-item button {
            padding: 6px 14px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 2px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.15s;
            font-size: 12px;
            color: #666;
        }
        
        .posture-item .load-btn {
            border-color: #87CEEB;
            color: #333;
        }
        
        .posture-item .load-btn:hover {
            background: #87CEEB;
            color: white;
        }
        
        .posture-item .delete-btn {
            border-color: #ddd;
            color: #999;
        }
        
        .posture-item .delete-btn:hover {
            background: #333;
            color: white;
            border-color: #333;
        }
        
        .modal-input-group {
            margin-bottom: 0;
        }
        
        .modal-input-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-weight: 500;
            font-size: 13px;
        }
        
        .modal-input-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.15s;
            font-family: inherit;
        }
        
        .modal-input-group input:focus {
            outline: none;
            border-color: #87CEEB;
        }
        
        .modal-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding: 16px 24px;
            border-top: 1px solid #e0e0e0;
            background: #fafafa;
        }
        
        .modal-buttons button {
            padding: 8px 20px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 2px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.15s;
            color: #666;
        }
        
        .modal-btn-primary {
            background: #87CEEB;
            color: white;
            border-color: #87CEEB;
        }
        
        .modal-btn-primary:hover {
            background: #5FB8DD;
            border-color: #5FB8DD;
        }
        
        .modal-btn-secondary {
            background: white;
            color: #666;
        }
        
        .modal-btn-secondary:hover {
            background: #f5f5f5;
        }
        
        .modal-btn-danger {
            background: white;
            color: #999;
            border-color: #ddd;
        }
        
        .modal-btn-danger:hover {
            background: #333;
            color: white;
            border-color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        
        .empty-state-text {
            font-size: 14px;
            line-height: 1.6;
            color: #666;
        }
        
        .empty-state-text strong {
            color: #333;
        }
    `;
    document.head.appendChild(style);
}

function modifyUI() {
    const exportBtn = document.getElementById('ep');
    if (!exportBtn) {
        console.error('[MannequinExporter] Export button not found');
        return;
    }

    // Remove the GLB export button
    exportBtn.remove();

    const postureGroup = document.querySelector('.button-group');
    if (!postureGroup) return;

    // Create Save button (replaces Export)
    const btnSave = document.createElement('button');
    btnSave.id = 'save';
    btnSave.textContent = 'Save';
    btnSave.title = 'Save current posture to library';
    
    // Create Load button
    const btnLoad = document.createElement('button');
    btnLoad.id = 'load';
    btnLoad.textContent = 'Load';
    btnLoad.title = 'Load a saved posture';
    
    // Create JSON export button
    const btnJSON = document.createElement('button');
    btnJSON.id = 'ej';
    btnJSON.textContent = 'Export JSON';
    btnJSON.title = 'Export posture as JSON file';
    
    // Create PNG export button  
    const btnPNG = document.createElement('button');
    btnPNG.id = 'eimg';
    btnPNG.textContent = 'Export Image';
    btnPNG.title = 'Export current view as PNG';
    
    // Add click handlers
    btnSave.addEventListener('click', (e) => { e.stopPropagation(); showSaveModal(); });
    btnLoad.addEventListener('click', (e) => { e.stopPropagation(); showLoadModal(); });
    btnJSON.addEventListener('click', (e) => { e.stopPropagation(); exportJSON(); });
    btnPNG.addEventListener('click', (e) => { e.stopPropagation(); exportPNG(); });
    
    // Insert buttons
    const setBtn = document.getElementById('sp');
    setBtn.after(document.createElement('br'));
    setBtn.after(btnSave);
    btnSave.after(document.createElement('br'));
    btnSave.after(btnLoad);
    btnLoad.after(document.createElement('br'));
    btnLoad.after(btnJSON);
    btnJSON.after(document.createElement('br'));
    btnJSON.after(btnPNG);
    
    
    const figureAddBtn = document.getElementById('am');
    const figureRemBtn = document.getElementById('rm');
    
    // hide buttons
    figureAddBtn.style.display = 'none';
    figureRemBtn.style.display = 'none';
    
    // hide their parent container
    figureAddBtn.parentElement.style.display = 'none';


    console.log('[MannequinExporter] UI modified successfully');
}

// Posture library stored in localStorage
const STORAGE_KEY = 'mannequin_posture_library';

function getPostureLibrary() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (error) {
        console.error('[MannequinExporter] Failed to load library:', error);
        return [];
    }
}

function savePostureLibrary(library) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(library));
        return true;
    } catch (error) {
        console.error('[MannequinExporter] Failed to save library:', error);
        showErrorModal('Failed to save posture library: ' + error.message);
        return false;
    }
}

// Create and show modal
function showModal(title, content, buttons) {
    // Remove existing modal if any
    const existingModal = document.querySelector('.mannequin-modal-overlay');
    if (existingModal) {
        existingModal.remove();
    }
    
    const overlay = document.createElement('div');
    overlay.className = 'mannequin-modal-overlay';
    
    const modal = document.createElement('div');
    modal.className = 'mannequin-modal';
    
    const titleEl = document.createElement('h2');
    titleEl.textContent = title;
    
    const contentEl = document.createElement('div');
    contentEl.className = 'mannequin-modal-content';
    if (typeof content === 'string') {
        contentEl.innerHTML = content;
    } else {
        contentEl.appendChild(content);
    }
    
    const buttonsEl = document.createElement('div');
    buttonsEl.className = 'modal-buttons';
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.textContent = btn.text;
        button.className = btn.className || 'modal-btn-secondary';
        // stopPropagation prevents the original click from bubbling and accidentally hitting the overlay
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            try {
                if (btn.onClick) btn.onClick();
            } catch (err) {
                console.error('[MannequinExporter] Modal button handler error:', err);
            }
            // default behavior: close modal after onClick unless explicitly set to keep open
            if (btn.keepOpen !== true) {
                closeModal();
            }
        });
        buttonsEl.appendChild(button);
    });
    
    modal.appendChild(titleEl);
    modal.appendChild(contentEl);
    modal.appendChild(buttonsEl);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Prevent immediate clicks (the common problem: original click that opened modal also triggers overlay)
    // We suppress overlay clicks for a short window after creation.
    overlay._suppressClickUntil = performance.now() + 250; // ms

    overlay.addEventListener('click', (e) => {
        // ignore clicks that happen too soon after creation (e.g. the same click that opened the modal)
        if (performance.now() < overlay._suppressClickUntil) {
            return;
        }
        if (e.target === overlay) closeModal();
    });
    
    // Show with animation (activate on next tick)
    setTimeout(() => overlay.classList.add('active'), 10);
    
    return overlay;
}

function closeModal() {
    const overlay = document.querySelector('.mannequin-modal-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    }
}

function showErrorModal(message) {
    showModal('Error', `<p style="color: #666; font-weight: 500;">${message}</p>`, [
        { text: 'OK', className: 'modal-btn-primary' }
    ]);
}

function showSaveModal() {
    const currentModel = window.mannequinEditorState?.model || window.model;
    
    if (!currentModel) {
        showErrorModal('No model selected to save');
        return;
    }

    const inputGroup = document.createElement('div');
    inputGroup.className = 'modal-input-group';
    
    const label = document.createElement('label');
    label.textContent = 'Posture Name:';
    
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'Enter a name for this posture...';
    input.value = `Pose ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`;
    
    inputGroup.appendChild(label);
    inputGroup.appendChild(input);
    
    setTimeout(() => input.select(), 100);
    
    const saveAction = () => {
        const name = input.value.trim();
        if (!name) {
            showErrorModal('Please enter a name for the posture');
            return;
        }
        
        try {
            const postureData = currentModel.postureString;
            if (!postureData) {
                throw new Error('No posture data available');
            }
            
            const library = getPostureLibrary();
            
            // If a posture with the same name exists, overwrite silently.
            const existingIndex = library.findIndex(p => p.name === name);
            if (existingIndex !== -1) {
                library[existingIndex] = {
                    name: name,
                    data: postureData,
                    timestamp: Date.now()
                };
            } else {
                library.push({
                    name: name,
                    data: postureData,
                    timestamp: Date.now()
                });
            }
            
            if (savePostureLibrary(library)) {
                showModal('Success', 
                    `<p style="color: #333;">✓ Posture "<strong>${name}</strong>" saved successfully!</p>
                     <p style="color: #999; font-size: 13px; margin-top: 10px;">Total saved postures: ${library.length}</p>`,
                    [{ text: 'OK', className: 'modal-btn-primary' }]
                );
            }
        } catch (error) {
            console.error('[MannequinExporter] Save failed:', error);
            showErrorModal('Failed to save posture: ' + error.message);
        }
    };
    
    showModal('Save Posture', inputGroup, [
        { text: 'Cancel', className: 'modal-btn-secondary' },
        { 
            text: 'Save', 
            className: 'modal-btn-primary', 
            onClick: saveAction 
        }
    ]);
    
    // Allow Enter key to save
    input.onkeypress = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveAction();
        }
    };
}

function showLoadModal() {
    const currentModel = window.mannequinEditorState?.model || window.model;
    
    if (!currentModel) {
        showErrorModal('No model selected to load posture into');
        return;
    }

    const library = getPostureLibrary();
    
    if (library.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <div class="empty-state-icon">📭</div>
            <div class="empty-state-text">
                <strong>No saved postures yet</strong><br>
                Use the "Save" button to save your first posture!
            </div>
        `;
        
        showModal('Load Posture', emptyState, [
            { text: 'Close', className: 'modal-btn-secondary' }
        ]);
        return;
    }

    const listContainer = document.createElement('div');
    listContainer.className = 'posture-list';
    
    // Sort by timestamp (newest first)
    const sortedLibrary = [...library].sort((a, b) => b.timestamp - a.timestamp);
    
    sortedLibrary.forEach((posture, idx) => {
        const originalIndex = library.findIndex(p => p.timestamp === posture.timestamp && p.name === posture.name);
        const item = document.createElement('div');
        item.className = 'posture-item';
        
        const info = document.createElement('div');
        info.className = 'posture-item-info';
        
        const name = document.createElement('div');
        name.className = 'posture-item-name';
        name.textContent = posture.name;
        
        const date = document.createElement('div');
        date.className = 'posture-item-date';
        date.textContent = new Date(posture.timestamp).toLocaleString();
        
        info.appendChild(name);
        info.appendChild(date);
        
        const actions = document.createElement('div');
        actions.className = 'posture-item-actions';
        
        const loadBtn = document.createElement('button');
        loadBtn.textContent = 'Load';
        loadBtn.className = 'load-btn';
        loadBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            loadPostureByIndex(originalIndex);
        });
        
        const deleteBtn = document.createElement('button');
        deleteBtn.textContent = 'Delete';
        deleteBtn.className = 'delete-btn';
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            deletePostureByIndex(originalIndex);
        });
        
        actions.appendChild(loadBtn);
        actions.appendChild(deleteBtn);
        
        item.appendChild(info);
        item.appendChild(actions);
        
        // Click on item to load
        item.addEventListener('click', () => loadPostureByIndex(originalIndex));
        
        listContainer.appendChild(item);
    });
    
    showModal('Load Posture', listContainer, [
        { 
            text: 'Delete All', 
            className: 'modal-btn-danger',
            onClick: () => {
                showModal('Confirm Delete All',
                    '<p style="color: #333;">Are you sure you want to delete <strong>ALL</strong> saved postures?</p><p style="color: #999; margin-top: 8px;">This cannot be undone!</p>',
                    [
                        { text: 'Cancel', className: 'modal-btn-secondary' },
                        {
                            text: 'Delete All',
                            className: 'modal-btn-danger',
                            onClick: () => {
                                localStorage.removeItem(STORAGE_KEY);
                                showModal('Success', '<p style="color: #333;">✓ All postures have been deleted</p>', [
                                    { text: 'OK', className: 'modal-btn-primary' }
                                ]);
                            }
                        }
                    ]
                );
            }
        },
        { text: 'Close', className: 'modal-btn-secondary' }
    ]);
}

function loadPostureByIndex(index) {
    const currentModel = window.mannequinEditorState?.model || window.model;
    const library = getPostureLibrary();
    
    if (index < 0 || index >= library.length) {
        showErrorModal('Invalid posture selection');
        return;
    }

    try {
        const posture = library[index];
        currentModel.postureString = posture.data;
        
        const renderer = window.mannequinEditorState?.renderer || window.renderer;
        const camera = window.mannequinEditorState?.camera || window.camera;
        const scene = window.mannequinEditorState?.scene || window.scene;
        
        if (renderer && camera && scene) {
            renderer.render(scene, camera);
        }
        
        console.log('[MannequinExporter] Posture loaded:', posture.name);
        closeModal();
        
        // Show brief success message
        showModal('Success', `<p style="color: #333;">✓ Loaded "<strong>${posture.name}</strong>"</p>`, [
            { text: 'OK', className: 'modal-btn-primary' }
        ]);
    } catch (error) {
        console.error('[MannequinExporter] Load failed:', error);
        showErrorModal('Failed to load posture: ' + error.message);
    }
}

function deletePostureByIndex(index) {
    const library = getPostureLibrary();
    
    if (index < 0 || index >= library.length) {
        showErrorModal('Invalid posture selection');
        return;
    }

    const posture = library[index];
    
    showModal('Confirm Delete',
        `<p style="color: #333;">Delete posture "<strong>${posture.name}</strong>"?</p><p style="color: #999; font-size: 13px; margin-top: 8px;">This cannot be undone.</p>`,
        [
            { text: 'Cancel', className: 'modal-btn-secondary' },
            {
                text: 'Delete',
                className: 'modal-btn-danger',
                onClick: () => {
                    library.splice(index, 1);
                    savePostureLibrary(library);
                    console.log('[MannequinExporter] Posture deleted:', posture.name);
                    
                    // Refresh the load modal
                    showLoadModal();
                }
            }
        ]
    );
}

// Export current posture as JSON file
function exportJSON() {
    console.log('[MannequinExporter] JSON export started');
    
    const currentModel = window.mannequinEditorState?.model || window.model;
    
    if (!currentModel) {
        showErrorModal('No model selected to export');
        return;
    }

    try {
        const postureData = currentModel.postureString;
        if (!postureData) {
            throw new Error('No posture data available');
        }
        
        const blob = new Blob([postureData], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `mannequin_posture_${Date.now()}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        
        console.log('[MannequinExporter] JSON exported successfully');
    } catch (error) {
        console.error('[MannequinExporter] JSON export failed:', error);
        showErrorModal('Failed to export JSON: ' + error.message);
    }
}

// Export current view as PNG
function exportPNG() {
    console.log('[MannequinExporter] PNG export started');
    
    const renderer = window.mannequinEditorState?.renderer || window.renderer;
    const camera = window.mannequinEditorState?.camera || window.camera;
    const scene = window.mannequinEditorState?.scene || window.scene;
    
    if (!renderer || !camera || !scene) {
        showErrorModal('Scene not ready for export');
        return;
    }

    try {
        const canvas = renderer.domElement;
        const currentWidth = canvas.width;
        const currentHeight = canvas.height;
        
        const exportWidth = 1920;
        const exportHeight = 1080;
        
        renderer.setSize(exportWidth, exportHeight);
        camera.aspect = exportWidth / exportHeight;
        camera.updateProjectionMatrix();
        
        renderer.render(scene, camera);
        
        const dataURL = canvas.toDataURL('image/png');
        
        renderer.setSize(currentWidth, currentHeight);
        camera.aspect = currentWidth / currentHeight;
        camera.updateProjectionMatrix();
        renderer.render(scene, camera);
        
        const link = document.createElement('a');
        link.href = dataURL;
        link.download = `mannequin_${Date.now()}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('[MannequinExporter] PNG exported successfully');
    } catch (error) {
        console.error('[MannequinExporter] PNG export failed:', error);
        showErrorModal('Failed to export PNG: ' + error.message);
    }
}

// Export to window for debugging
window.mannequinExporter = {
    showSaveModal,
    showLoadModal,
    exportJSON,
    exportPNG,
    getPostureLibrary,
    clearLibrary: () => {
        showModal('Confirm Delete All',
            '<p style="color: #333;">Delete all saved postures?</p><p style="color: #999; margin-top: 8px;">This cannot be undone!</p>',
            [
                { text: 'Cancel', className: 'modal-btn-secondary' },
                {
                    text: 'Delete All',
                    className: 'modal-btn-danger',
                    onClick: () => {
                        localStorage.removeItem(STORAGE_KEY);
                        showModal('Success', '<p style="color: #333;">✓ Library cleared</p>', [
                            { text: 'OK', className: 'modal-btn-primary' }
                        ]);
                    }
                }
            ]
        );
    }
};

console.log('[MannequinExporter] Ready');