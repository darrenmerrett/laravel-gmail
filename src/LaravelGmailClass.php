<?php

namespace DarrenMerrett\LaravelGmail;

use DarrenMerrett\LaravelGmail\Exceptions\AuthException;
use DarrenMerrett\LaravelGmail\Services\Message;
use DarrenMerrett\LaravelGmail\Services\Message\Mail;
use Illuminate\Support\Facades\Redirect;

class LaravelGmailClass extends GmailConnection
{
	public function __construct($config, $userId = null)
	{
		if (!\is_array($config) && class_basename($config) === 'Application') {
			$config = $config['config'];
		}

		parent::__construct($config, $userId);
	}

	/**
	 * @return Message
	 * @throws AuthException
	 */
	public function message()
	{
		if (!$this->getToken()) {
			throw new AuthException('No credentials found.');
		}

		return new Message($this);
	}

	public function mail()
	{
		if (!$this->getToken()) {
			throw new AuthException('No credentials found.');
		}

		return new Mail();
	}

	/**
	 * Returns the Gmail user email
	 *
	 * @return \Google_Service_Gmail_Profile
	 */
	public function user()
	{
		return $this->config('email');
	}

	/**
	 * Updates / sets the current userId for the service
	 *
	 * @return \Google_Service_Gmail_Profile
	 */
	public function setUserId($userId)
	{
		$this->userId = $userId;
		return $this;
	}

	public function redirect()
	{
		return redirect()->to($this->getAuthUrl());
	}

	/**
	 * Gets the URL to authorize the user
	 *
	 * @return string
	 */
	public function getAuthUrl()
	{
		return $this->createAuthUrl();
	}

	public function logout()
	{
		$this->revokeToken();
		$this->deleteAccessTokenFromCache();
	}
}
