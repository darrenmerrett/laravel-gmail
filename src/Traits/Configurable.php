<?php

namespace Dacastro4\LaravelGmail\Traits;

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

	protected function getAccessTokenFromCache(): ?array {
		$file = $this->getFullFilePath();

		$d = $this->getCacheStore()->get($file);

		if ($d) {
			if (!$this->_config['gmail.disable_json_encrypt']) {
				$d = decrypt($d);
			}
			return json_decode($d, true);
		}

		return null;
	}

	protected function saveAccessTokenInCache(array $config): void {
		$file = $this->getFullFilePath();

		$config = json_encode($config);

		if (!$this->_config['gmail.disable_json_encrypt']) {
			$config = encrypt($config);
		}

		$this->getCacheStore()->forever($file, $config);
	}

	/**
	 * Delete the credentials in a file
	 */
	protected function deleteAccessTokenFromCache()
	{
		$this->saveAccessTokenInCache([]);
	}

	private function getFullFilePath(): string {
		$fileName = $this->getFileName();
		return "gmail/tokens/$fileName.json";
	}

	private function getFileName()
	{
		if (property_exists(get_class($this), 'userId') && $this->userId) {
			$userId = $this->userId;
		} elseif (auth()->user()) {
			$userId = auth()->user()->id;
		}

		$credentialFilename = $this->_config['gmail.credentials_file_name'];
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

	public abstract function setScopes($scopes);

	private function getUserScopes()
	{
		return $this->mapScopes();
	}

	private function getCacheStore(): Repository {
		return app('cache')->store($this->_config['tokenCacheStore']);
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

	public abstract function setAccessType($type);

	public abstract function setApprovalPrompt($approval);

}
