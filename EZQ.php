<?php

class EZQ
{
	CONST  MEMORY_PREFIX = 'EZQCACHE_';

	public $jobs_queue = array();
	public $max_number_of_workers = 4;
	public $time_start;
	public $events = array();
	public $parent_pid = null;


	function __construct($max_number_of_workers=null)
	{
		if(!is_null($max_number_of_workers))
			$this->max_number_of_workers = $max_number_of_workers;
	}

	function __destruct()
	{
		try {
				$this->shutdown();
		} catch (\Exception $exception) {
				trigger_error($exception->getMessage(), E_USER_ERROR);
		}
	}

	public function shutdown()
	{
		if ($this->parent_pid > 0) {
			posix_kill($this->parent_pid, SIGKILL);
		}	

		$this->parent_pid = null;
	}

	public function addJob(Closure $job)
	{
		$this->jobs_queue[] = $job;
	}

	public function run($time_start=null, $all_pids = array())
	{	
		if(is_null($time_start))
			$time_start = $this->microtimeFloat();

		$pids = array();

		if(count($this->jobs_queue) == 0)
			return;

		$number_of_workers_to_run = ($this->max_number_of_workers > count($this->jobs_queue))
			? count($this->jobs_queue)
			: $this->max_number_of_workers;

		for( $i = 0; $i < $number_of_workers_to_run; $i++ ) {
			$job = array_shift($this->jobs_queue);
			$pids[$i] = pcntl_fork();
			if(!$pids[$i]) {
				//This is the child process
				$job_result = $job();
				$this->write( getmypid(), $job_result );
				$this->emit('job.complete', $job_result);
				exit(1);
			}
		}

		//This is the parent process
		$this->parent_pid = getmypid();

		for( $cnt = 0; $cnt < count($pids); $cnt++ ) {
			$all_pids[] = $pids[$cnt];
			pcntl_waitpid($pids[$cnt], $status, WUNTRACED);
		}
		
		if( count($this->jobs_queue) > 0 ) {
			$this->run($time_start, $all_pids);
			return;
		}
		

		$this->emit( 'q.complete', $this->read($all_pids, true), ( $this->microtimeFloat() - $time_start ) );
		return true;
	}

	protected function microtimeFloat()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	protected function emit($event_name, $result=null, $process_time=null)
	{
		if( isset($this->events[$event_name]) ) {
			foreach( $this->events[$event_name] as $listener ) {
				if( is_null($process_time) ) {
					$listener($result);
				} else {
					$listener($result, $process_time);
				}
			}
		}
	}	

	public function on($event_name, $listener)
	{
		if( !isset($this->events[$event_name]) )
			$this->events[$event_name] = array();
		$this->events[$event_name][] = $listener;
	}

	protected function read($pids, $delete=false)
	{
		$results = array();

		if(!is_array($pids))
			$pids = array($pids);

		foreach( $pids as $pid ) {
			$results[] = unserialize( file_get_contents( $this->getMemoryLocation($pid) ) );
			if($delete) {
				$this->deleteMemory($pid);
			}
		}

		return $results;
	}

	protected function write($pid, $result)
	{
		file_put_contents( $this->getMemoryLocation($pid), serialize($result) );
	}

	protected function getMemoryLocation($pid)
	{
		return sys_get_temp_dir() . '/' . self::MEMORY_PREFIX . "$pid.txt";
	}

	protected function deleteMemory($pid)
	{
		unlink( $this->getMemoryLocation($pid) );
	}

}

