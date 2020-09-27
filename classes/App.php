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
	protected $pool;

	/**
	 * App constructor.
	 * @param int $CLIENTS_COUNT
	 * @param int $MAX_MESSAGES
	 * @param int $CLIENT_MESSAGES_MAX_BATCH_SIZE
	 * @param int $MAX_PROCS
	 */
	public function __construct(
		$CLIENTS_COUNT = 10,
		$MAX_MESSAGES = 100,
		$CLIENT_MESSAGES_MAX_BATCH_SIZE = 5,
		$MAX_PROCS = 5
	) {
		$this->CLIENTS_COUNT = $CLIENTS_COUNT;
		$this->MAX_MESSAGES = $MAX_MESSAGES;
		$this->CLIENT_MESSAGES_MAX_BATCH_SIZE = $CLIENT_MESSAGES_MAX_BATCH_SIZE;
		$this->MAX_PROCS = $MAX_PROCS;
	}

	public function run() {
		$this->fillQueue();
		$this->processQueue();
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

	public function processQueue() {
		$this->pool = new ProcessPool($this->MAX_PROCS);
		while ($this->messages_queue->count() > 0) {
			$message = $this->messages_queue->getMessage();
			echo 'Processing message: '.json_encode($message)."\n";
			while (!$this->pool->processMessage($message)) {
				$interval = 500;
				$this->pool->log(1,'Waiting for '.$interval.' ms for the next try');
				if ($interval) {
					usleep($interval * 1000);
				}
			}
		}
		$this->pool->cleanAllSlots(true);
	}
}