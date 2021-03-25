<?php

/*
 * This file is part of the Laravel Exchange Mail Server package.
 *
 * Copyright (c) 2021 Raju Rayhan
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Raju\EWSMail;

use Illuminate\Support\ServiceProvider;

class ExchangeMailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
                __DIR__.'/../config/ews-mail-server.php' => config_path('ews-mail-server.php')
            ], 'ewsmailserver');
    }
}