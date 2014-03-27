<?php

/***********************************************************************/
/*********************** Storm Cache ***********************************/
/******* An advanced library to extend and handle PECL-Memcached *******/
/***********************************************************************/
/** Author: David Carlos Manuelda **************************************/
/** Email: StormByte@gmail.com *****************************************/
/** Date: 03/25/2014 ***************************************************/
/** Version: 2.0.0 *****************************************************/
/***********************************************************************/
/** Requirements:
	- >=PHP-5.5 (To handle exceptions that were implemented in PHP 5.5)
	- PECL-Memcached (To make use of memcache's features, it will not
		be required in case you don't configure any pool's server, so it is
		safe to use the library even if PECL-Memcached is not installed. )
 */
/** License:
	You are granted to use, modify and distribute this library in any
	form, included but not limited to, source code, binary distributions,
	or any other form as long as you retain README.txt file and LICENSE.txt file in
	the same folder than this library is located, as well as maintaining
	this header intact.

	However, if you find this library interesting or it saved you develop
	time, please consider making a donation (contact with me via my email
	to get more details).

	Furthermore, if, for any reason you need a special right to allow you
	to remove this header, drop me an email to discuss the details about it.
	Please note that, it is strictly forbidden to replace the library ownership to
	any other than me (except in derived work, see details below), in any way, even
	if a donation exists.

	Use and Derived Work definition:
	In terms of this license, there are three terms to be defined:
		* Use:
			In terms of this license, the term "Use" refers to library's
		class instantiation, and any call to any of its functions to perform any
		operations defined library's operations (even if calls come from derived
		library, see below)
		* Derived Work:
			It is considered derived work, any other library or code that extends,
			modify or remove any function set to this library, including,
			but not limited to, extending this class (conforming PHP inheritance),
			copy/paste or adapt some parts of the code in your own library (or similar), and
			similar, even, if variable names (or similar) are changed.
			In this case, your library or code portion will be efectivelly considered
			a derived work from this library, and must comply with this license.
			Unless any of the terms above are met, usage of this library in your code will
			NOT be considered derived work.
	By using this library, or any portion of it (including the above mentioned "Derived Work"),
	anywhere, you implicitelly accept the terms of this license.

	Any term of this license can be modified by written and signed paper from library's author
*/

/**
 * Exception Class for items not found in cache exception
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolItemNotFound extends Exception {
	public function __construct($keyname) {
		parent::__construct("Item with key $keyname is not found in cache");
	}
}

/**
 * Exception Class for pool not found
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolNotFound extends Exception {
	public function __construct($poolName) {
		parent::__construct("Pool $poolName not found");
	}
}

/**
 * Exception Class for handling a pool name conflict
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolNameConflict extends Exception {
	public function __construct($name) {
		parent::__construct("Pool's name $name is already defined");
	}
}

/**
 * Exception Class for handling a pool server conflict
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolServerConflict extends Exception {
	public function __construct($serverIP, $serverPORT) {
		parent::__construct("Server $serverIP in port $serverPORT is already defined in another pool");
	}
}

/**
 * Exception Class for can't delete default pool
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolCantDeleteDefault extends Exception {
	public function __construct() {
		parent::__construct("Default pool can't be deleted");
	}
}

/**
 * Exception Class for a pool not connected
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.0
 */
class PoolNotConnected extends Exception {
	public function __construct($poolName) {
		parent::__construct("Pool $poolName is not connected or does not have servers");
	}
}

/**
 * Class for caching data in ram
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	2.0.1
 */
class StormCache {
	const DefaultCacheExpiryTime	= 432000; //5 days
	const DefaultPoolName			= "default";
	
	/**
	 * Memcached Resource for StormCache
	 * @var Memcached[]
	 */
	private $resource;

	/**
	 * Pool server configuration
	 * @var string[][]
	 */
	private $poolConfig;
	
	/**
	 * Instance for StormCache
	 * @var StormCache 
	 */
	private static $instance = NULL;
	
	/**
	 * Hits and misses to get some useful statistics
	 * @var int
	 */
	private static $hits=0, $misses=0;

