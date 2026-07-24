<?php

declare(strict_types=1);

namespace Yard\Warden;

use Yard\Warden\LimitLogin\Limiter;
use Yard\Warden\LimitLogin\LimitLoginAdminPage;
use Yard\Warden\LimitLogin\LimitLoginServiceProvider;
use Yard\Warden\Login\LoginServiceProvider;
use Yard\Warden\Login\LostPasswordServiceProvider;
use Yard\Warden\Onboarding\OnboardingServiceProvider;
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
		self::registerPasswordProviders();
		self::registerLoginProviders();
		self::registerOnboardingProviders();
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
