<?php
	//https://github.com/transmission/transmission/blob/main/docs/rpc-spec.md

	class Transmission {

		const TR_STATUS_STOPPED       = 0;
		const TR_STATUS_CHECK_WAIT    = 1;
		const TR_STATUS_CHECK         = 2;
		const TR_STATUS_DOWNLOAD_WAIT = 3;
		const TR_STATUS_DOWNLOAD      = 4;
		const TR_STATUS_SEED_WAIT     = 5;
		const TR_STATUS_SEED          = 6;
	  
		const RPC_LT_14_TR_STATUS_CHECK_WAIT = 1;
		const RPC_LT_14_TR_STATUS_CHECK      = 2;
		const RPC_LT_14_TR_STATUS_DOWNLOAD   = 4;
		const RPC_LT_14_TR_STATUS_SEED       = 8;
		const RPC_LT_14_TR_STATUS_STOPPED    = 16;

		private $url, $username, $password, $sessionId, $rpcVersion;

		private $contextOptions = [
			'http' => [
				'user_agent' => 'TransmissionUN for PHP/8.0',
				//'timeout' => '5',
				'ignore_errors' => true,
			]
		];

		public function __construct($url = 'http://localhost:9091/transmission/rpc',
				$username = null,
				$password = null){
			$this->url = $url;
			$this->username = $username;
			$this->password = $password;
			$this->initSessionId();
			$this->rpcVersion = $this->sessionGet()['arguments']['rpc-version'];
		}

		public function startTorrents($ids){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-start', [
				'ids' => $ids
			]);
		}

		public function stopTorrents($ids){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-stop', [
				'ids' => $ids
			]);
		}

		public function verifyTorrents($ids){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-verify', [
				'ids' => $ids
			]);
		}

		public function reannounceTorrents($ids){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-reannounce', [
				'ids' => $ids
			]);
		}

		public function setTorrents($ids, $arguments = []){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			$arguments['ids'] = $ids;
			return $this->request('torrent-reannounce', $arguments);
		}

		public function getTorrent($id, $fields = null){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			//ALLOW MORE
			return $this->request('torrent-get', [
				'fields' => [
					'id',
					'name',
					'status',
					'doneDate',
					'haveValid',
					'totalSize'
				],
				'ids' => [
					$id
				]
			]);
		}

		public function listTorrents($fields = []){
			$total = $this->sessionStats()['arguments']['torrentCount'];
			/*
			$ids = [];
			for($i = 0; $i < $total; $i++){
				$ids[$i] = $i;
			}
			*/

			return $this->request('torrent-get', [
				'fields' => [
					'id',
					'name',
					'status',
					'doneDate',
					'haveValid',
					'totalSize',

					'percentDone',
					'peersConnected',

					'eta'
				]//,
				//'ids' => $ids
			]);
		}

		public function addFile($torrent, $save = '', $extra = []){
			$extra['download-dir'] = $save;
			$extra['filename'] = $torrent;

			return $this->request('torrent-add', $extra);
		}

		public function addMetaInfo($meta, $save = '', $extra = []){
			$extra['download-dir'] = $save;
			$extra['metainfo'] = base64_encode($meta);
			
			return $this->request('torrent-add', $extra);
		}

		public function removeTorrent($ids, $delete = false){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-remove', [
				'ids' => $ids,
				'delete-local-data' => $delete
			]);
		}

		public function moveTorrent($ids, $location, $moveExisting){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			return $this->request('torrent-set-location', [
				'ids' => $ids,
				'location' => $location,
				'move' => $moveExisting
			]);
		}

		public function renameTorrent($ids, $location, $name){
			if(!is_array($ids)){
				$ids = [ $ids ];
			}

			if(count($ids) != 1){
				throw new Exception('Cannot rename more than one torrent at a time.');
			}

			return $this->request('torrent-rename-path', [
				'ids' => $ids,
				'path' => $location,
				'name' => $name
			]);
		}



		public function sessionGet(){
			return $this->request('session-get', []);
		}

		public function sessionSet(){
			return $this->request('session-set', []);
		}

		public function sessionStats(){
			return $this->request('session-stats', []);
		}

		public function getStatusString($status){
			if($this->rpcVersion < 14){
				switch($status){
					case $this->RPC_LT_14_TR_STATUS_CHECK_WAIT:
						return 'Waiting to verify local files';

					case $this->RPC_LT_14_TR_STATUS_CHECK:
						return 'Verifying local files';

					case $this->RPC_LT_14_TR_STATUS_DOWNLOAD:
						return 'Downloading';

					case $this->RPC_LT_14_TR_STATUS_SEED:
						return 'Seeding';

					case $this->RPC_LT_14_TR_STATUS_STOPPED:
						return 'Stopped';

					default:
						return 'Unknown';
				}

			}else{
				switch($status){
					case $this->TR_STATUS_CHECK_WAIT:
						return 'Waiting to verify local files';

					case $this->TR_STATUS_CHECK:
						return 'Verifying local files';

					case $this->TR_STATUS_DOWNLOAD:
						return 'Downloading';

					case $this->TR_STATUS_SEED:
						return 'Seeding';

					case $this->TR_STATUS_STOPPED:
						return 'Stopped';

					case $this->TR_STATUS_SEED_WAIT:
						return 'Queued for seeding';

					case $this->TR_STATUS_DOWNLOAD_WAIT:
						return 'Queued for download';

					default:
						return 'Unknown';
				}
			}
		}



		protected function initSessionId(){
			$contextOptions = $this->contextOptions;
			
			if($this->username && $this->password){
				$contextOptions['http']['header'] = sprintf("Authorization: Basic %s\r\n",base64_encode($this->username.':'.$this->password));
			}

			$context  = stream_context_create($contextOptions);
			if(!$fp = @fopen($this->url, 'r', false, $context)){
				throw new Exception('Unable to connect to '.$this->url);
			}

			$stream = stream_get_meta_data($fp);
			fclose($fp);

			if($stream['timed_out']){
				throw new Exception('Timed out...');
			}

			if(substr($stream['wrapper_data'][0], 9, 3) == '401'){
				throw new Exception('Invalid username/password.');
			}elseif(substr($stream['wrapper_data'][0], 9, 3) == '409'){
				foreach($stream['wrapper_data'] as $header){
					if(strpos($header, 'X-Transmission-Session-Id: ') === 0){
						$this->sessionId = trim(substr($header, 27));
						break;
					}
				}
				if(!$this->sessionId){	// Didn't find a session_id
					throw new Exception('Unable to retrieve session ID');
				}
			}else{
				throw new Exception('Unexpected response from transmission');
			}
		}

		protected function request($method, $arguments){
			$data = json_encode([
				'method' => $method,
				'arguments' => $arguments
			]);

			$contextOptions = $this->contextOptions;
			$contextOptions['http']['method'] = 'POST';
			$contextOptions['http']['header'] = 'Content-type: application/json'."\r\n".
											 'X-Transmission-Session-Id: '.$this->sessionId."\r\n";
			$contextOptions['http']['content'] = $data;

			if($this->username && $this->password){
				$contextOptions['http']['header'] = sprintf("Authorization: Basic %s\r\n",base64_encode($this->username.':'.$this->password));
			}

			$context  = stream_context_create($contextOptions);
			if(!$fp = @fopen($this->url, 'r', false, $context)){
				throw new Exception('Unable to connect to '.$this->url);
			}

			$response = '';
			while($row = fgets($fp)){
				$response .= trim($row)."\n";
			}

			$stream = stream_get_meta_data($fp);
			fclose($fp);

			if($stream['timed_out']){
				throw new Exception('Timed out...');
			}

			if(substr($stream['wrapper_data'][0], 9, 3) == '401'){
				throw new Exception('Invalid username/password.');
			}elseif(substr($stream['wrapper_data'][0], 9, 3) == '409'){
				throw new Exception('Invalid session ID.');
			}

			return json_decode($response, true);
		}
	}
?>
