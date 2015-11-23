<?
	namespace PHPCraftdream\SocketServer;

	class WebSocketServer extends \PHPCraftdream\SocketServer\SocketServer
	{
		public function initServ($addr, $port, $pidFile)
		{
			$th = $this->proxyThis();
			$th->___parentCall('initServ', [$addr, $port, $pidFile]);
			$th->makeTriggers();
		}

		public function makeTriggers()
		{
			$th = $this->proxyThis();

			$th->addTrigger('onMessageSystem', [$th, 'onMessage']);
			$th->addTrigger('onConnectSystem', [$th, 'onConnect']);
			$th->addTrigger('onCloseSystem', [$th, 'onClose']);
		}

		public function onConnect($ws, $con)
		{
			$th = $this->proxyThis();
			$th->usleep__(1000);

			$th->readBuffer($con);

			$th->perform_handshaking($con->mess, $con->resource, $th->addr, $th->port);
		}

		public function onClose($ws, $con)
		{

		}

		public function onMessage($ws, $con)
		{
			$th = $this->proxyThis();

			if (strtolower($con->mess) === 'ping')
				return $con->mess = NULL;

			$con->mess = $th->dataDecode($con->mess);

			if (strtolower($con->mess) === 'ping')
				return $con->mess = NULL;

			$th->wsMessage($con->resource, $con->mess);
		}

		public function wsMessage($socket, $mess)
		{
			$th = $this->proxyThis();

			$sendMess = $th->dataEncode($mess);

			$th->___parentCall('sendMessage', [$socket, $sendMess]);
		}

		public function dataDecode($text)
		{
			$length = ord($text[1]) & 127;

			if ($length == 126)
			{
				$masks = substr($text, 4, 4);
				$data = substr($text, 8);
			}
			elseif ($length == 127)
			{
				$masks = substr($text, 10, 4);
				$data = substr($text, 14);
			}
			else
			{
				$masks = substr($text, 2, 4);
				$data = substr($text, 6);
			}

			$text = "";

			for ($i = 0; $i < strlen($data); $i++)
				$text .= $data[$i] ^ $masks[$i%4];

			return $text;
		}

		public function dataEncode($text)
		{
			$b1 = 0x80 | (0x1 & 0x0f);
			$length = strlen($text);

			if ($length <= 125)
			{
				$header = pack('CC', $b1, $length);
			}
			elseif ($length > 125 && $length < 65536)
			{
				$header = pack('CCn', $b1, 126, $length);
			}
			elseif ($length >= 65536)
			{
				$header = pack('CCNN', $b1, 127, $length);
			}

			return $header . $text;
		}

		public function perform_handshaking($recevedHeader, $socket, $host, $port)
		{
			$headers = array();
			$lines = preg_split("/\r\n/", $recevedHeader);

			foreach ($lines as $line)
			{
				$line = chop($line);

				if (preg_match('/\A(\S+): (.*)\z/', $line, $matches))
					$headers[$matches[1]] = $matches[2];
			}

			$secKey = isset($headers['Sec-WebSocket-Key']) ? $headers['Sec-WebSocket-Key'] : '';
			$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

			$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
						"Upgrade: websocket\r\n" .
						"Connection: Upgrade\r\n" .
						"WebSocket-Origin: ".$host."\r\n" .
						"Sec-WebSocket-Accept:".$secAccept."\r\n\r\n";

			socket_write($socket, $upgrade, strlen($upgrade));
		}
	}