<?php

namespace App\Gallery;

class FrameDetails
{
    private $mysqli;
    public $frameData = null;
    public $entityName = '';
    public $error = null;

    // Lore/KG references (populated in load())
    private $loreRef = null;   // ['doc_id', 'entity_type', 'entity_name', 'sketch_id']
    private $kgRef   = null;   // ['id', 'name', 'node_type']

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function load(int $frameId): bool
    {
        if ($frameId <= 0) {
            $this->error = "Invalid Frame ID.";
            return false;
        }

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

        $entityType = $this->frameData['entity_type'];
        $entityId = $this->frameData['entity_id'];

        if ($entityType && $entityId) {
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
                error_log("Failed to prepare statement for entity_type: " . $entityType);
            }
        }

        // --- Lore & KG reference lookup via sketch_lore_history ---
        // Find the most recent sketch_lore_history entry for this frame's sketch.
        // The frame may be directly owned by a sketch (entity_type='sketches') or
        // linked via frames_2_sketches.
        $sketchId = null;
        if ($entityType === 'sketches' && $entityId) {
            $sketchId = (int)$entityId;
        } else {
            // Check frames_2_sketches
            $stmt = $this->mysqli->prepare(
                "SELECT to_id FROM frames_2_sketches WHERE from_id = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('i', $frameId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $sketchId = (int)$row['to_id'];
                }
                $stmt->close();
            }
        }

        if ($sketchId) {
            // Fetch all sketch_lore_history rows for this sketch (newest first).
            // KG generator writes singular entity_type (e.g. 'character', 'location').
            // Lore generator writes plural entity_type (e.g. 'characters', 'locations').
            // KG is the leading/curated system. The KG link is resolved by name lookup
            // against kg_nodes directly — independent of which system generated the sketch.
            // The lore link is resolved from plural-typed history rows only.
            $stmt = $this->mysqli->prepare(
                "SELECT slh.doc_id, slh.entity_type, slh.entity_name
                 FROM sketch_lore_history slh
                 WHERE slh.sketch_id = ?
                 ORDER BY slh.id DESC"
            );
            $histRows = [];
            if ($stmt) {
                $stmt->bind_param('i', $sketchId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $histRows[] = $row;
                }
                $stmt->close();
            }

            $anyEntityName  = null;  // first entity name found, any world
            $loreDocId      = null;
            $loreEntityName = null;
            $loreEntityType = null;

            foreach ($histRows as $row) {
                // Capture the first entity name we see regardless of world
                if ($anyEntityName === null) {
                    $anyEntityName = $row['entity_name'];
                }

                // Plural entity_type = lore-derived
                if ($loreDocId === null && substr($row['entity_type'], -1) === 's') {
                    $loreDocId      = (int)$row['doc_id'];
                    $loreEntityName = $row['entity_name'];
                    $loreEntityType = $row['entity_type'];
                }

                // Stop once we have the entity name and a lore candidate
                if ($anyEntityName !== null && $loreDocId !== null) break;
            }

            // Lore link: validate doc_id maps to an active documentation
            if ($loreDocId) {
                $stmt = $this->mysqli->prepare(
                    "SELECT id FROM documentations WHERE id = ? AND is_active = 1 LIMIT 1"
                );
                if ($stmt) {
                    $stmt->bind_param('i', $loreDocId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()) {
                        $this->loreRef = [
                            'doc_id'      => $loreDocId,
                            'entity_type' => $loreEntityType,
                            'entity_name' => $loreEntityName,
                        ];
                    }
                    $stmt->close();
                }
            }

            // KG link: always attempt by entity name — KG is the leading system.
            // Works whether the sketch was KG-derived, lore-derived, or both.
            if ($anyEntityName) {
                $stmt = $this->mysqli->prepare(
                    "SELECT id, name, node_type FROM kg_nodes
                     WHERE LOWER(name) = LOWER(?) AND status = 'active'
                     LIMIT 1"
                );
                if ($stmt) {
                    $stmt->bind_param('s', $anyEntityName);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $this->kgRef = $row;
                    }
                    $stmt->close();
                }
            }
        }
        // --- End lore/KG lookup ---

        return true;
    }

    public function renderContent(): string
    {
        if (!$this->frameData) {
            return "<p>Error: Frame data not loaded.</p>";
        }

        $frameId = $this->frameData['id'];
        $mapRunId = $this->frameData['map_run_id'];
        $entity = $this->frameData['entity_type'] ?? 'unknown';
        $entityId = $this->frameData['entity_id'] ?? 0;
        $entityName = $this->entityName ?: 'N/A';
        $filename = $this->frameData['filename'] ?? '';
        $prompt = $this->frameData['prompt'] ?? '';
        $prompt_negative = $this->frameData['prompt_negative'] ?? '';
        $seed = $this->frameData['seed'] ?? '';
        $style = $this->frameData['style'] ?? '';
        $mapRunId = array_key_exists('map_run_id', $this->frameData) ? $this->frameData['map_run_id'] : null;
        $entityUrl = "gallery_{$entity}_nu.php";
        $hasDepthMap = !empty($this->frameData['depth_map_filename']);

        // Pre-render lore/KG reference HTML (inserted once, reused in both layout branches)
        $loreRefHtml = '';
        if ($this->loreRef) {
            $docId      = (int)$this->loreRef['doc_id'];
            $entType    = htmlspecialchars($this->loreRef['entity_type']);
            $entName    = htmlspecialchars($this->loreRef['entity_name']);
            $loreUrl    = "view_curated_docs.php?doc_id={$docId}&focus_type=" . urlencode($this->loreRef['entity_type']) . "&focus_entity=" . urlencode($this->loreRef['entity_name']);
            $loreRefHtml .= '<div class="metadata-item">'
                . '<span class="metadata-label">Lore Doc</span>'
                . '<div class="metadata-value">'
                . '<a href="' . $loreUrl . '" target="_blank" style="color:var(--text); text-decoration:none;" title="Open in Story Bible">'
                . '📜 ' . $entName . '</a>'
                . ' <span style="font-size:0.8em; color:var(--text-muted);">(' . $entType . ')</span>'
                . '</div>'
                . '</div>';
        }

        $kgRefHtml = '';
        if ($this->kgRef) {
            $kgNodeId   = (int)$this->kgRef['id'];
            $kgName     = htmlspecialchars($this->kgRef['name']);
            $kgType     = htmlspecialchars($this->kgRef['node_type'] ?? '');
            $kgUrl      = "kg_view.php?node_id={$kgNodeId}";
            $kgRefHtml .= '<div class="metadata-item">'
                . '<span class="metadata-label">KG Node</span>'
                . '<div class="metadata-value">'
                . '<a href="' . $kgUrl . '" target="_blank" style="color:var(--text); text-decoration:none;" title="Open in Knowledge Graph">'
                . '🔮 ' . $kgName . '</a>'
                . ' <span style="font-size:0.8em; color:var(--text-muted);">(' . $kgType . ')</span>'
                . '</div>'
                . '</div>';
        }

        // Graph links/modal triggers — shown when Fuzz, KG node or AG doc is available.
        // Placed below the KG/Lore ref blocks in the metadata panel.
        $miniGraphHtml = '';
        $fuzzSafeEntities = ['sketches', 'characters', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles'];
        $showFuzzGraph = in_array($entity, $fuzzSafeEntities) && $entityId > 0;

        if ($showFuzzGraph || $this->kgRef || $this->loreRef) {
            $miniGraphHtml .= '<div class="metadata-item">'
                . '<span class="metadata-label">Graphs</span>'
                . '<div class="metadata-value" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">';

            if ($showFuzzGraph) {
                $fuzzUrl = "/fuzzgraph.php?entity=" . urlencode($entity) . "&id=" . (int)$entityId;
                $miniGraphHtml .= '<a href="' . htmlspecialchars($fuzzUrl) . '" target="_blank"'
                    . ' style="color:var(--text); text-decoration:none; font-size:0.85em;"'
                    . ' title="Open Fuzz Graph in new tab">🕸️ Fuzz</a>';
                $miniGraphHtml .= ' <button onclick="window.showMiniGraphModal(' . htmlspecialchars(json_encode($fuzzUrl), ENT_QUOTES) . ')"'
                    . ' style="background:none; border:1px solid var(--border); border-radius:4px;'
                    . ' padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em;'
                    . ' font-family:inherit;" title="Open Fuzz Graph in modal">'
                    . '⤢ modal</button>';
            }

            if ($this->kgRef) {
                if ($showFuzzGraph) {
                    $miniGraphHtml .= ' <span style="color:var(--border);">|</span> ';
                }
                
                $kgNodeId  = (int)$this->kgRef['id'];
                $kgMgUrl   = 'mini_graph.php?graph=kg&node_id=' . $kgNodeId;
                // Inline link (new tab)
                $miniGraphHtml .= '<a href="' . htmlspecialchars($kgMgUrl) . '" target="_blank"'
                    . ' style="color:var(--text); text-decoration:none; font-size:0.85em;"'
                    . ' title="Open KG mini-graph in new tab">🔮 KG</a>';
                // Modal trigger — json_encode then htmlspecialchars so double-quotes don't break the attribute
                $miniGraphHtml .= ' <button onclick="window.showMiniGraphModal(' . htmlspecialchars(json_encode($kgMgUrl), ENT_QUOTES) . ')"'
                    . ' style="background:none; border:1px solid var(--border); border-radius:4px;'
                    . ' padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em;'
                    . ' font-family:inherit;" title="Open KG mini-graph in modal">'
                    . '⤢ modal</button>';
            }

            if ($this->loreRef) {
                $agDocId   = (int)$this->loreRef['doc_id'];
                // For AG mini-graph we need the ag_node id for this entity.
                // We resolve it lazily in JS; pass graph=ag&doc_id=N and rely on
                // the ag_nodes name lookup via a helper endpoint, OR we embed the
                // search directly: mini_graph.php?graph=ag&doc_id=N&node_id=0
                // resolves gracefully (shows error). Better: we look it up in PHP here.
                $agNodeId  = 0;
                $agStmt    = $this->mysqli->prepare(
                    "SELECT id FROM ag_nodes WHERE doc_id = ? AND LOWER(name) = LOWER(?) AND status='active' LIMIT 1"
                );
                if ($agStmt) {
                    $agStmt->bind_param('is', $agDocId, $this->loreRef['entity_name']);
                    $agStmt->execute();
                    $agRes = $agStmt->get_result();
                    if ($agRow = $agRes->fetch_assoc()) {
                        $agNodeId = (int)$agRow['id'];
                    }
                    $agStmt->close();
                }

                if ($agNodeId > 0) {
                    $agMgUrl = 'mini_graph.php?graph=ag&doc_id=' . $agDocId . '&node_id=' . $agNodeId;
                    if ($showFuzzGraph || $this->kgRef) {
                        $miniGraphHtml .= ' <span style="color:var(--border);">|</span> ';
                    }
                    $miniGraphHtml .= '<a href="' . htmlspecialchars($agMgUrl) . '" target="_blank"'
                        . ' style="color:var(--text); text-decoration:none; font-size:0.85em;"'
                        . ' title="Open AG mini-graph in new tab">📜 AG</a>';
                    $miniGraphHtml .= ' <button onclick="window.showMiniGraphModal(' . htmlspecialchars(json_encode($agMgUrl), ENT_QUOTES) . ')"'
                        . ' style="background:none; border:1px solid var(--border); border-radius:4px;'
                        . ' padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em;'
                        . ' font-family:inherit;" title="Open AG mini-graph in modal">'
                        . '⤢ modal</button>';
                }
            }

            $miniGraphHtml .= '</div></div>';
        }

        ob_start();
        ?>

<style>
/* Frame-specific styles */
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
    display: inline-flex;
    align-items: center;
}

.frame-detail-nav {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Icons */
.copy-frame-id-btn, .vision-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.2em;
    margin-left: 8px;
    padding: 0 4px;
    display: inline-flex;
    align-items: center;
    opacity: 0.7;
    transition: opacity 0.2s, transform 0.2s;
    vertical-align: middle;
    color: var(--text);
}
.copy-frame-id-btn:hover, .vision-btn:hover {
    opacity: 1;
    transform: scale(1.1);
}
.copy-frame-id-btn:active, .vision-btn:active {
    transform: scale(0.95);
}

.vision-btn {
    margin-left: 4px;
    font-size: 1.4em; /* Larger for the Eye */
}

/* Layout */
.frame-detail-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.split-50-50 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    align-items: start;
}

