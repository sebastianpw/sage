<?php

namespace App\Gallery;

class FrameDetails
{
    private $mysqli;
    public $frameData = null;
    public $entityName = '';
    public $error = null;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Loads the frame and its associated entity data.
     *
     * @param int $frameId
     * @return bool True on success, false on failure.
     */
    public function load(int $frameId): bool
    {
        if ($frameId <= 0) {
            $this->error = "Invalid Frame ID.";
            return false;
        }

        // 1. Fetch the frame data from the `frames` table.
        $stmt = $this->mysqli->prepare("SELECT * FROM frames WHERE id = ?");
        $stmt->bind_param('i', $frameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->frameData = $result->fetch_assoc();
        $stmt->close();

        if (!$this->frameData) {
            $this->error = "Frame #{$frameId} not found in the frames table.";
            return false;
        }

        // 2. Fetch the entity name using entity_type and entity_id.
        $entityType = $this->frameData['entity_type'];
        $entityId = $this->frameData['entity_id'];

        if ($entityType && $entityId) {
            // We trust entity_type is a valid table name as per your instructions.
            // Using prepared statement for entity_id is crucial for security.
            $query = "SELECT name FROM `" . $this->mysqli->real_escape_string($entityType) . "` WHERE id = ?";
            $stmt = $this->mysqli->prepare($query);
            if ($stmt) {
                $stmt->bind_param('i', $entityId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $this->entityName = $row['name'];
                }
                $stmt->close();
            } else {
                // This might happen if the table name is invalid, good to log.
                error_log("Failed to prepare statement for entity_type: " . $entityType);
            }
        }
        
        return true;
    }

    /**
     * Renders the frame details content into an HTML string.
     *
     * @return string The HTML content.
     */
    public function renderContent(): string
    {
        if (!$this->frameData) {
            return "<p>Error: Frame data not loaded.</p>";
        }

        // Make variables available to the included file
        $frameId = $this->frameData['id'];
        $entity = $this->frameData['entity_type'] ?? 'unknown';
        $entityId = $this->frameData['entity_id'] ?? 0;
        $entityName = $this->entityName ?: 'N/A';
        $filename = $this->frameData['filename'] ?? '';
        $prompt = $this->frameData['prompt'] ?? '';
        $style = $this->frameData['style'] ?? '';
        $entityUrl = "gallery_{$entity}_nu.php";

        // Use output buffering to capture the HTML from a separate file
        ob_start();
        ?>

<style>
/* Frame-specific styles extending base.css */
.frame-detail-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.frame-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(var(--muted-border-rgb), 1);
    flex-wrap: wrap;
    gap: 16px;
}

.frame-detail-header h1 {
    margin: 0;
    font-size: 24px;
    color: var(--text);
    font-weight: 600;
}

.frame-detail-nav {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.frame-detail-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 24px;
    align-items: start;
}

.frame-image-section {
    position: relative;
    background-color: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--card-elevation);
}

.frame-image-wrapper {
    position: relative;
    width: 100%;
    line-height: 0;
}

.frame-image {
    width: 100%;
    height: auto;
    display: block;
}

/* Gear icon positioning for frame detail view */
.frame-image-wrapper .gear-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 2em;
    cursor: pointer;
    color: #fff;
    text-shadow: 0 0 4px rgba(0, 0, 0, 0.8), 0 0 8px rgba(0, 0, 0, 0.6);
    z-index: 100;
    opacity: 0.7;
    transition: opacity 0.2s, transform 0.2s;
}

.frame-image-wrapper .gear-icon:hover {
    opacity: 1;
    transform: rotate(30deg);
}

.frame-metadata-section {
    background-color: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 20px;
    box-shadow: var(--card-elevation);
}

.metadata-header {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--text);
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.08);
}

.metadata-item {
    margin-bottom: 16px;
}

.metadata-item:last-child {
    margin-bottom: 0;
}

.metadata-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: block;
}

.metadata-value {
    color: var(--text);
    word-wrap: break-word;
    line-height: 1.5;
}

.metadata-value code {
    font-family: monospace;
    font-size: 12px;
    background-color: rgba(0, 0, 0, 0.1);
    padding: 2px 6px;
    border-radius: 3px;
}

