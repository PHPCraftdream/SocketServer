<?
	namespace PHPCraftdream\SocketServer;

	class Daemon extends \PHPCraftdream\TotalTest\CoreObject
	{
		use \PHPCraftdream\Events\traits\Events;

		public $runAsDaemon = true;
		public $stop = false;
		public $pid;
		public $pidFile;
		public $pidFileModTime;
		public $lastCollectCyclesTime = 0;

		const CYCLES_CHECK_INTERVAL_SEC = 30;
		const PID_FILE_CHECK_INTERVAL_SEC = 30;

		function __construct($pidFile)
		{
			$this->init($pidFile);
		}

		public function init($pidFile)
		{
			$th = $this->proxyThis();

			$th->gc_disable__();

			$th->pidFile = $pidFile;

			$th->initDaemon();
		}

		public function initDaemon()
		{
			$th = $this->proxyThis();

			declare(ticks=1);
			pcntl_signal_dispatch();
			pcntl_signal(SIGTERM, [$th, 'stopDaemon']);

			$th->pid = posix_getpid();

			if (file_exists($th->pidFile))
				throw new \Exception("Process already exists: " . @abs(@file_get_contents($th->pidFile)));

			if ($th->runAsDaemon)
				$th->pcntlFork();

			file_put_contents($th->pidFile, $th->pid);
		}

		public function pcntlFork()
		{
			$th = $this->proxyThis();

			$childPid = pcntl_fork();
			if ($childPid) exit();

			posix_setsid();

			$th->pid = posix_getpid();
		}

		public function stopDaemon()
		{
			$th = $this->proxyThis();

			$str = PHP_EOL . 'SIGTERM SIGNAL ACCEPTED: ' . time() . PHP_EOL;

			echo $str;

			$th->stop = true;
			unlink($th->pidFile);
		}

		public function collectCycles()
		{
			$th = $this->proxyThis();

			$last = abs(time() - $th->lastCollectCyclesTime);

			if ($last < self::CYCLES_CHECK_INTERVAL_SEC)
				return;

			gc_collect_cycles();

			$th->lastCollectCyclesTime = time();
		}

		public function tick()
		{
			$th = $this->proxyThis();

			$th->collectCycles();

			$th->event('onTick', [$th]);
		}

		public function run()
		{
			$th = $this->proxyThis();

			while (true)
			{
				if (!empty($th->stop)) break;

				$th->tick();
			}
		}
	}