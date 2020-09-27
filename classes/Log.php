<?php
namespace classes;

trait Log {
	protected $log_file;

	public function log($level, $message, $flags = FILE_APPEND) {
		file_put_contents(
			$this->log_file,
			(new \DateTime())->format('Y-m-d H:i:s.u')."\n".str_repeat("\t", $level).$message."\n",
			$flags
		);
	}
}