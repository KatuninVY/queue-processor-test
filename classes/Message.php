<?php
namespace classes;

class Message {

	public $clientId;
	public $time;
	public $text;

	/**
	 * Message constructor.
	 * @param $clientId
	 * @param $time
	 * @param $text
	 */
	public function __construct($clientId, $time, $text = '') {
		$this->clientId = $clientId;
		$this->time = $time;
		$this->text = $text;
	}
}