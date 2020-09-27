<?php
namespace classes;

class StackPool {
	protected $pool = []; # keys = clients' Ids

	public function addMessage(Message $message) {
		$stack =
			$this->checkStackExists($message->clientId)
			?
			$this->getStackForClient($message->clientId)
			:
			$this->createStackForClient($message->clientId)
		;
		$stack->addMessage($message);
	}

	/**
	 * @param $clientId
	 * @return bool|Message
	 */
	public function getMessage($clientId) {
		$result = false;
		if ($this->checkStackExists($clientId)) {
			$stack = $this->getStackForClient($clientId);
			$result = $stack->getMessage();
			if (!$stack->count()) {
				$this->removeStackForClient($clientId);
			}
		}
		return $result;
	}

	public function count() {
		return count($this->pool);
	}

	public function getStackIds() {
		return array_keys($this->pool);
	}

	/**
	 * @param $clientId
	 * @return Queue
	 */
	protected function getStackForClient($clientId) {
		return $this->pool[$clientId];
	}

	/**
	 * @param $clientId
	 * @return Queue
	 */
	protected function createStackForClient($clientId) {
		$this->pool[$clientId] = new Queue();
		return $this->pool[$clientId];
	}

	public function checkStackExists($clientId) {
		return array_key_exists($clientId, $this->pool);
	}

	protected function removeStackForClient($clientId) {
		unset($this->pool[$clientId]);
	}
}