/* Responsive layout */
@media (max-width: 968px) {
    .frame-detail-content {
        grid-template-columns: 1fr;
    }
    
    .frame-metadata-section {
        order: 2;
    }
    
    .frame-image-section {
        order: 1;
    }
}

@media (max-width: 640px) {
    .frame-detail-container {
        padding: 12px;
    }
    
    .frame-detail-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .frame-detail-nav {
        width: 100%;
    }
    
    .frame-detail-nav .btn {
        flex: 1;
        justify-content: center;
        text-align: center;
    }
}
</style>

<div class="frame-detail-container">
    <div class="frame-detail-header">
        <h1>Frame #<?= htmlspecialchars($frameId) ?></h1>
        <div class="frame-detail-nav">
            <a href="<?= htmlspecialchars($entityUrl) ?>" class="btn btn-secondary btn-sm">
                📂 <?= ucfirst(htmlspecialchars($entity)) ?> Gallery
            </a>
            <a href="entity_form.php?entity_type=<?= htmlspecialchars($entity) ?>&entity_id=<?= htmlspecialchars($entityId) ?>&redirect_url=<?= urlencode("view_frame.php?frame_id={$frameId}") ?>" class="btn btn-secondary btn-sm">
                ✏️ Edit Entity
            </a>
        </div>
    </div>
    
    <div class="frame-detail-content">
        <div class="frame-image-section">
            <div class="frame-image-wrapper img-wrapper" 
                 data-entity="<?= htmlspecialchars($entity) ?>" 
                 data-entity-id="<?= htmlspecialchars($entityId) ?>" 
                 data-frame-id="<?= htmlspecialchars($frameId) ?>">
                <img src="/<?= htmlspecialchars($filename) ?>" 
                     alt="<?= htmlspecialchars($entityName) ?>" 
                     class="frame-image">
            </div>
        </div>
        
        <div class="frame-metadata-section">
            <div class="metadata-header">Frame Information</div>
            
            <div class="metadata-item">
                <span class="metadata-label">Entity</span>
                <div class="metadata-value">
                    <?= htmlspecialchars($entityName) ?>
                    <span class="badge badge-blue"><?= ucfirst(htmlspecialchars($entity)) ?></span>
                </div>
            </div>
            
            <div class="metadata-item">
                <span class="metadata-label">Frame ID</span>
                <div class="metadata-value">
                    <code><?= htmlspecialchars($frameId) ?></code>
                </div>
            </div>
            
            <div class="metadata-item">
                <span class="metadata-label">Entity ID</span>
                <div class="metadata-value">
                    <code><?= htmlspecialchars($entityId) ?></code>
                </div>
            </div>
            
            <?php if ($prompt): ?>
            <div class="metadata-item">
                <span class="metadata-label">Prompt</span>
                <div class="metadata-value"><?= htmlspecialchars($prompt) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($style): ?>
            <div class="metadata-item">
                <span class="metadata-label">Style</span>
                <div class="metadata-value"><?= htmlspecialchars($style) ?></div>
            </div>
            <?php endif; ?>
            
            <div class="metadata-item">
                <span class="metadata-label">Filename</span>
                <div class="metadata-value">
                    <code><?= htmlspecialchars(basename($filename)) ?></code>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize frame details when ready
function initializeFrameDetailsScripts() {
    console.log('Frame details initialized');
    
    // Debug: Check if GearMenu is available
    if (typeof window.GearMenu !== 'undefined') {
        console.log('GearMenu object found:', window.GearMenu);
        
        // Find all img-wrappers
        const wrappers = document.querySelectorAll('.img-wrapper');
        console.log('Found img-wrappers:', wrappers.length, wrappers);
        
        // Try to attach gear menu
        if (typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(document);
            console.log('GearMenu.attach() called');
        }
    } else {
        console.warn('GearMenu not available. Make sure the module is loaded.');
    }
    
    // Debug: Check if ImageEditorModal is available
    if (typeof window.ImageEditorModal !== 'undefined') {
        console.log('ImageEditorModal found');
    } else {
        console.warn('ImageEditorModal not available');
    }
}
</script>

<?php
        $content = ob_get_clean(); 
        return $content;
    }
}
