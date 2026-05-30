<?php

require "bootstrap.php";
require "e.php";
require "VoicePool.php";

$vp = new VoicePool();

var_dump($vp->listModels());


