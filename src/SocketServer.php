<?
	namespace PHPCraftdream\SocketServer;

	class SocketServer extends \PHPCraftdream\TotalTest\CoreObject
	{
		use \PHPCraftdream\Events\traits\Events;

		public $addr;
		public $port;
		public $socket;
		public $stop = false;

		public $pid;
		public $pidFile;

		public $connections = array();

		public function stop()
		{
			$this->stop = true;
		}

		function __construct($addr = '127.0.0.1', $port = 35487, $pidFile)
		{
			$this->initServ($addr, $port, $pidFile);
		}

		public function getLastSocketError()
		{
			$th = $this->proxyThis();

			$code = $th->socket_last_error__();
			$error = $th->socket_strerror__($code);

			return $error;
		}

		public function initServ($addr, $port, $pidFile)
		{
			$th = $this->proxyThis();

			$th->gc_disable__();

			$th->addr = $addr;
			$th->port = $port;
			$th->pidFile = $pidFile;

			//---------------------------------------------------------------

			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			if (!$socket)
				return $th->throwLastCoecketError();
			//---------------------------------------------------------------

			$nb = $th->socket_set_nonblock__($socket);
			if ($nb === false)
				return $th->throwLastCoecketError();
			//---------------------------------------------------------------

			$SO_REUSEADDR = $th->socket_set_option__($socket, SOL_SOCKET, SO_REUSEADDR, 1);
			if ($SO_REUSEADDR === false)
				return $th->throwLastCoecketError();
			//---------------------------------------------------------------

			$bind = $th->socket_bind__($socket, $th->addr, $th->port);
			if ($bind === false)
				return $th->throwLastCoecketError();
			//---------------------------------------------------------------

			$listen = $th->socket_listen__($socket);
			if ($listen === false)
				return $th->throwLastCoecketError();
			//---------------------------------------------------------------

			$th->socket = $socket;
		}

		public function throwLastCoecketError()
		{
			$th = $this->proxyThis();

			throw new Exception($th->getLastSocketError());
		}

		public function closeSocket($conRes)
		{
			$th = $this->proxyThis();

			$th->socket_shutdown__($conRes, 2);
			$th->socket_close__($conRes);
		}

		public function reindexConnections()
		{
			$th = $this->proxyThis();

			foreach ($th->connections as $id => $con)
				$con->id = $id;
		}

		public function closeConnectionsByIds($ids)
		{
			$th = $this->proxyThis();

			if (!is_array($ids))
				$ids = [$ids];

			foreach ($ids as $id)
			{
				$con = $th->connections[$id];

				$th->event('onCloseSystem', [$th, $con]);
				$th->event('onClose', [$th, $con]);

				$th->closeSocket($con->resource);

				unset($th->connections[$id]);
			}

			$th->connections = array_values($th->connections);
			$th->reindexConnections();
		}

		public function sendMessage($socket, $mess)
		{
			socket_write($socket, $mess, strlen($mess));
		}

		####################################################################

		public function closeConnections()
		{
			$th = $this->proxyThis();

			$deadIds = [];
			foreach ($this->connections as $id => $con)
			{
				if (empty($con->dead)) continue;
				$deadIds[] = $id;
			}

			if (empty($deadIds)) return;

			$th->closeConnectionsByIds($deadIds);
		}

		public function acceptNewConnections()
		{
			$th = $this->proxyThis();

			$newConnections = [];

			while ($connection = socket_accept($th->socket))
			{
				socket_set_nonblock($connection);

				$conn = (object)array('resource' => $connection, 'time' => time(), 'mess' => NULL, 'dead' => 0);

				$th->connections[] = $conn;
				$newConnections[] = $conn;
			}

			if (empty($newConnections)) return;

			$th->reindexConnections();

			foreach ($newConnections as $con)
			{
				$th->event('onConnectSystem', [$th, $con]);
				$th->event('onConnect', [$th, $con]);
			}
		}

		public function initDaemon()
		{
			if (function_exists('pcntl_signal_dispatch'))
				pcntl_signal_dispatch();

			if (function_exists('pcntl_signal_dispatch'))
				pcntl_signal(SIGCHLD, array($this, 'stopServer'));

			if (function_exists('posix_getpid'))
				$this->pid = posix_getpid();

			file_put_contents($this->pidFile, $this->pid);
		}

		public function listen()
		{
			$th = $this->proxyThis();

			$th->initDaemon();

			while (true)
			{
				if (!empty($th->stop)) return;

				if (mt_rand(1, 1000) == 500)
					gc_collect_cycles();

				$th->acceptNewConnections();
				$th->readConnectionsBuffers();
				$th->closeConnections();

				$th->event('onTick', [$th]);

				usleep(1000);
			}
		}

		public function stopDaemon()
		{
			$th = $this->proxyThis();

			$th->stop = true;

			unlink($this->pidFile);

			echo PHP_EOL . "SIGTERM" . PHP_EOL;
		}

		public function readConnectionsBuffers()
		{
			$th = $this->proxyThis();

			if (empty($th->connections))
				return usleep(10000);

			foreach ($th->connections as $id => $con)
			{
				$hasMess = $th->readBuffer($con);

				if (empty($hasMess)) continue;

				$th->event('onMessageSystem', [$th, $con]);
				$th->event('onMessage', [$th, $con]);
			}
		}

		public function readBuffer($con)
		{
			$th = $this->proxyThis();

			$mess = NULL;

			while (true)
			{
				socket_clear_error($con->resource);
				$buffer = socket_read($con->resource, 64);

				if (empty($buffer)) break;

				$mess .= $buffer;
				usleep(1000);
			}

			if (abs(time() - $con->time) > 5)
				$con->dead = true;

			if (is_null($mess)) return false;

			$con->time = time();
			$con->mess = $mess;

			return true;
		}
	}