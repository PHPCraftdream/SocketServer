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

		function __construct($pidFile, $args)
		{
			$this->pidFile = $pidFile;

			$this->runWithArgs($args);

			$this->init($pidFile);
		}

		//--------------------------------------------------------------------------------

		public function runWithArgs($args)
		{
			$th = $this->proxyThis();

			if (!$th->array_key_exists__(1, $args))
				$args[1] = 'status';

			$th->checkRunArg($args[1]);
			$th->processArgs($args[1]);
		}

		public function checkRunArg($arg)
		{
			$th = $this->proxyThis();

			$alowed =
			[
				'start' => 1,
				'restart' => 1,
				'stop' => 1,
				'status' => 1,
			];

			if (!$th->array_key_exists__($arg, $alowed))
				throw new \Exception("Unknown arg");
		}

		public function processArgs($arg)
		{
			$th = $this->proxyThis();

			switch ($arg)
			{
				case 'start' :
				{
					return $th->daemonStart();
				}
				break;

				case 'restart' :
				{
					return $th->daemonReStart();
				}
				break;

				case 'status' :
				{
					return $th->daemonStatus();
				}
				break;

				case 'stop' :
				{
					return $th->daemonStop();
				}
				break;

				default:
				{
					throw new \Exception("Wtf? #hz");
				}
				break;
			}
		}

		public function daemonStop()
		{
			$th = $this->proxyThis();

			$th->daemonReStart();

			echo PHP_EOL . "stoped" . PHP_EOL;
			exit;
		}

		public function daemonStatus()
		{
			$th = $this->proxyThis();

			$statuses =
			[
				self::STATUS_RUN => 'Running',
				self::STATUS_STOPPED => 'Stopped'
			];

			list($filePid, $status) = $th->pidStatus();

			if ($filePid)
				echo "pid = $filePid, ";

			echo $statuses[$status] . PHP_EOL;

			exit;
		}

		public function daemonReStart()
		{
			$th = $this->proxyThis();

			list($filePid, $status) = $th->pidStatus();

			if ($status === self::STATUS_RUN)
			{
				echo "Sending SIGTERM signal to $filePid" . PHP_EOL;

				$th->posix_kill__($filePid, SIGTERM);

				$th->sleep__(3);

				list($filePid, $status) = $th->pidStatus();

				if ($status === self::STATUS_RUN)
					throw new \Exception("Process $filePid still running.");
			}
		}

		public function daemonStart()
		{
			$th = $this->proxyThis();

			list($filePid, $status) = $th->pidStatus();

			if ($status === self::STATUS_RUN)
				throw new \Exception("Daemon is already running");
		}

		const STATUS_RUN = 1;
		const STATUS_STOPPED = 2;

		public function pidStatus()
		{
			$th = $this->proxyThis();

			$pid = $th->posix_getpid__();
			$filePid = $th->getFilePid();

			if (empty($filePid))
				return [$filePid, self::STATUS_STOPPED];

			$pidFileMTime = 0;

			if ($th->is_file__($th->pidFile))
				$pidFileMTime = $th->filemtime__($th->pidFile);

			if (abs(time() - $pidFileMTime) > self::PID_FILE_CHECK_INTERVAL_SEC + 30)
				return [$filePid, self::STATUS_STOPPED];

			return [$filePid, self::STATUS_RUN];
		}

		public function getFilePid()
		{
			$th = $this->proxyThis();

			return @abs(@$th->file_get_contents__($th->pidFile));
		}

		//--------------------------------------------------------------------------------

		public function init()
		{
			$th = $this->proxyThis();

			$th->gc_disable__();

			$th->initDaemon();
		}


		public function initDaemon()
		{
			$th = $this->proxyThis();

			declare(ticks=1);
			$th->pcntl_signal_dispatch__();
			$th->pcntl_signal__(SIGTERM, [$th, 'stopDaemon']);

			$th->pid = $th->posix_getpid__();

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

			$pidFromFile = $th->getFilePid();
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
				throw new \Exception("Wrong pid.");

			$th->file_put_contents__($th->pidFile, $th->pid);

			$th->lastUpdatePidFileTime = time();
		}

		public function tick()
		{
			$th = $this->proxyThis();

			$th->event('onTick', [$th]);

			$th->collectCycles();
			$th->updatePidFile();
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