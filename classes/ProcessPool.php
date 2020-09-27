<?php
namespace classes;

class ProcessPool {
	use Log;

	protected $pool = []; # keys = clients' Ids
	protected $MAX_PROCS;

	/**
	 * ProcessPool constructor.
	 * @param int $MAX_PROCS
	 * @param string $log_file
	 */
	public function __construct(int $MAX_PROCS, $log_file = './pool.log') {
		$this->MAX_PROCS = $MAX_PROCS;
		$this->log_file = $log_file;
	}

	public function count() {
		return count($this->pool);
	}

	public function processMessage(Message $message) {
		$result = false;
		$this->log(0,'Processing message: '.json_encode($message));
		if ($this->canProcessMessage($message)) {
			if ($this->addProcessSlot($message)) {
				$result = true;
			}
		}
		$this->log(1,$result ? 'Message processing started OK' : 'Cannot process message');
		return $result;
	}

	protected function canProcessMessage(Message $message) {
		$this->log(1,'Checking pool');
		if (array_key_exists($message->clientId, $this->pool)) {
			$this->log(2, 'Already have slot with process for clientId '.$message->clientId.', trying to clean');
			$this->checkAndCleanSlot($message->clientId);
		}
		if (
			!array_key_exists($message->clientId, $this->pool)
			&& ($this->count() == $this->MAX_PROCS)
		) {
			$this->log(2, 'No available slots found, trying to clean all');
			$this->cleanAllSlots();
		}
		$result =
			!array_key_exists($message->clientId, $this->pool)
			&&
			($this->count() < $this->MAX_PROCS)
		;
		$this->log(2, $result ? 'Found free process slot' : 'No available slots found');
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
		/** @var Process $process */
		$process = $this->pool[$clientId];
		if (!empty($process)) {
			$state = proc_get_status($process->pid);
			if ($state['running'] === false) {
				$this->removeProcessSlot($clientId);
			}
		}
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
			$interval = 1;
			$this->log(1, 'Found '.$this->count().' working processes, waiting for '.$interval.' seconds for next try ...');
			sleep(1);
			$this->cleanAllSlots($wait);
		}
	}

	protected function removeProcessSlot($clientId) {
		/** @var Process $process */
		$process = $this->pool[$clientId];
		$process->close();
		unset($this->pool[$clientId]);
	}
}