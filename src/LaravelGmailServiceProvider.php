<?php

namespace Dacastro4\LaravelGmail;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class LaravelGmailServiceProvider extends ServiceProvider
{

	public function boot()
	{
		$this->publishes([__DIR__ . '/config/gmail.php' => base_path('config/gmail.php')]);
	}

	public function register()
	{

		$this->mergeConfigFrom(__DIR__ . '/config/gmail.php', 'gmail');

		$this->app->configure('gmail');

		// Main Service
		$this->app->bind('laravelgmail', function ($app) {
			return new LaravelGmailClass($app['config']);
		});

	}
}
