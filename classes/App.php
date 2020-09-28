<?php
namespace classes;

use Exception;

class App {

	protected $CLIENTS_COUNT;
	protected $MAX_MESSAGES;
	protected $CLIENT_MESSAGES_MAX_BATCH_SIZE;
	protected $MAX_PROCS;
	/**
	 * @var Queue
	 */
	protected $messages_queue;
	/**
	 * @var ProcessPool
	 */
	protected $processPool;
	/**
	 * @var StackPool
	 */
	protected $stackPool;
	protected $processing_interval; # in ms
	protected $clean_interval; # in ms

	/**
	 * App constructor.
	 * @param int $CLIENTS_COUNT
	 * @param int $MAX_MESSAGES
	 * @param int $CLIENT_MESSAGES_MAX_BATCH_SIZE
	 * @param int $MAX_PROCS
	 * @param int $processing_interval
	 * @param int $clean_interval
	 */
	public function __construct(
		$CLIENTS_COUNT = 10,
		$MAX_MESSAGES = 100,
		$CLIENT_MESSAGES_MAX_BATCH_SIZE = 5,
		$MAX_PROCS = 5,
		$processing_interval = 0,
		$clean_interval = 100
	) {
		$this->CLIENTS_COUNT = $CLIENTS_COUNT;
		$this->MAX_MESSAGES = $MAX_MESSAGES;
		$this->CLIENT_MESSAGES_MAX_BATCH_SIZE = $CLIENT_MESSAGES_MAX_BATCH_SIZE;
		$this->MAX_PROCS = $MAX_PROCS;
		$this->processing_interval = $processing_interval;
		$this->clean_interval = $clean_interval;
	}

	public function run() {
		$this->fillQueue();
		$this->process();
	}

	public function fillQueue() {
		$this->messages_queue = new Queue();
		$clientIdx = 0; # first = 1, no clients array, just numbers
		$cnt = 0;
		do {
			$clientIdx += 1 - (($clientIdx < $this->CLIENTS_COUNT) ? 0 : $clientIdx); # get next client; go to 1 from last
			try {
				$batch_size = random_int(1, $this->CLIENT_MESSAGES_MAX_BATCH_SIZE);
			} catch (Exception $e) {
				$batch_size = 1;
			}
			if ($cnt + $batch_size > $this->MAX_MESSAGES) {
				$batch_size = $this->MAX_MESSAGES - $cnt;
			}
			for ($i = 1; $i <= $batch_size; ++$i) {
				$this->messages_queue->addMessage(new Message(
					$clientIdx,
					microtime(true),
					'message ' . ($cnt + 1)
				));
				$cnt = $this->messages_queue->count();
			}
		} while ($cnt < $this->MAX_MESSAGES);
		$this->messages_queue->log_queue();
	}

	public function process() {
		$this->processPool = new ProcessPool($this->MAX_PROCS, $this->clean_interval);
		$this->stackPool = new StackPool();
		$this->processPool->log(0, 'Start', 0x0);
		while ($this->messages_queue->count()) {
			$this->processQueue();
		}
		while ($this->stackPool->count()) {
			$this->processStackPool();
		}
		$this->processPool->cleanAllSlots(true);
	}

	protected function processQueue(){
		$message = $this->messages_queue->getMessage();
		static::say('Processing message from queue: ' . json_encode($message) . "\n");
		if ($this->stackPool->checkStackExists($message->clientId)) { # found stack for client
			$this->processPool->log(1, 'Found stack for client ' . $message->clientId);
			$this->stackPool->addMessage($message);
			static::say("\tMoved message to stack\n");
			$this->processPool->log(1, 'Moved message to stack');
			if ($this->processPool->canProcessMessageForClient($message->clientId) === $this->processPool::PROCESS_MESSAGE_RESULT_SUCCESS) {
				$this->processPool->log(1, 'Found completed process slot for client ' . $message->clientId);
				$message = $this->stackPool->getMessage($message->clientId);
				static::say('Processing message from stack: ' . json_encode($message) . "\n");
			}
			else {
				return;
			}
		}
		else { # no stack for client
			if ($this->processPool->canProcessMessageForClient($message->clientId) === $this->processPool::PROCESS_MESSAGE_RESULT_HAS_PROCESS_FOR_CLIENT) {
				$this->stackPool->addMessage($message);
				static::say("\tMoved message to stack\n");
				return;
			}
		}
		while ($this->processPool->processMessage($message) !== $this->processPool::PROCESS_MESSAGE_RESULT_SUCCESS) {
			if ($this->stackPool->count()) {
				$this->processStackPool();
			}
		}
	}

	protected function processStackPool() {
		if ($this->processing_interval) {
			$this->processPool->log(1, 'Waiting for ' . $this->processing_interval . ' ms for the next try');
			usleep($this->processing_interval * 1000);
		}
		$stackIds = $this->stackPool->getStackIds();
		$this->processPool->log(0, 'Processing stack: ' . json_encode($stackIds));
		foreach ($stackIds as $clientId) {
			if ($this->processPool->canProcessMessageForClient($clientId) === $this->processPool::PROCESS_MESSAGE_RESULT_SUCCESS) {
				$message = $this->stackPool->getMessage($clientId);
				static::say('Processing message from stack: ' . json_encode($message) . "\n");
				$this->processPool->processMessage($message);
			}
		}
	}

	public function say($msg) {
		echo $msg;
	}
}