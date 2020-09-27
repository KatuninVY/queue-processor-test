<?php
namespace classes;

class ProcessPool {
	use Log;

	protected $pool = []; # keys = clients' Ids
	protected $MAX_COUNT;
	protected $clean_interval; # in ms
	#public const PROCESS_MESSAGE_RESULT_UNKNOWN = -1;
	public const PROCESS_MESSAGE_RESULT_SUCCESS = 0;
	public const PROCESS_MESSAGE_RESULT_POOL_FULL = 1;
	public const PROCESS_MESSAGE_RESULT_HAS_PROCESS_FOR_CLIENT = 2;

	/**
	 * ProcessPool constructor.
	 * @param int $MAX_PROCS
	 * @param int $clean_interval
	 * @param string $log_file
	 */
	public function __construct(int $MAX_PROCS, $clean_interval, $log_file = './pool.log') {
		$this->MAX_COUNT = $MAX_PROCS;
		$this->clean_interval = $clean_interval;
		$this->log_file = $log_file;
	}

	public function count() {
		return count($this->pool);
	}

	public function processMessage(Message $message) {
		$this->log(0,'Processing message: '.json_encode($message));
		$result = $this->canProcessMessageForClient($message->clientId);
		if ($result === self::PROCESS_MESSAGE_RESULT_SUCCESS) {
			if ($this->addProcessSlot($message)) {
				$result = self::PROCESS_MESSAGE_RESULT_SUCCESS;
				$this->log(1, 'Process slot created, process started');
			}
		}
		return $result;
	}

	public function canProcessMessageForClient($clientId) {
		$this->log(1,'Checking pool for client '.$clientId);
		if (array_key_exists($clientId, $this->pool)) {
			$this->log(2, 'Already have slot with process for clientId '.$clientId.', trying to clean');
			$this->checkAndCleanSlot($clientId);
		}
		if (
			!array_key_exists($clientId, $this->pool)
			&& ($this->count() == $this->MAX_COUNT)
		) {
			$this->log(2, 'No available slots found, trying to clean all');
			$this->cleanAllSlots();
		}
		switch(true) {
			case array_key_exists($clientId, $this->pool):
				$result = self::PROCESS_MESSAGE_RESULT_HAS_PROCESS_FOR_CLIENT;
				$description = 'Process slot for client '.$clientId.' is busy';
				break;
			case $this->count() == $this->MAX_COUNT:
				$result = self::PROCESS_MESSAGE_RESULT_POOL_FULL;
				$description = 'Process pool is full';
				break;
			default:
				$description = 'Found free process slot';
				$result = self::PROCESS_MESSAGE_RESULT_SUCCESS;
		}
		$this->log(2, $description);
		return $result;
	}

	protected function addProcessSlot(Message $message) {
		$result = false;
		$this->log(1, 'Creating new process');
		try {
			$this->pool[$message->clientId] = new Process($message);
			$result = true;
		}
		catch (\Exception $e) {}
		return $result;
	}

	protected function checkAndCleanSlot($clientId) {
		$result = false;
		/** @var Process $process */
		$process = array_key_exists($clientId, $this->pool) ? $this->pool[$clientId] : false;
		if (!empty($process)) {
			$state = proc_get_status($process->pid);
			if ($state['running'] === false) {
				$this->removeProcessSlot($clientId);
				$result = true;
			}
		}
		else {
			$this->removeProcessSlot($clientId);
			$result = $this->count() < $this->MAX_COUNT;
		}
		return $result;
	}

	public function cleanAllSlots($wait = false) {
		if ($wait) {
			$this->log(1, 'Trying to clean all process slots');
		}
		/** @var Process $process */
		foreach ($this->pool as $clientId => $process) {
			if (!empty($process)) {
				$state = proc_get_status($process->pid);
				if ($state['running'] === false) {
					$this->removeProcessSlot($clientId);
				}
			}
			else {
				unset($this->pool[$clientId]);
			}
		}
		if ($wait && $this->count()) {
			$this->log(1, 'Found '.$this->count().' working processes, waiting for '.$this->clean_interval.' ms for the next try ...');
			usleep($this->clean_interval * 1000);
			$this->cleanAllSlots($wait);
		}
	}

	protected function removeProcessSlot($clientId) {
		/** @var Process $process */
		$process = $this->pool[$clientId];
		if ($process) {
			$process->close();
			$this->log(2, 'Process for client '.$clientId.' terminated');
		}
		if (array_key_exists($clientId, $this->pool)) {
			unset($this->pool[$clientId]);
			$this->log(2, 'Process slot for client '.$clientId.' cleaned');
		}
	}
}