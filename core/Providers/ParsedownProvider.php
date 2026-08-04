<?php

namespace Core\Providers;

class ParsedownProvider extends Provider
{
    public function register(): void
    {
        $this->app->singleton(\Parsedown::class, function () {
            $pd = new \Parsedown();
            $pd->setSafeMode(true);
            return $pd;
        });
    }
}
