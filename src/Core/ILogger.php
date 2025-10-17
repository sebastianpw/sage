<?php

namespace App\Core;

interface ILogger
{
    public function debug(array $data): void;
    public function info(array $data): void;
    public function warning(array $data): void;
    public function error(array $data): void;
}
