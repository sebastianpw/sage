<?php
// src/Wtf/HandlerRegistry.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Central registry mapping handler_key (string, stored in
// wtf_run_steps.handler_key) to a StepHandlerInterface instance.
//
// This is THE seam between WTF core and every other SAGE module. To wire
// in a new capability (wrish, image generation, PDF export, whatever),
// you write a class implementing StepHandlerInterface and register it
// here with a unique key. WTF core (WorkflowExecutor) never needs to
// change — it only ever calls resolve($handler_key)->execute(...).
//
// Deliberately NOT autoloading/scanning a directory: an explicit map is
// easier to audit ("what can a workflow actually do on this install?")
// and prevents a stray file from silently becoming callable.
//
// v3 CHANGE: No more constructor parameters. $pdo and $spw are NEVER
// passed through constructors anywhere in WTF/Wrish — every class that
// needs them pulls the app's single global $spw (App\Core\SpwBase
// singleton, set up once in bootstrap.php) directly, and gets $pdo from
// $spw->getPDO(). This matches how every other file in the app already
// works (see bootstrap.php / env_locals.php) and removes an entire class
// of bugs where a call site forgets to thread $spw through (this is
// exactly what happened with bash/wtf_advance_runs.php previously).
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf;

use App\Wtf\Handlers\ManualHandler;
use App\Wtf\Handlers\NoopDelayHandler;
use App\Wtf\Handlers\SchedulerTaskHandler;
use App\Wtf\Handlers\WrishRunHandler;
use App\Wtf\Handlers\SqlishRunHandler;
use App\Wtf\Handlers\SplashRunHandler;
use App\Wtf\Handlers\SubWorkflowHandler;
use App\Wtf\Handlers\ContinuityIngestHandler;

class HandlerRegistry
{
    /** @var array<string, StepHandlerInterface> */
    private array $handlers = [];

    public function __construct()
    {
        // Built-ins. These have zero external dependencies by design.
        $this->register('manual', new ManualHandler());
        $this->register('noop_delay', new NoopDelayHandler());

        // These handlers pull $spw/$pdo from the global singleton
        // internally (see their own files) — nothing to inject here.
        $this->register('scheduler_task', new SchedulerTaskHandler());
        $this->register('wrish_run', new WrishRunHandler());
        $this->register('sqlish_run', new SqlishRunHandler());
        
        $this->register('splash_run', new SplashRunHandler());
        $this->register('sub_workflow', new SubWorkflowHandler());

        $this->register('continuity_ingest', new ContinuityIngestHandler()); 
        // ── Extension point ────────────────────────────────────────────────
        // Future tool-specific handlers get registered here, e.g.:
        //   $this->register('sage_sketch_generate', new \App\Wtf\Handlers\SketchGenerateHandler());
        // Each such handler is additive — it does not require touching
        // wtf_schema.sql, WorkflowExecutor, or wtf_api.php.
    }

    public function register(string $key, StepHandlerInterface $handler): void
    {
        $this->handlers[$key] = $handler;
    }

    public function has(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    public function resolve(string $key): StepHandlerInterface
    {
        if (!isset($this->handlers[$key])) {
            throw new \RuntimeException("WTF: no handler registered for handler_key '{$key}'.");
        }
        return $this->handlers[$key];
    }

    /** @return string[] all registered handler keys, e.g. for a UI dropdown */
    public function availableKeys(): array
    {
        return array_keys($this->handlers);
    }
}


