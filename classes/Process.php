<?php
namespace classes;

class Process {
	public $pid;
	/**
	 * @var Message
	 */
	public $message;

	public function __construct(Message $message) {
		$this->message = $message;
		$this->pid = static::run();
	}

	private static function run() {
		return proc_open(static::createCommand(), [], $pipes);
	}

	private static function createCommand() {
		return $command = 'php -r "sleep(1);"';
	}

	public function close() {
		proc_close($this->pid);
	}
}