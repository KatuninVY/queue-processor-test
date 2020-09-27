<?php
namespace classes;

class Queue {
	use Log;

	private $queue = [];

	/**
	 * Queue constructor.
	 * @param $log_file
	 */
	public function __construct($log_file = './queue.log') {
		$this->log_file = $log_file;
	}

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
		return array_shift($this->queue);
	}

	public function log_queue() {
		$this->log(0, json_encode($this->queue, JSON_PRETTY_PRINT), 0x0);
	}
}