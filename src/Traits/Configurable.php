<?php

namespace DarrenMerrett\LaravelGmail\Traits;

use DarrenMerrett\LaravelGmail\gmail_tokens;
use Google_Service_Gmail;
use Illuminate\Cache\Repository;
use Illuminate\Support\Arr;

/**
 * Trait Configurable
 * @package Dacastro4\LaravelGmail\Traits
 */
trait Configurable
{
	protected $additionalScopes = [];
	private $_config;

	private $fetchedToken;

	public function __construct($config)
	{
		$this->_config = $config;
	}

	public function config($string = null)
	{
		if ($config = $this->getAccessTokenFromCache()) {
			if ($string) {
				if (isset($config[$string])) {
					return $config[$string];
				}
			} else {
				return $config;
			}
		}

		return null;
	}

	protected function getAccessTokenFromCache(): ?array
	{
		if ($this->fetchedToken) {
			return $this->fetchedToken;
		}

		if ($this->fetchedToken = gmail_tokens::where('userId', $this->userId)->first()) {
			$this->fetchedToken = $this->fetchedToken->toArray();
		}

		return $this->fetchedToken;
	}

	protected function saveAccessTokenInCache(array $config): void
	{
		$db = gmail_tokens::firstOrNew(['userId' => $this->userId]);
		$db->fill($config);
		$db->userId = $this->userId;
		$db->save();

		$this->fetchedToken = $db->toArray();
	}

	protected function deleteAccessTokenFromCache(): void
	{
		gmail_tokens::where('userId', $this->userId)->delete();
	}

	private function getFileName()
	{
		if (property_exists(\get_class($this), 'userId') && $this->userId) {
			$userId = $this->userId;
		} elseif (auth()->user()) {
			$userId = auth()->user()->id;
		}

		$credentialFilename = Arr::get($this->_config, 'gmail.cache.prefix', '').'-'.$this->_config['gmail.credentials_file_name'];
		$allowMultipleCredentials = $this->_config['gmail.allow_multiple_credentials'];

		if (isset($userId) && $allowMultipleCredentials) {
			return sprintf('%s-%s', $credentialFilename, $userId);
		}

		return $credentialFilename;
	}

	/**
	 * @return array
	 */
	public function getConfigs()
	{
		return [
			'client_secret' => $this->_config['gmail.client_secret'],
			'client_id' => $this->_config['gmail.client_id'],
			'redirect_uri' => url($this->_config['gmail.redirect_url']),
			'state' => isset($this->_config['gmail.state']) ? $this->_config['gmail.state'] : null,
		];
	}

	public function setAdditionalScopes(array $scopes)
	{
		$this->additionalScopes = $scopes;

		return $this;
	}

	private function configApi()
	{
		$type = $this->_config['gmail.access_type'];
		$approval_prompt = $this->_config['gmail.approval_prompt'];

		$this->setScopes($this->getUserScopes());

		$this->setAccessType($type);

		$this->setApprovalPrompt($approval_prompt);
	}

	abstract public function setScopes($scopes);

	private function getUserScopes()
	{
		return $this->mapScopes();
	}

	private function getCacheStore(): Repository
	{
		$c = app('cache');
		if ($store = Arr::get($this->_config, 'gmail.cache.store')) {
			$c = $c->store($store);
		}
		return $c;
	}

	private function mapScopes()
	{
		$scopes = array_merge($this->_config['gmail.scopes'] ?? [], $this->additionalScopes);
		$scopes = array_unique(array_filter($scopes));
		$mappedScopes = [];

		if (!empty($scopes)) {
			foreach ($scopes as $scope) {
				$mappedScopes[] = $this->scopeMap($scope);
			}
		}

		return array_merge($mappedScopes, $this->_config['gmail.additional_scopes'] ?? []);
	}

	private function scopeMap($scope)
	{
		$scopes = [
			'all' => Google_Service_Gmail::MAIL_GOOGLE_COM,
			'compose' => Google_Service_Gmail::GMAIL_COMPOSE,
			'insert' => Google_Service_Gmail::GMAIL_INSERT,
			'labels' => Google_Service_Gmail::GMAIL_LABELS,
			'metadata' => Google_Service_Gmail::GMAIL_METADATA,
			'modify' => Google_Service_Gmail::GMAIL_MODIFY,
			'readonly' => Google_Service_Gmail::GMAIL_READONLY,
			'send' => Google_Service_Gmail::GMAIL_SEND,
			'settings_basic' => Google_Service_Gmail::GMAIL_SETTINGS_BASIC,
			'settings_sharing' => Google_Service_Gmail::GMAIL_SETTINGS_SHARING,
		];

		return Arr::get($scopes, $scope);
	}

	abstract public function setAccessType($type);

	abstract public function setApprovalPrompt($approval);
}
