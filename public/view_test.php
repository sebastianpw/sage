<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Test View - AI Search & Character View Demo";
$content = "";

ob_start(); 
?>

<div class="view-container">

    <!-- AI-Powered Search Component (Updated with Category Selector & Send Button) -->
    <?php require 'ai_search.php'; ?>

    <div style="max-width: 900px; margin: 40px auto; padding: 30px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid #87CEEB;">
        <h2 style="margin-top: 0; color: #333; font-size: 20px; font-weight: 600;">✨ AI Search & Character View Demo</h2>
        
        <div style="margin: 20px 0;">
            <h3 style="color: #555; font-size: 16px; margin-bottom: 10px;">🔍 Try the Enhanced Search</h3>
            <p style="color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 15px;">
                The search now includes a category selector and send button. Try these examples:
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0;">
                    <strong style="color: #87CEEB; display: block; margin-bottom: 8px;">General Search</strong>
                    <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-size: 13px; display: block;">observatory frames</code>
                    <p style="font-size: 12px; color: #888; margin: 8px 0 0 0;">AI determines it's about frames</p>
                </div>
                
                <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0;">
                    <strong style="color: #87CEEB; display: block; margin-bottom: 8px;">Category: Characters</strong>
                    <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-size: 13px; display: block;">Nova</code>
                    <p style="font-size: 12px; color: #888; margin: 8px 0 0 0;">Directly searches characters</p>
                </div>
                
                <div style="background: white; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0;">
                    <strong style="color: #87CEEB; display: block; margin-bottom: 8px;">Category: Todos</strong>
                    <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-size: 13px; display: block;">implement feature</code>
                    <p style="font-size: 12px; color: #888; margin: 8px 0 0 0;">Searches todo items</p>
                </div>
            </div>
        </div>

        <div style="margin: 30px 0;">
            <h3 style="color: #555; font-size: 16px; margin-bottom: 10px;">🎭 Character Detail View</h3>
            <p style="color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 15px;">
                Characters now have a dedicated detail view showing all information and a 3-column grid of associated frames.
                Search for a character or use the demo buttons below:
            </p>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="showCharacterDetailsModal(1)" style="background: #87CEEB; color: #000; border: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer;">
                    View Character #1 (Modal)
                </button>
                <a href="view_character.php?character_id=1" style="background: #333; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block;">
                    View Character #1 (Full Page)
                </a>
            </div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h3 style="color: #555; font-size: 16px; margin-bottom: 10px;">🎨 Features</h3>
            <ul style="color: #666; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
                <li><strong>Send Button:</strong> Click the paper plane icon or press Enter to search (no more auto-search)</li>
                <li><strong>Category Filter:</strong> Pre-select entity type for faster, more accurate results</li>
                <li><strong>Smart Routing:</strong> Results link to appropriate detail views (characters, frames, etc.)</li>
                <li><strong>3-Column Grid:</strong> Character frames displayed in responsive grid layout</li>
                <li><strong>Lightbox:</strong> Click any frame for fullscreen view with link to frame details</li>
                <li><strong>Modal Support:</strong> Open character details as overlay or full page</li>
            </ul>
        </div>
    </div>

    <?php /* Keep your existing modal includes */ ?>
    <?php require 'modal_frame_details.php'; ?>
    <?php require 'modal_character_details.php'; ?>

    <div style="text-align: center; margin: 40px 0;">
        <h3 style="color: #ccc; margin-bottom: 20px;">Frame Modal Demo</h3>
        <a href="javascript:void(0);" onclick="showFrameDetailsModal(1949, 0.5);">
            <img style="width: 200px; height: 200px; border-radius: 4px; border: 2px solid #444; transition: border-color 0.2s;" 
                 src="/frames_starlightguardians_nu/frame0004455.jpg" 
                 alt="Observatory" 
                 class="frame-image"
                 onmouseover="this.style.borderColor='#87CEEB'"
                 onmouseout="this.style.borderColor='#444'">
        </a>
        <p style="color: #888; font-size: 13px; margin-top: 10px;">Click image to test frame modal</p>
    </div>

</div>

<?php          
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
