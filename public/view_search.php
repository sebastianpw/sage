<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Test View - AI Search Demo";
$content = "";

ob_start(); 
?>

<div class="view-container">

    <!-- AI-Powered Search Component -->
    <?php require 'ai_search.php'; ?>

    <div style="max-width: 800px; margin: 40px auto; padding: 20px; background: #f9f9f9; border-radius: 4px;">
        <h2 style="margin-top: 0; color: #333; font-size: 18px; font-weight: 500;">AI Search Demo</h2>
        <p style="color: #666; font-size: 14px; line-height: 1.6;">
            Try searching with natural language queries like:
        </p>
        <ul style="color: #666; font-size: 14px; line-height: 1.8; margin: 10px 0;">
            <li><code style="background: white; padding: 2px 6px; border-radius: 3px;">observatory frames</code></li>
            <li><code style="background: white; padding: 2px 6px; border-radius: 3px;">find frames with starlight</code></li>
            <li><code style="background: white; padding: 2px 6px; border-radius: 3px;">show my todos</code></li>
            <li><code style="background: white; padding: 2px 6px; border-radius: 3px;">search bookmarks for code</code></li>
        </ul>
        <p style="color: #666; font-size: 13px; margin-top: 15px;">
            The AI will analyze your query and intelligently determine which database tables to search.
        </p>
    </div>

    <?php /* Keep your existing modal and frame demo */ ?>
    <?php require 'modal_frame_details.php'; ?>

    <div style="text-align: center; margin: 40px 0;">
        <a href="javascript:void(0);" onclick="showFrameDetailsModal(1949,0.5);" href="view_frame.php?entity=generatives&entity_id=143&frame_id=1949">
            <img style="width: 200px; height: 200px; border-radius: 4px;" src="/frames_starlightguardians_nu/frame0004455.jpg" alt="Observatory" class="frame-image">
        </a>
        <p style="color: #666; font-size: 13px; margin-top: 10px;">Click image to test frame modal</p>
    </div>

</div>

<?php          
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
