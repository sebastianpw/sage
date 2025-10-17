<?php
// env_locals.php
global $spw;

if (!isset($spw) || !$spw instanceof \App\Core\SpwBase) {
    require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
    global $spw;
}

$pdo        = $spw->getPDO();
$pdoSys     = $spw->getSysPDO();
$mysqli     = $spw->getMysqli();
$mysqliSys  = $spw->getSysMysqli();
$dbname     = $spw->getDbName();
$sysDbName  = $spw->getSysDbName();
$fileLogger = $spw->getFileLogger();
$framesDir  = $spw->getFramesDir();
$framesDirRel = $spw->getFramesDirRel();
$projectPath   = $spw->getProjectPath();
$publicPathAbs = $projectPath . PUBLIC_PATH_REL;
