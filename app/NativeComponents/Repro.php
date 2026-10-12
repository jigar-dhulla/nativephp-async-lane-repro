<?php

namespace App\NativeComponents;

use App\Async\PingTask;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Repro extends NativeComponent
{
    public float $mountedAt = 0;

    public string $firstLine = 'waiting...';

    public string $againLine = '(not pressed yet)';

    public function mount(): void
    {
        $this->mountedAt = microtime(true);

        PingTask::dispatch()
            ->finished(function ($result) {
                $this->firstLine = 'RESULT: '.$result.' | callback after '.$this->elapsed().' ms';
            })
            ->failed(function (\Throwable $e) {
                $this->firstLine = 'FAILED: '.$e::class.' - '.$e->getMessage().' | callback after '.$this->elapsed().' ms';
            });
    }

    public function dispatchAgain(): void
    {
        $at = $this->elapsed();

        PingTask::dispatch()
            ->finished(function ($result) use ($at) {
                $this->againLine = 'RESULT: '.$result.' | dispatched '.$at.' ms after mount, callback '.$this->elapsed().' ms after mount';
            })
            ->failed(function (\Throwable $e) use ($at) {
                $this->againLine = 'FAILED: '.$e::class.' - '.$e->getMessage().' | dispatched '.$at.' ms after mount';
            });
    }

    protected function elapsed(): int
    {
        return (int) round((microtime(true) - $this->mountedAt) * 1000);
    }

    public function render(): View
    {
        return view('native.repro');
    }
}
