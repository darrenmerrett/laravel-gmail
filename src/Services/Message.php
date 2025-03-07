<?php

namespace DarrenMerrett\LaravelGmail\Services;

use DarrenMerrett\LaravelGmail\LaravelGmailClass;
use DarrenMerrett\LaravelGmail\Services\Message\Mail;
use DarrenMerrett\LaravelGmail\Traits\Filterable;
use DarrenMerrett\LaravelGmail\Traits\SendsParameters;
use Google_Service_Gmail;
use Google\Service\Exception as GoogleException;

class Message
{
	use SendsParameters;

	use Filterable;

	public $service;

	public $preload = false;

	public $pageToken;

	public $client;

	private $resultSizeEstimate;

	/**
	 * Optional parameter for getting single and multiple emails
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * Message constructor.
	 *
	 * @param LaravelGmailClass $client
	 */
	public function __construct(LaravelGmailClass $client)
	{
		$this->client = $client;
		$this->service = new Google_Service_Gmail($client);
	}

	/**
	 * Returns next page if available of messages or an empty collection
	 *
	 * @return \Illuminate\Support\Collection
	 * @throws \Google_Exception
	 */
	public function next()
	{
		if ($this->pageToken) {
			return $this->all($this->pageToken);
		} else {
			return new MessageCollection([], $this);
		}
	}

	/**
	 * Returns a collection of Mail instances
	 *
	 * @param string|null $pageToken
	 *
	 * @return \Illuminate\Support\Collection
	 * @throws \Google_Exception
	 */
	public function all(string $pageToken = null)
	{
		if (!\is_null($pageToken)) {
			$this->add($pageToken, 'pageToken');
		}

		$mails = [];
		$response = $this->getMessagesResponse();
		$this->pageToken = method_exists($response, 'getNextPageToken') ? $response->getNextPageToken() : null;

		$this->setResultSizeEstimate($response->getResultSizeEstimate());

		$messages = $response->getMessages();

		if (!$this->preload) {
			foreach ($messages as $message) {
				$mails[] = new Mail($message, $this->preload, $this->client->userId);
			}
		} else {
			$mails = \count($messages) > 0 ? $this->batchRequest($messages) : [];
		}

		return new MessageCollection($mails, $this);
	}

	/**
	 * Returns boolean if the page token variable is null or not
	 *
	 * @return bool
	 */
	public function hasNextPage()
	{
		return !!$this->pageToken;
	}

	/**
	 * Limit the messages coming from the queryxw
	 *
	 * @param int $number
	 *
	 * @return Message
	 */
	public function take($number)
	{
		$this->params['maxResults'] = abs((int)$number);

		return $this;
	}

	/**
	 * @param $id
	 *
	 * @return Mail
	 */
	public function get($id)
	{
		$message = $this->getRequest($id);

		return new Mail($message, false, $this->client->userId);
	}

	/**
	 * Creates a batch request to get all emails in a single call
	 *
	 * @param $allMessages
	 *
	 * @return array|null
	 */
	public function batchRequest($allMessages)
	{
		$this->client->setUseBatch(true);

		$batch = null;

		$c = 0;
		$messages = [];

		foreach ($allMessages as $key => $message) {
			if (!$batch) {
				$batch = $this->service->createBatch();
			}
			$batch->add($this->getRequest($message->getId()), $key);
			$c++;

			if ($c / 20 === \intval($c / 20)) {
				$messagesBatch = $batch->execute();

				foreach ($messagesBatch as $message) {
					if ($message instanceof GoogleException) {
						app('log')->error(print_r($message->getErrors(), true));
						foreach ($messagesBatch as $k => $message) {
							app('log')->error($k.' '.\get_class($message));
						}
					}
					$messages[] = new Mail($message, false, $this->client->userId);
				}
				$batch = null;
			}
		}

		if ($batch) {
			$messagesBatch = $batch->execute();
			foreach ($messagesBatch as $message) {
				$messages[] = new Mail($message, false, $this->client->userId);
			}
		}

		$this->client->setUseBatch(false);

		return $messages;
	}

	/**
	 * Preload the information on each Mail objects.
	 * If is not preload you will have to call the load method from the Mail class
	 * @return $this
	 * @see Mail::load()
	 *
	 */
	public function preload()
	{
		$this->preload = true;

		return $this;
	}

	public function getUser()
	{
		return $this->client->user();
	}

	/**
	 * @param $id
	 *
	 * @return \Google_Service_Gmail_Message
	 */
	private function getRequest($id)
	{
		return $this->service->users_messages->get('me', $id);
	}

	/**
	 * @return \Google_Service_Gmail_ListMessagesResponse|object
	 * @throws \Google_Exception
	 */
	private function getMessagesResponse()
	{
		$responseOrRequest = $this->service->users_messages->listUsersMessages('me', $this->params);

		if (\get_class($responseOrRequest) === "GuzzleHttp\Psr7\Request") {
			$response = $this->service->getClient()->execute(
				$responseOrRequest,
				'Google_Service_Gmail_ListMessagesResponse'
			);

			return $response;
		}

		return $responseOrRequest;
	}

	public function getResultSizeEstimate()
	{
		return $this->resultSizeEstimate;
	}

	private function setResultSizeEstimate(int $resultSizeEstimate): void
	{
		$this->resultSizeEstimate = $resultSizeEstimate;
	}
}
