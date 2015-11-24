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
		public $lastUpdatePidFileTime = 0;

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
			$th->pcntl_signal_dispatch__();
			$th->pcntl_signal__(SIGTERM, [$th, 'stopDaemon']);

			$th->pid = $th->posix_getpid__();

			if (file_exists($th->pidFile))
				throw new \Exception("Process already exists: " . @abs(@$th->file_get_contents__($th->pidFile)));

			if ($th->runAsDaemon)
				$th->pcntlFork();

			$th->file_put_contents__($th->pidFile, $th->pid);
		}

		public function pcntlFork()
		{
			$th = $this->proxyThis();

			$childPid = $th->pcntl_fork__();
			if ($childPid) exit();

			$th->posix_setsid__();

			$th->pid = $th->posix_getpid__();
		}

		public function stopDaemon()
		{
			$th = $this->proxyThis();

			$str = PHP_EOL . 'SIGTERM SIGNAL ACCEPTED: ' . time() . PHP_EOL;

			echo $str;

			$th->stop = true;
			$th->unlink__($th->pidFile);
		}

		public function collectCycles()
		{
			$th = $this->proxyThis();

			$last = abs(time() - $th->lastCollectCyclesTime);

			if ($last < self::CYCLES_CHECK_INTERVAL_SEC)
				return;

			$th->gc_collect_cycles__();

			$th->lastCollectCyclesTime = $th->time__();
		}

		public function pidFileIsMyhek()
		{
			$th = $this->proxyThis();

			if (!$th->file_exists__($th->pidFile))
				return true;

			$pidFromFile = @abs(@$th->file_put_contents__($th->pidFile));
			$res = $pidFromFile === $th->pid;

			return $res;
		}

		public function updatePidFile()
		{
			$th = $this->proxyThis();

			$last = abs(time() - $th->lastUpdatePidFileTime);

			if ($last < self::PID_FILE_CHECK_INTERVAL_SEC)
				return;

			if (!$th->pidFileIsMyhek())
				throw new \Exception("Wrong pid: $pidFromFile != {$th->pid}");

			$th->file_put_contents__($th->pidFile, $th->pid);

			$th->lastUpdatePidFileTime = time();
		}

		public function tick()
		{
			$th = $this->proxyThis();

			$th->collectCycles();
			$th->updatePidFile();

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