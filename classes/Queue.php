<?php
namespace classes;

class Queue {
	private $queue = [];

	public function count() {
		return count($this->queue);
	}

	/**
	 * @param $message Message
	 */
	public function addMessage($message) {
		$this->queue[] = $message;
	}

	/**
	 * @return Message
	 */
	public function getMessage() {
		return array_shift($this->queue[]);
	}

	public function log($file) {
		file_put_contents($file, json_encode($this->queue, JSON_PRETTY_PRINT));
	}
}