.split-50-50 > .frame-metadata-section {
    min-width: 0;
}

.frame-image-section {
    position: relative;
    background-color: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--card-elevation);
    text-align: center;
}

.frame-image-wrapper {
    position: relative;
    width: 100%;
    line-height: 0;
}

.frame-image {
    max-width: 100%;
    width: auto;
    height: auto;
    max-height: 75vh;
    margin: 0 auto;
    display: inline-block;
}

.frame-metadata-section {
    background-color: var(--card);
    border: 1px solid rgba(var(--muted-border-rgb), 0.08);
    border-radius: 8px;
    padding: 20px;
    box-shadow: var(--card-elevation);
    height: 100%;
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

/* Vision Modal Styles */
#visionModal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
}

#visionModalContent {
    background-color: var(--bg-color, #fff);
    color: var(--text-color, #000);
    margin: 5% auto;
    padding: 20px;
    border: 1px solid var(--border-color, #888);
    border-radius: 8px;
    width: 90%;
    max-width: 650px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}[data-theme="dark"] #visionModalContent {
    background-color: #2a2a2a;
    border-color: #444;
    color: #eee;
}

.vision-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid var(--border-color, #ccc);
    padding-bottom: 10px;
}

.vision-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}
.vision-close:hover { color: var(--text-color, #000); }

.vision-label {
    font-size: 0.9em;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--text-muted, #666);
}

.vision-textarea {
    width: 100%;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid #ccc;
    font-family: monospace;
    background: var(--input-bg, #fff);
    color: var(--input-text, #000);
    resize: vertical;
    margin-bottom: 10px;
    box-sizing: border-box;
}

#visionPromptInput { height: 80px; }
#visionResultText { height: 150px; }

.vision-actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.vision-btn-ui {
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid #ccc;
    background: #f0f0f0;
    color: #333;
    font-weight: bold;
}
.vision-btn-ui:hover { background: #e0e0e0; }
.vision-btn-ui.primary {
    background: #007bff;
    color: white;
    border-color: #0056b3;
}
.vision-btn-ui.primary:hover { background: #0056b3; }

#visionLoading {
    text-align: center;
    padding: 20px;
    font-style: italic;
    color: var(--text-muted);
}
.vision-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0,0,0,0.1);
    border-radius: 50%;
    border-top-color: #007bff;
    animation: spin 1s ease-in-out infinite;
    vertical-align: middle;
    margin-right: 10px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Separator between input and result */
.vision-separator {
    margin: 15px 0;
    border-top: 1px solid var(--border-color, #eee);
}

/* Responsive layout */
@media (max-width: 968px) {
    .split-50-50 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .frame-metadata-section {
        padding: 14px;
    }
}

@media (max-width: 640px) {
    .frame-detail-container { padding: 12px; }
    .frame-detail-header { flex-direction: column; align-items: flex-start; }
    .frame-detail-nav { width: 100%; }
    .frame-detail-nav .btn { flex: 1; justify-content: center; text-align: center; }
}
</style>

<div class="frame-detail-container">
    <div class="frame-detail-header">
        <h1>
            Frame #<?= $frameId ?>
            <button class="copy-frame-id-btn" data-frame-id="<?= htmlspecialchars($frameId) ?>" title="Copy Frame ID to clipboard" aria-label="Copy Frame ID">📋</button>
            <button class="vision-btn" id="visionTriggerBtn" title="Analyze Image (AI Vision)">&#128065;</button>
        </h1>
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
        <!-- 1. Full-width Main Image -->
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
        
        <?php if ($hasDepthMap): ?>
        <!-- 2. 50:50 Split Row -->
        <div class="split-50-50">
            <!-- Left: Depth Map -->
            <div class="frame-metadata-section">
                <div class="metadata-header">Depth Map</div>
                <?php if (!empty($this->frameData['depth_map_filename'])): ?>
                <div id="pswp-depth-preview" style="border-radius: 6px; overflow: hidden; border: 1px solid rgba(var(--muted-border-rgb), 0.1); box-shadow: var(--card-elevation); background: #000;">
                    <a href="/<?= htmlspecialchars($this->frameData['depth_map_filename']) ?>" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                        <img src="/<?= htmlspecialchars($this->frameData['depth_map_filename']) ?>" style="width: 100%; height: auto; display: block; transition: transform 0.2s;" alt="Depth Map" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                    </a>
                </div>
                <?php else: ?>
                <div style="padding: 20px; text-align: center; color: var(--text-muted); border: 1px dashed rgba(var(--muted-border-rgb), 0.2); border-radius: 8px;">No Depth Map Available</div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Frame Information Block -->
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
                    <span class="metadata-label">Map Run ID</span>
                    <div class="metadata-value">
                        <code><?= htmlspecialchars($mapRunId) ?></code>
                        <a href="view_scrollmagic_map_run.php?map_run_id=<?= $mapRunId ?>" target="_blank" title="ScrollMagic" style="margin-left:6px; color:var(--text-muted); text-decoration:none;">🎬</a>
                        <a href="enhanimaticism.php?entity_type=<?= $entity ?>&map_run_id=<?= $mapRunId ?>" target="_blank" title="Enhanimatics" style="margin-left:4px; color:var(--text-muted); text-decoration:none;">✨</a>
                    </div>
                </div>

                <?= $loreRefHtml ?>
                <?= $kgRefHtml ?>
                <?= $miniGraphHtml ?>
                
                <div class="metadata-item">
                    <span class="metadata-label">Entity ID</span>
                    <div class="metadata-value">
                        <code><?php echo '<a href="entity_form.php?entity_type=' . $entity . '&entity_id=' . $entityId . '" target="_blank" style="color: var(--text);">' . htmlspecialchars($entityId) . '</a>'; ?></code>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- 2. Full-width Info Block when no Depth Map -->
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
                <span class="metadata-label">Map Run ID</span>
                <div class="metadata-value">
                    <code><?= htmlspecialchars($mapRunId) ?></code>
                    <a href="view_scrollmagic_map_run.php?map_run_id=<?= $mapRunId ?>" target="_blank" title="ScrollMagic" style="margin-left:6px; color:var(--text-muted); text-decoration:none;">🎬</a>
                    <a href="enhanimaticism.php?entity_type=<?= $entity ?>&map_run_id=<?= $mapRunId ?>" target="_blank" title="Enhanimatics" style="margin-left:4px; color:var(--text-muted); text-decoration:none;">✨</a>
                </div>
            </div>

            <?= $loreRefHtml ?>
            <?= $kgRefHtml ?>
            <?= $miniGraphHtml ?>
            
            <div class="metadata-item">
                <span class="metadata-label">Entity ID</span>
                <div class="metadata-value">
                    <code><?php echo '<a href="entity_form.php?entity_type=' . $entity . '&entity_id=' . $entityId . '" target="_blank" style="color: var(--text);">' . htmlspecialchars($entityId) . '</a>'; ?></code>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 3. One Column Details Section -->
        <div class="frame-metadata-section">
            <div class="metadata-header">Details</div>
            
            <?php if ($prompt): ?>
            <div class="metadata-item">
                <span class="metadata-label">Prompt</span>
                <div class="metadata-value"><?= nl2br(htmlspecialchars($prompt)) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($prompt_negative): ?>
            <div class="metadata-item">
                <span class="metadata-label">Negative Prompt</span>
                <div class="metadata-value"><?= nl2br(htmlspecialchars($prompt_negative)) ?></div>
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

<!-- Vision Result Modal -->
<div id="visionModal">
    <div id="visionModalContent">
        <div class="vision-header">
            <h3 style="margin:0;">AI Vision Analysis</h3>
            <span class="vision-close" onclick="closeVisionModal()">&times;</span>
        </div>
        
        <!-- Step 1: Input -->
        <div id="visionInputSection">
            <div class="vision-label">Instructions for AI:</div>
            <textarea id="visionPromptInput" class="vision-textarea">Describe this image in a comma-separated format suitable for Stable Diffusion prompting. Describe every detail. Focus on visual elements, style, lighting, and composition. Do not include introductory text.</textarea>
            <div class="vision-actions">
                <button class="vision-btn-ui primary" onclick="submitVisionRequest()">Generate Description</button>
            </div>
        </div>

        <!-- Step 1.5: Loading -->
        <div id="visionLoading" style="display:none;">
            <div class="vision-spinner"></div> Analyzing image with computer vision...
        </div>

        <!-- Step 2: Result (Hidden initially) -->
        <div id="visionResultSection" style="display:none;">
            <div class="vision-separator"></div>
            <div class="vision-label">Generated Description:</div>
            <textarea id="visionResultText" class="vision-textarea" readonly></textarea>
            <div class="vision-actions">
                <button class="vision-btn-ui" onclick="closeVisionModal()">Close</button>
                <button class="vision-btn-ui primary" onclick="copyVisionResult()">Copy to Clipboard</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global UI Functions
window.closeVisionModal = function() {
    $('#visionModal').fadeOut(200);
}

window.copyVisionResult = function() {
    const textarea = document.getElementById('visionResultText');
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            if(window.Toast) Toast.show("Copied!", "success");
            else alert("Copied!");
        });
    } catch (err) {
        document.execCommand('copy');
        if(window.Toast) Toast.show("Copied!", "success");
    }
}

// Global Request Function
window.submitVisionRequest = function() {
    // UI Transitions
    $('#visionInputSection button').prop('disabled', true).css('opacity', 0.5);
    $('#visionLoading').slideDown(200);
    $('#visionResultSection').slideUp(200); // Hide previous if any

    const instruction = $('#visionPromptInput').val();

    $.ajax({
        url: 'view_frame.php', 
        type: 'POST',
        data: {
            action: 'analyze_frame',
            frame_id: <?= $frameId ?>,
            prompt: instruction
        },
        dataType: 'json',
        success: function(response) {
            $('#visionLoading').hide();
            $('#visionInputSection button').prop('disabled', false).css('opacity', 1);

            if (response.status === 'success' && response.data && response.data.description) {
                let raw = response.data.description;
                
                // Safety Fix: Check if response is Object/Array and stringify it
                if (typeof raw === 'object') {
                    console.log("AI returned object:", raw);
                    raw = JSON.stringify(raw, null, 2);
                }

                // Cleanup
                raw = raw.replace(/```[\s\S]*?```/g, "");
                raw = raw.replace(/\*\*/g, "");
                raw = raw.replace(/^(Here is|Sure|I can|Certainly).*?:/i, "");
                raw = raw.replace(/\s+/g, ' ').trim();
                
                $('#visionResultText').val(raw);
                $('#visionResultSection').slideDown(200);
            } else {
                let msg = response.message || "Unknown error";
                if(window.Toast) Toast.show("Analysis failed: " + msg, "error");
                else alert("Error: " + msg);
            }
        },
        error: function(xhr, status, error) {
            $('#visionLoading').hide();
            $('#visionInputSection button').prop('disabled', false).css('opacity', 1);
            
            let errText = "Server error: " + error;
            if (xhr.responseJSON && xhr.responseJSON.message) errText = xhr.responseJSON.message;
            if(window.Toast) Toast.show(errText, "error");
        }
    });
}

function initializeFrameDetailsScripts() {
    // Copy ID Button
    $('.copy-frame-id-btn').off('click').on('click', function(e) {
        e.preventDefault();
        var frameId = $(this).data('frame-id');
        navigator.clipboard.writeText(frameId).then(function() {
             if(window.Toast) Toast.show('ID copied: ' + frameId, 'success');
        });
    });

    // Vision Eye Button: Just Opens the Modal
    $('#visionTriggerBtn').off('click').on('click', function(e) {
        e.preventDefault();
        // Reset modal state
        $('#visionLoading').hide();
        $('#visionResultSection').hide();
        $('#visionInputSection button').prop('disabled', false).css('opacity', 1);
        $('#visionModal').fadeIn(200);
    });
    
    // Background click close
    $(window).click(function(event) {
        if (event.target == document.getElementById('visionModal')) {
            window.closeVisionModal();
        }
    });
}
</script>

<?php
        $content = ob_get_clean(); 
        return $content;
    }
}
?>
