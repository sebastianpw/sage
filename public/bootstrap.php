<?php

date_default_timezone_set('UTC');
/*
curl_setopt($ch, CURLOPT_TIMEOUT, 300);          // total seconds allowed
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);   // seconds to wait for connection
 */
if (!defined('PUBLIC_PATH_REL')) {
    define('PUBLIC_PATH_REL', '/public');
}

require "error_reporting.php";
require __DIR__ . "/load_root.php"; // PROJECT_ROOT
require PROJECT_ROOT . '/vendor/autoload.php';
require "eruda_var.php";

require_once PROJECT_ROOT . PUBLIC_PATH_REL . '/AccessManager.php';

global $spw;
$spw = \App\Core\SpwBase::getInstance();

$mysqli = $spw->getMysqli();
$pdo = $spw->getPDO();

$mysqliSys = $spw->getSysMysqli();
$pdoSys    = $spw->getSysPDO();

$pdoRoot   = $spw->getRootPDO();

$pdoWN     = $spw->getWNPDO();

$dbname     = $spw->getDbName();
$sysDbName  = $spw->getSysDbName();

$fileLogger = $spw->getFileLogger();

$framesDir = $spw->getFramesDir();
$framesDirRel = $spw->getFramesDirRel();

$projectPath = $spw->getProjectPath();

$publicPathAbs = $spw->getProjectPath() . PUBLIC_PATH_REL;

AccessManager::authenticate();


