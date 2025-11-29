<?php
// env_locals.php
global $spw;

if (!isset($spw) || !$spw instanceof \App\Core\SpwBase) {
    require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
    global $spw;
}

// near top of env_locals.php
$GLOBALS['WORDNET_PYAPI_URL'] = getenv('WORDNET_PYAPI_URL') ?: 'http://127.0.0.1:8009';

$pdo        = $spw->getPDO();
$pdoSys     = $spw->getSysPDO();
$pdoRoot    = $spw->getRootPDO();
$pdoWN      = $spw->getWNPDO();
$mysqli     = $spw->getMysqli();
$mysqliSys  = $spw->getSysMysqli();
$dbname     = $spw->getDbName();
$sysDbName  = $spw->getSysDbName();
$fileLogger = $spw->getFileLogger();
$framesDir  = $spw->getFramesDir();
$framesDirRel = $spw->getFramesDirRel();
$projectPath   = $spw->getProjectPath();
$publicPathAbs = $projectPath . PUBLIC_PATH_REL;
