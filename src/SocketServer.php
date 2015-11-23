<?
	namespace PHPCraftdream\SocketServer;

	class SocketServer extends \PHPCraftdream\TotalTest\CoreObject
	{
		use \PHPCraftdream\Events\traits\Events;

		public $addr;
		public $port;
		public $socket;

		public $connections = array();

		function __construct($addr = '127.0.0.1', $port = 35471)
		{
			$this->initServ($addr, $port);
		}

		public function getLastSocketError()
		{
			$th = $this->proxyThis();

			$code = $th->socket_last_error__();
			$error = $th->socket_strerror__($code);

			return $error;
		}

		public function initServ($addr, $port)
		{
			$th = $this->proxyThis();

			$th->gc_disable__();

			$th->addr = $addr;
			$th->port = $port;

			//---------------------------------------------------------------

			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			if (!$socket)
				return $th->throwLastSocketError();
			//---------------------------------------------------------------

			$nb = $th->socket_set_nonblock__($socket);
			if ($nb === false)
				return $th->throwLastSocketError();
			//---------------------------------------------------------------

			$SO_REUSEADDR = $th->socket_set_option__($socket, SOL_SOCKET, SO_REUSEADDR, 1);
			if ($SO_REUSEADDR === false)
				return $th->throwLastSocketError();
			//---------------------------------------------------------------

			$bind = $th->socket_bind__($socket, $th->addr, $th->port);
			if ($bind === false)
				return $th->throwLastSocketError();
			//---------------------------------------------------------------

			$listen = $th->socket_listen__($socket);
			if ($listen === false)
				return $th->throwLastSocketError();
			//---------------------------------------------------------------

			$th->socket = $socket;
		}

		public function throwLastSocketError()
		{
			$th = $this->proxyThis();

			throw new \Exception($th->getLastSocketError());
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

		public function getNewConnection($socket)
		{
			$conn = NULL;

			try
			{
				$conn = @socket_accept($socket);
			}
			catch (\PHPCraftdream\ErrorLog\UnhandledError $e)
			{
				return $th->throwLastSocketError();
			}

			return $conn;
		}

		public function acceptNewConnections()
		{
			$th = $this->proxyThis();

			$newConnections = [];

			while ($connection = $th->getNewConnection($th->socket))
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

		public function listen()
		{
			$th = $this->proxyThis();

			$th->acceptNewConnections();
			$th->readConnectionsBuffers();
			$th->closeConnections();

			usleep(1000);
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

			if (abs(time() - $con->time) > 30)
				$con->dead = true;

			if (is_null($mess)) return false;

			$con->time = time();
			$con->mess = $mess;

			return true;
		}
	}