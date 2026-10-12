<?php

namespace App\Async;

use Native\Mobile\AsyncTask;

/** Trivial task: no network, no I/O. */
class PingTask extends AsyncTask
{
    public function handle(): string
    {
        return 'ok at '.date('H:i:s');
    }
}
