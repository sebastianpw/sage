<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'];

require __DIR__ . '/../vendor/autoload.php';

use App\Core\ChatUI;

$ui = new ChatUI($userId);

require "eruda_var.php";

// Render the chat UI
/*
$content = $eruda . \App\Core\SpwBase::getInstance()->getJquery() .  '<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;>
    <div style="position: absolute;">
        <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
        &#x1F5C3;
    </a>
    <h2 style="margin: 0; padding 0 0 20px 0; position: absolute; top: 10px; left: 50px;">AI Chat Prompts</h2>

    </div>

</div>
<div style="margin: 0; padding: 0;"> <br />  </div>
<div style="margin: 0 20px 80px 20px;">  ' . $ui->render() . '</div>';
 */
$content = $eruda . $ui->render();


$spw = \App\Core\SpwBase::getInstance();
$spw->renderLayout($content, "Chat", $spw->getProjectPath() . '/templates/chat.php');



