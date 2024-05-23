<?php

namespace DarrenMerrett\LaravelGmail;

use DarrenMerrett\LaravelGmail\Traits\Configurable;
use DarrenMerrett\LaravelGmail\Traits\HasLabels;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_WatchRequest;
use Illuminate\Container\Container;

class GmailConnection extends Google_Client
{
	use HasLabels;
	use Configurable {
		__construct as configConstruct;
	}


	protected $emailAddress;
	protected $refreshToken;
	protected $app;
	protected $accessToken;
	protected $token;
	private $configuration;
	public $userId;

	public function __construct($config = null, $userId = null)
	{
		$this->app = Container::getInstance();

		$this->userId = $userId;

		$this->configConstruct($config);

		$this->configuration = $config;

		parent::__construct($this->getConfigs());

		$this->configApi();

		if ($this->checkPreviouslyLoggedIn()) {
			$this->refreshTokenIfNeeded();
		}
	}

	/**
	 * Check and return true if the user has previously logged in without checking if the token needs to refresh
	 *
	 * @return bool
	 */
	public function checkPreviouslyLoggedIn()
	{
		if ($savedConfigToken = $this->getAccessTokenFromCache()) {
			return !empty($savedConfigToken['access_token']);
		}

		return false;
	}

	/**
	 * Refresh the auth token if needed
	 *
	 * @return mixed|null
	 */
	private function refreshTokenIfNeeded()
	{
		if ($this->isAccessTokenExpired()) {
			$this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
			$token = $this->getAccessToken();
			$this->setBothAccessToken($token);

			return $token;
		}

		return $this->token;
	}

	/**
	 * Check if token exists and is expired
	 * Throws an AuthException when the auth file its empty or with the wrong token
	 *
	 *
	 * @return bool Returns True if the access_token is expired.
	 */
	public function isAccessTokenExpired()
	{
		$token = $this->getToken();

		if ($token) {
			$this->setAccessToken($token);
		}

		return parent::isAccessTokenExpired();
	}

	public function getToken()
	{
		return parent::getAccessToken() ?: $this->config();
	}

	public function setToken($token)
	{
		$this->setAccessToken($token);
	}

	public function getAccessToken()
	{
		$token = parent::getAccessToken() ?: $this->config();

		return $token;
	}

	/**
	 * @param array|string $token
	 */
	public function setAccessToken($token)
	{
		parent::setAccessToken($token);
	}

	/**
	 * @param $token
	 */
	public function setBothAccessToken($token)
	{
		$this->setAccessToken($token);
		$this->saveAccessToken($token);
	}

	/**
	 * Save the credentials in a file
	 *
	 * @param array $config
	 */
	public function saveAccessToken(array $config)
	{
		$config['email'] = $this->emailAddress;

		if ($savedConfigToken = $this->getAccessTokenFromCache()) {
			if (empty($config['email'])) {
				if (isset($savedConfigToken['email'])) {
					$config['email'] = $savedConfigToken['email'];
				}
			}
		}

		if ($savedConfigToken && empty($config['refresh_token'])) {
			$config['refresh_token'] = $savedConfigToken['refresh_token'];
		}

		$this->saveAccessTokenInCache($config);
	}

	/**
	 * @return array|string
	 * @throws \Exception
	 */
	public function makeToken()
	{
		if (!$this->check()) {
			$request = app('request')->capture();
			$code = (string)$request->input('code', null);
			if (!\is_null($code) && !empty($code)) {
				$accessToken = $this->fetchAccessTokenWithAuthCode($code);
				if ($this->haveReadScope()) {
					$me = $this->getProfile();
					if (property_exists($me, 'emailAddress')) {
						$this->emailAddress = $me->emailAddress;
						$accessToken['email'] = $me->emailAddress;
					}
				}
				$this->setBothAccessToken($accessToken);

				return $accessToken;
			} else {
				throw new \Exception('No access token');
			}
		} else {
			return $this->getAccessToken();
		}
	}

	/**
	 * Check
	 *
	 * @return bool
	 */
	public function check()
	{
		return !$this->isAccessTokenExpired();
	}

	/**
	 * Gets user profile from Gmail
	 *
	 * @return \Google_Service_Gmail_Profile
	 */
	public function getProfile()
	{
		$service = new Google_Service_Gmail($this);

		return $service->users->getProfile('me');
	}

	/**
	 * Revokes user's permission and logs them out
	 */
	public function logout()
	{
		$this->revokeToken();
	}

	private function haveReadScope()
	{
		$scopes = $this->getUserScopes();

		return \in_array(Google_Service_Gmail::GMAIL_READONLY, $scopes);
	}

	/**
	 * users.stop receiving push notifications for the given user mailbox.
	 *
	 * @param string $userEmail Email address
	 * @param array $optParams
	 * @return \Google_Service_Gmail_Stop
	 */
	public function stopWatch($userEmail, $optParams = [])
	{
		$service = new Google_Service_Gmail($this);

		return $service->users->stop($userEmail, $optParams);
	}

	/**
	 * Set up or update a push notification watch on the given user mailbox.
	 *
	 * @param string $userEmail Email address
	 * @param Google_Service_Gmail_WatchRequest $postData
	 *
	 * @return \Google_Service_Gmail_WatchResponse
	 */
	public function setWatch($userEmail, \Google_Service_Gmail_WatchRequest $postData): \Google_Service_Gmail_WatchResponse
	{
		$service = new Google_Service_Gmail($this);

		return $service->users->watch($userEmail, $postData);
	}

	/**
	 * Lists the history of all changes to the given mailbox. History results are returned in chronological order (increasing historyId).
	 * @param $userEmail
	 * @param $params
	 * @return \Google\Service\Gmail\ListHistoryResponse
	 */
	public function historyList($userEmail, $params)
	{
		$service = new Google_Service_Gmail($this);

		return $service->users_history->listUsersHistory($userEmail, $params);
	}
}
