<?php

declare(strict_types=1);

namespace Yard\Warden;

use Yard\Warden\Login\LoginServiceProvider;
use Yard\Warden\Login\LostPasswordServiceProvider;
use Yard\Warden\Onboarding\OnboardingServiceProvider;
use Yard\Warden\LimitLogin\Limiter;
use Yard\Warden\LimitLogin\LimitLoginAdminPage;
use Yard\Warden\LimitLogin\LimitLoginServiceProvider;
use Yard\Warden\Password\PasswordServiceProvider;
use Yard\Warden\Password\PasswordUIServiceProvider;
use Yard\Warden\Password\StrengthValidator;

/**
 * Exit when accessed directly.
 */
if (! defined('ABSPATH')) {
	exit;
}

class Bootstrap
{
	public static function bootstrap(): void
	{
		self::registerPluginTextDomain();
		self::registerPasswordProviders();
		self::registerLoginProviders();
		self::registerOnboardingProviders();
	}

	private static function registerPluginTextDomain(): void
	{
		\add_action('init', [self::class, 'loadPluginTextDomain']);
	}

	public static function loadPluginTextDomain(): void
	{
		\load_plugin_textdomain(
			YARD_WARDEN_TEXT_DOMAIN,
			false,
			YARD_WARDEN_PLUGIN_NAME . '/languages/'
		);
	}

	private static function registerPasswordProviders(): void
	{
		(new PasswordServiceProvider(new StrengthValidator()))->register();
		(new PasswordUIServiceProvider())->register();
	}

	private static function registerLoginProviders(): void
	{
		$limiter = new Limiter();

		(new LoginServiceProvider())->register();
		(new LostPasswordServiceProvider())->register();
		(new LimitLoginServiceProvider($limiter))->register();
		(new LimitLoginAdminPage($limiter))->register();
	}

	private static function registerOnboardingProviders(): void
	{
		(new OnboardingServiceProvider())->register();
	}
}
