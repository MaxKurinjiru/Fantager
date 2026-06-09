<?php

/**
 * Cron worker entry point for shared hosting environments.
 *
 * The hosting cron runs this file every 5 minutes.
 * It invokes the Symfony Messenger worker for up to 270 seconds (4.5 min),
 * leaving a safe margin before the next cron cycle starts.
 *
 * Cron panel configuration (path to this file):
 *   /path/on/server/bin/cron_worker.php
 */

declare(strict_types=1);

chdir(dirname(__DIR__));

passthru(
    PHP_BINARY.' bin/console messenger:consume async'
    .' --limit=100'        // max messages per run (safety cap)
    .' --time-limit=270'   // stop after 270 s (cron interval is 300 s)
    .' --memory-limit=128M'
    .' -vv 2>&1',          // verbose output goes to server error log
    $exitCode
);

exit($exitCode);