	/**
	 * Get an instance for DB Cache
	 * @return StormCache
	 */
	public static function GetInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new StormCache();
		}
		return self::$instance;
	}

	/**
	 * Construct object for Cache, creates an empty default pool
	 */
	private function __construct() {
		$this->memcachedData=NULL;
		$this->resource=array();
		$this->poolConfig=array(self::DefaultPoolName=>array());
	}
	
	public function __destruct() {
		foreach ($this->resource as $poolResource) {
			
		}
	}
	
	/**
	 * Does a pool exists?
	 * @param string $poolName Pool Name
	 * @return bool
	 */
	private function PoolExists($poolName) {
		$pn=  strtolower($poolName);
		return isset($this->poolConfig["$pn"]);
	}
	
	/**
	 * Is pool connected?
	 * @param string $poolName Pool Name
	 * @return bool
	 */
	private function IsPoolConnected($poolName) {
		$pn=  strtolower($poolName);
		return isset($this->resource["$pn"]);
	}
	
	/**
	 * Creates a new pool
	 * @param string $poolName Pool's Name (case insensitive)
	 * @throws PoolNameConflict
	 */
	public function CreatePool($poolName) {
		$pn=  strtolower($poolName);
		if ($this->PoolExists($poolName)) throw new PoolNameConflict($pn);
		else $this->poolConfig["$pn"]=array();
	}
	
	/**
	 * Deletes a pool (and disconnect it if it was active)
	 * @param string $poolName Pool name (case insensitive)
	 * @throws PoolCantDeleteDefault
	 * @throws PoolNotFound
	 */
	public function DeletePool($poolName) {
		$pn=  strtolower($poolName);
		if ("default" == $pn) throw new PoolCantDeleteDefault();
		else if (!$this->PoolExists($poolName)) throw new PoolNotFound();
		else {
			if ($this->IsPoolConnected($poolName)) {
				$this->resource["$pn"]->close();
				unset($this->resource["$pn"]);
			}
			unset($this->poolConfig["$pn"]);
		}
	}
	
	/**
	 * Adds a pool server
	 * @param string $serverIP Server IP
	 * @param string $serverPort Server Port
	 * @param int $weight The weight of the server relative to the total weight of all the servers in the pool. This controls the probability of the server being selected for operations. This is used only with consistent distribution option and usually corresponds to the amount of memory available to memcache on that server.
	 * @param string $poolName Pool name
	 * @throws PoolNotFound
	 * @throws PoolServerConflict
	 */
	public function AddPoolServer($serverIP, $serverPort, $weight=0, $poolName=  self::DefaultPoolName) {
		$pn=  strtolower($poolName);
		if (!isset($this->poolConfig["$pn"])) throw new PoolNotFound($pn);
		else {
			$found=FALSE;
			foreach ($this->poolConfig as $poolCFG) {
				for($i=0; $i<count($poolCFG) && !$found; $i++)
					$found=($poolCFG[$i]['serverIP']==$serverIP && $poolCFG[$i]['serverPORT']==$serverPort);
				if ($found) break;
			}
		}
		if ($found) throw new PoolServerConflict($serverIP, $serverPort);
		else {
			array_push($this->poolConfig["$pn"],	array(	'serverIP' => $serverIP,
															'serverPORT' => $serverPort,
															'weight' => $weight
													));
			if (!isset($this->resource["$pn"])) $this->resource["$pn"]=new Memcached();
			$this->resource["$pn"]->addServer($serverIP, $serverPort, $weight);
		}
	}

	/**
	 * Sets data (it does not throw any exception to improve code quality when using the lib)
	 * @param string $key Key
	 * @param mixed $data Data
	 * @param string|array|NULL $namespaces Namespace to bind data to (if applicable)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name to set data to
	 * @return bool TRUE if it was stored, FALSE on error
	 */
	public function Set($key, $data, $namespaces=NULL, $expire=self::DefaultCacheExpiryTime, $poolName=self::DefaultPoolName) {
		$result=FALSE;
		if ($this->IsPoolConnected($poolName)) {
			$this->Lock($namespaces, $poolName);
			$result=$this->SetData($key, $data, $expire, $poolName);
			$this->AddNamespaceStoredKey($namespaces, $key, $poolName);
			$this->UnLock($namespaces, $poolName);
		}
		return $result;
	}
	
	/**
	 * Sets multiple data at once
	 * @param array $items Items to be added in the form key => data
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name to set data to
	 * @return bool Result operation
	 */
	public function SetMulti($items, $namespaces=NULL, $expire=self::DefaultCacheExpiryTime, $poolName=self::DefaultPoolName) {
		$result=FALSE;
		if ($this->IsPoolConnected($poolName)) {
			$this->Lock($namespaces, $poolName);
			$result=$this->SetDataMulti($key, $data, $expire, $poolName);
			$this->AddNamespaceStoredKey($namespaces, $key, $poolName);
			$this->UnLock($namespaces, $poolName);
		}
		return $result;
	}
	
	/**
	 * Sets data for cache
	 * @param string $key Key
	 * @param variant $data Data to store
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name to set data to
	 * @return bool Operation Result (if FALSE, then, data was NOT stored)
	 */
	private function SetData($key, $data, $expire, $poolName) {
		$pn=  strtolower($poolName);
		return $this->resource["$pn"]->set($key, $data, (int)$expire);
	}
	
	/**
	 * Sets multiple data at once
	 * @param array $items Items to be added in the form key => data
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name to set data to
	 * @result Result Operation
	 */
	private function SetDataMulti($items, $expire, $poolName) {
		$pn=  strtolower($poolName);
		return $this->resource["$pn"]->setMulti($items, (int)$expire);
	}

	/**
	 * Replaces data for cache
	 * @param string $key Key
	 * @param variant $data Data to store
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name to set data to
	 * @return bool Operation Status
	 */
	public function Replace($key, $data, $expire = self::DefaultCacheExpiryTime, $poolName=self::DefaultPoolName) {
		if ($this->IsPoolConnected($poolName)) {
			$pn=  strtolower($poolName);
			return $this->resource["$pn"]->replace($key, $data, $expire);
		}
	}

	/**
	 * Internal function to get data from cache
	 * @param string $key Key to recover data from
	 * @param string $poolName Pool name to get data from
	 * @return mixed|false FALSE on failure
	 */
	private function GetData($key, $poolName) {
		$pn=  strtolower($poolName);
		$data=$this->resource["$pn"]->get($key);
		if ($data!==FALSE) self::$hits++; else self::$misses++;
		return $data;
	}
	
	/**
	 * Expires namespaces
	 * @param string|array $namespaces
	 * @param string $poolName Pool name to expire namespace from
	 */
	public function ExpireNamespace($namespaces, $poolName=self::DefaultPoolName) {
		if ($this->IsPoolConnected($poolName)) {
			if (is_array($namespaces)) {
				foreach ($namespaces as $namespace) {
					$this->ExpireNamespace($namespace, $poolName);
				}
			}
			else if (!is_null($namespaces)) {
				$pn=  strtolower($poolName);
				$this->Lock($namespaces, $poolName);
				$currentKeys=$this->GetData($namespaces, $poolName);
				if (!empty($currentKeys)) {
					foreach ($currentKeys as $key => $ignored) {
						$this->Delete($key, $poolName);
					}
				}
				unset($currentKeys);
				$this->Delete($namespaces, $poolName);
				$this->UnLock($namespaces, $poolName);
			}
		}
	}
	
	/**
	 * Waits on lock
	 * @param string $key
	 * @param string $poolName Pool name to expire namespace from
	 */
	private function WaitLock($key, $poolName=self::DefaultPoolName) {
		while ($this->GetData($key."_lock", $poolName)!==FALSE) {
			usleep(10);
		}
	}
	
	/**
	 * Creates a lock for $key, and waits if it is currently locked
	 * @param string|array $key
	 * @param string $poolName Pool name to expire namespace from
	 */
	private function Lock($key, $poolName) {
		if (is_array($key)) {
			foreach ($key as $ignored => $keystring) {
				$this->Lock($keystring, $poolName);
			}
		}
		else if (!is_null($key)) {
			$this->WaitLock($key, $poolName); //Wait until it is unlocked to prevent double lock issues
			$this->SetData($key."_lock", TRUE, 0, $poolName);
		}
	}
	
	/**
	 * Unlocks key
	 * @param string|array $key 
	 * @param string $poolName Pool name to expire namespace from
	 */
	private function UnLock($key, $poolName) {
		if (is_array($key)) {
			foreach ($key as $ignored => $keystring) {
				$this->UnLock($keystring, $poolName);
			}
		}
		else if (!is_null($key)) {
			if ($this->GetData($key."_lock", $poolName)!==FALSE) {
				$this->Delete($key."_lock", $poolName);
			}
		}
	}
	
	/**
	 * Creates a namespace if it does not exists
	 * @param string|array $namespaces
	 * @param string $poolName Pool name to expire namespace from
	 */
	private function CreateNamespace($namespaces, $poolName) {
		if (is_array($namespaces)) {
			foreach ($namespaces as $namespace) {
				$this->SetNamespace($namespace, $poolName);
			}
		}
		else {
			if ($this->GetData($namespaces, $poolName)===FALSE) {
				$this->SetData($namespaces, array(), 0, $poolName);
			}
		}
	}
	
	/**
	 * Adds a key to namespace
	 * @param string|array $namespaces
	 * @param string $key 
	 * @param string $poolName Pool name to expire namespace from
	 */
	private function AddNamespaceStoredKey($namespaces, $key, $poolName) {
		if (is_array($namespaces)) {
			foreach ($namespaces as $namespace) {
				$this->AddNamespaceStoredKey($namespace, $key, $poolName);
			}
		}
		else if (!is_null($namespaces)) {
			$currentkeys=$this->GetData($namespaces, $poolName);
			if ($currentkeys===FALSE) {
				$this->CreateNamespace($namespaces, $poolName);
				$currentkeys=array();
			}
			$currentkeys[$key]=TRUE;
			$this->Replace($namespaces, $currentkeys, 0, $poolName);
		}
	}
	
	/**
	 * Gets data from pool's cache
	 * @param string $key Key to look data
	 * @param mixed &$data Data variable to stored results (passed by reference)
	 * @param string $poolName Pool Name to look data
	 * @throws PoolNotFound
	 * @throws PoolNotConnected
	 * @throws PoolItemNotFound
	 */
	public function Get($key, &$data, $poolName=self::DefaultPoolName) {
		if (!$this->PoolExists($poolName)) throw new PoolNotFound($poolName);
		else if (!$this->IsPoolConnected($poolName)) throw new PoolNotConnected($poolName);
		else {
			$tmp = $this->GetData($key, $poolName);
			if ($tmp!==FALSE) {
				$data=$tmp;
			}
			else {
				throw new PoolItemNotFound($key);
			}
		}
	}

	/**
	 * Flush all data from cache
	 * @param string $poolName Pool Name to look data
	 */
	public function Flush($poolName=self::DefaultPoolName) {
		if ($this->IsPoolConnected($poolName)) {
			$pn=  strtolower($poolName);
			$this->resource["$pn"]->flush();
		}
	}

	/**
	 * Deletes a stored key data
	 * @param string $key 
	 * @param string $poolName Pool Name to look data
	 * @return bool Operation Status
	 */
	public function Delete($key, $poolName=self::DefaultPoolName) {
		$result=FALSE;
		if ($this->IsPoolConnected($poolName)) {
			$pn=  strtolower($poolName);
			$result=$this->resource["$pn"]->delete($key);
		}
		return $result;
	}
	
	/**
	 * Deletes multiple items in cache
	 * @param string[] $keys Keys to delete 
	 * @param string $poolName Pool Name to look data
	 * @return bool Operation Status
	 */
	public function DeleteMulti($keys, $poolName=self::DefaultPoolName) {
		$result=FALSE;
		if ($this->IsPoolConnected($poolName)) {
			$pn=  strtolower($poolName);
			$result=$this->resource["$pn"]->deleteMulti($keys);
		}
		return $result;
	}
	
	/**
	 * Touch data (sets new expiration time)
	 * @param string $key Key to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name
	 * @return bool Operation Status
	 */
	public function Touch($key, $expire, $poolName=self::DefaultPoolName) {
		$result=FALSE;
		if ($this->IsPoolConnected($poolName)) {
			$pn=  strtolower($poolName);
			$result=$this->resource["$pn"]->touch($key, (int)$expire);
		}
		return $result;
	}
	
	/**
	 * Touch multiple data (sets new expiration time)
	 * @param string[] $keys Keys to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolName Pool name
	 * @return bool Operation Status
	 */
	public function TouchMulti($keys, $expire, $poolName=self::DefaultPoolName) {
		$result=TRUE;
		foreach ($keys as $key) {
			$result&=$this->Touch($key, $expire, $poolName);
		}
		return $result;
	}
	
	/**
	 * Gets pool stats
	 * @param string $poolName Pool Name to get stats
	 * @param string|NULL $poolName Pool Name to get stats from (NULL to get all pool stats)
	 * @return array|null
	 */
	public function GetPoolStats($poolName=NULL) {
		$result=NULL;
		if (is_null($poolName)) {
			foreach ($this->resource as $poolName => $poolResource) {
				if (is_null($result)) $result=array();
				$result["$poolName"]=$this->GetPoolStats($poolName);
			}
		}
		else {
			$pn=  strtolower($poolName);
			$result=$this->resource["$pn"]->getstats();
		}
		return $result;
	}
	
	/**
	 * Gets cache hits
	 * @return int
	 */
	public static function GetCacheHits() {
		return self::$hits;
	}
	
	/**
	 * Gets cache misses
	 * @return int
	 */
	public static function GetCacheMisses() {
		return self::$misses;
	}
}

?>
