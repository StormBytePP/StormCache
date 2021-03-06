<?php

/***********************************************************************/
/*********************** Storm Cache Internals *************************/
/********** DO NOT USE/INSTANTIATE ANY OF THIS CLASSES DIRECTLY, *******/
/**********            THEY ARE USED INTERNALLY                  *******/
/******* An advanced library to extend and handle PECL-Memcached *******/
/***********************************************************************/
/** Author: David Carlos Manuelda **************************************/
/** Email: StormByte@gmail.com *****************************************/
/** Version: 3.1.1 *****************************************************/
/***********************************************************************/
/** Requirements:
	- >=PHP-5.5 (To handle exceptions that were implemented in PHP 5.5)
	- PECL-Memcached (To make use of memcache's features, it will not
		be required in case you don't configure any pool's server, so it is
		safe to use the library even if PECL-Memcached is not installed. )
	- OpenSSL support (optional, for encrypting features)
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
 * Abstract class for handling memcached functions
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.1
 */
abstract class MemcachedPool {
	const DefaultCacheExpiryTime	= 432000; //5 days
	
	/**
	 * Memcached Resource for StormCache
	 * @var Memcached
	 */
	private $resource;
	
	/**
	 * Array containing the server list
	 * @var array
	 */
	private $serverList;
	
	/**
	 * Hits and misses to get some useful statistics
	 * @var int
	 */
	private $hits,$misses;

	/**
	 * Construct object for Cache, creates an empty default pool
	 */
	public function __construct() {
		$this->resource=NULL;
		$this->serverList=array();
		$this->hits=$this->misses=0;
	}
	
	/**
	 * Destructor
	 */
	public function __destruct() {
		if ($this->resource!==NULL) {
			$this->resource->quit();
			$this->resource=NULL;
		}
	}

	/**
	 * Adds a memcache server
	 * @param string $serverIP Server IP
	 * @param string $serverPort Server Port
	 * @param int $weight The weight of the server relative to the total weight of all the servers in the pool. This controls the probability of the server being selected for operations. This is used only with consistent distribution option and usually corresponds to the amount of memory available to memcache on that server.
	 */
	public function AddServer($serverIP, $serverPort, $weight=0) {
		if ($this->resource===NULL) $this->resource=new Memcached();
		$this->resource->addServer($serverIP, $serverPort, $weight);
		array_push($this->serverList, array('IP' => $serverIP, 'PORT' => $serverPort, 'WEIGHT' => $weight));
	}

	/**
	 * Sets data (it does not throw any exception to improve code quality when using the lib)
	 * @param string $key Key Key to store data in server
	 * @param mixed $data Data Data to store (do NOT store a boolean FALSE, because it will be stored but appear as failed when get)
	 * @param string|array|NULL $namespaces Namespace to bind data to (if applicable)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	public function Set($key, $data, $namespaces=NULL, $expire=self::DefaultCacheExpiryTime) {
		$result=FALSE;
                $this->ReconnectIfNeeded();
                if (!is_null($this->resource)) {
                    $this->Lock($namespaces);
                    $result=$this->SetData($key, $data, $expire);
                    $this->AddNamespaceStoredKey($namespaces, $key);
                    $this->UnLock($namespaces);
                }
		return $result;
	}
	
	/**
	 * Sets multiple data at once (SetMulti do NOT support namespace currently)
	 * @param array $items Items to be added in the form key => data (do NOT store boolean false in data)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	public function SetMulti($items, $expire=self::DefaultCacheExpiryTime) {
		$result=FALSE;
                $this->ReconnectIfNeeded();
                if (!is_null($this->resource)) {
                        $result=$this->SetDataMulti($key, $data, $expire);
                }
		return $result;
	}
	
	/**
	 * Sets data for cache
	 * @param string $key Key
	 * @param variant $data Data to store
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	private function SetData($key, $data, $expire) {
                $result = FALSE;
                if (!is_null($this->resource)) {
                    $result = $this->resource->set($key, $data, (int)$expire);
                }
                return $result;
	}
	
	/**
	 * Sets multiple data at once
	 * @param array $items Items to be added in the form key => data
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	private function SetDataMulti($items, $expire) {
                $result = FALSE;
                if (!is_null($this->resource)) {
                        $result = $this->resource->setMulti($items, (int)$expire);
                }
                return $result;
	}

	/**
	 * Replaces data for cache
	 * @param string $key Key
	 * @param mixed $data Data to store
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	public function Replace($key, $data, $expire = self::DefaultCacheExpiryTime) {
		$result=FALSE;
                $this->ReconnectIfNeeded();
                if (!is_null($this->resource)) {
                    $result = $this->resource->replace($key, $data, $expire);
                }
		return $result;
	}
        
        /**
	 * Sets or replace data (it does not throw any exception to improve code quality when using the lib)
         * @since 3.1.1
	 * @param string $key Key Key to store data in server
	 * @param mixed $data Data Data to store (do NOT store a boolean FALSE, because it will be stored but appear as failed when get)
	 * @param string|array|NULL $namespaces Namespace to bind data to (if applicable)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
        public function SetReplace($key, $data, $namespaces = NULL, $expire = self::DefaultCacheExpiryTime) {
                $result = $this->Replace($key, $data, $expire);
                if (!$result) {
                        $result = $this->Set($key, $result, $namespaces, $expire);
                }
                return $result;
        }

	/**
	 * Internal function to get data from cache
	 * @param string $key Key to recover data from
	 * @return mixed|false FALSE on failure or data in case of success
	 */
	private function GetData($key) {
                $data = FALSE;
                if (!is_null($this->resource)) {
                    $data=$this->resource->get($key);
                    if ($data!==FALSE) $this->hits++; else $this->misses++;
                }
		return $data;
	}
	
	/**
	 * Expires namespaces
	 * @param string|array $namespaces
	 */
	public function ExpireNamespace($namespaces) {
                $this->ReconnectIfNeeded();
                if (is_array($namespaces)) {
                        foreach ($namespaces as $namespace) {
                                $this->ExpireNamespace($namespace);
                        }
                }
                else if (!is_null($namespaces)) {
                        $this->Lock($namespaces);
                        $currentKeys=$this->GetData($namespaces);
                        if (!empty($currentKeys)) {
                                foreach ($currentKeys as $key => $ignored) {
                                        $this->Delete($key);
                                }
                        }
                        unset($currentKeys);
                        $this->Delete($namespaces);
                        $this->UnLock($namespaces);
                }
	}
	
	/**
	 * Waits on lock
	 * @param string $key
	 */
	private function WaitLock($key) {
		while ($this->GetData($key."_lock")!==FALSE) {
			usleep(10);
		}
	}
	
	/**
	 * Creates a lock for $key, and waits if it is currently locked
	 * @param string|array $key
	 */
	private function Lock($key) {
		if (is_array($key)) {
			foreach ($key as $keystring) {
				$this->Lock($keystring);
			}
		}
		else if (!is_null($key)) {
			$this->WaitLock($key); //Wait until it is unlocked to prevent double lock issues
			$this->SetData($key."_lock", TRUE, 0);
		}
	}
	
	/**
	 * Unlocks key
	 * @param string|array $key 
	 */
	private function UnLock($key) {
		if (is_array($key)) {
			foreach ($key as $keystring) {
				$this->UnLock($keystring);
			}
		}
		else if (!is_null($key)) {
			if ($this->GetData($key."_lock")!==FALSE) {
				$this->Delete($key."_lock");
			}
		}
	}
	
	/**
	 * Creates a namespace if it does not exists
	 * @param string|array $namespaces
	 */
	private function CreateNamespace($namespaces) {
		if (is_array($namespaces)) {
			foreach ($namespaces as $namespace) {
				$this->SetNamespace($namespace);
			}
		}
		else {
			if ($this->GetData($namespaces)===FALSE) {
				$this->SetData($namespaces, array(), 0);
			}
		}
	}
	
	/**
	 * Adds a key to namespace
	 * @param string|array $namespaces
	 * @param string $key 
	 */
	private function AddNamespaceStoredKey($namespaces, $key) {
		if (is_array($namespaces)) {
			foreach ($namespaces as $namespace) {
				$this->AddNamespaceStoredKey($namespace, $key);
			}
		}
		else if (!is_null($namespaces)) {
			$currentkeys=$this->GetData($namespaces);
			if ($currentkeys===FALSE) {
				$this->CreateNamespace($namespaces);
				$currentkeys=array();
			}
			$currentkeys[$key]=TRUE;
			$this->Replace($namespaces, $currentkeys, 0);
		}
	}
	
	/**
	 * Gets data from pool's cache
	 * @param string $key Key to look data
	 * @param mixed &$data Data variable to stored results (passed by reference)
	 */
	public function Get($key, &$data) {
                $this->ReconnectIfNeeded();
                $tmp = $this->GetData($key);
                if ($tmp!==FALSE) {
                        $data=$tmp;
                }
	}

	/**
	 * Flush all data from cache
	 * @return bool Operation Status
	 */
	public function Flush() {
                $this->ReconnectIfNeeded();
		return $this->resource->flush();
	}

	/**
	 * Deletes a stored key data
	 * @param string $key 
	 * @return bool Operation Status
	 */
	public function Delete($key) {
                $this->ReconnectIfNeeded();
		return $this->resource->delete($key);
	}
	
	/**
	 * Deletes multiple items in cache
	 * @param string[] $keys Keys to delete 
	 * @return bool Operation Status
	 */
	public function DeleteMulti($keys) {
		$this->ReconnectIfNeeded();
		return $this->resource->deleteMulti($keys);
	}
	
	/**
	 * Touch data (sets new expiration time)
	 * @param string $key Key to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	public function Touch($key, $expire) {
		$this->ReconnectIfNeeded();
		return $this->resource->touch($key, (int)$expire);
	}
	
	/**
	 * Touch multiple data (sets new expiration time)
	 * @param string[] $keys Keys to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @return bool Operation Status
	 */
	public function TouchMulti($keys, $expire) {
		$result=FALSE;
		if (!empty($keys)) {
			$result=TRUE;
			foreach ($keys as $key) {
				$result&=$this->Touch($key, $expire);
			}
		}
		return $result;
	}
	
	/**
	 * Gets pool stats
	 * @return array|null
	 */
	public function GetStats() {
		$this->ReconnectIfNeeded();
		return $this->resource->getStats();
	}
	
	/**
	 * Gets cache hits
	 * @return int
	 */
	public function GetHits() {
		return $this->hits;
	}
	
	/**
	 * Gets cache misses
	 * @return int
	 */
	public function GetMisses() {
		return $this->misses;
	}
	
	/**
	 * Is pool enabled?
	 * @return bool
	 */
	public function IsEnabled() {
		return !is_null($this->resource);
	}
        
        /**
         * Reconnects memcached if needed
         */
        private function ReconnectIfNeeded() {
                if ($this->resource === NULL && !empty($this->serverList)) {
                        $this->resource = new Memcached();
                        foreach ($this->serverList as $serverInfo) {
                                $this->resource->addServer($serverInfo['IP'], $serverInfo['PORT'], $serverInfo['WEIGHT']);
                        }
                }
        }
}

/**
 * Exception Class for items not found in cache exception
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class PoolItemNotFound extends Exception {
	public function __construct($keyname) {
		parent::__construct("Item with key $keyname is not found in cache");
	}
}

/**
 * Exception Class for items not decrypted correctly
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class PoolItemDecryptFailed extends Exception {
	public function __construct($keyname) {
		parent::__construct("Item $keyname can't be decrypted correctly (wrong password or insufficient data)");
	}
}

/**
 * Exception Class for items encrypted but encryption is disabled
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class PoolItemEncrypted extends Exception {
	public function __construct($keyname) {
		parent::__construct("Item $keyname is encrypted but encryption is disabled");
	}
}

/**
 * Exception Class for items not encrypted but encryption is enabled
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class PoolItemNotEncrypted extends Exception {
	public function __construct($keyname) {
		parent::__construct("Item $keyname is not encrypted but encryption is enabled");
	}
}

/**
 * Exception Class for pool not found
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
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
 * @version	3.1.0
 */
class PoolNameConflict extends Exception {
	public function __construct($name) {
		parent::__construct("Pool's name $name is already defined");
	}
}

/**
 * Exception Class for a pool not connected
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class PoolNoServersConfigured extends Exception {
	public function __construct($poolName) {
		parent::__construct("Pool $poolName does not have servers configured");
	}
}

/**
 * Exception Class for cache not enabled
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
class CacheNotEnabled extends Exception {
	public function __construct() {
		parent::__construct("Cache is not enabled");
	}
}

/**
 * Class for handling StormCache Pool
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 */
final class StormCachePool extends MemcachedPool {
	private $name;
	
	/**
	 * Constructor
	 * @param string $name Pool Name (case insensitive)
	 */
	public function __construct($name) {
		parent::__construct();
		$this->name=  strtolower($name);
	}
	
	/**
	 * Destructor
	 */
	public function __destruct() {
		parent::__destruct();
	}
	
	/**
	 * Gets pool name
	 * @return string
	 */
	public function GetName() {
		return $this->name;
	}
}

/**
 * Class for handling encryption credentials
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.1.0
 * @since	3.1.0
 */
final class StormCryptCredentials {
	/**
	 * Encryption password
	 * @var string
	 */
	private $password;
	
	/**
	 * Determines if these credentials are enabled
	 * @var boolean
	 */
	private $enabled;
	
	/**
	 * Constructor
	 * @param string|NULL $password
	 * @version 3.1.0
	 * @since 3.1.0
	 */
	public function __construct($password = NULL) {
		if (empty($password)) {
			$this->password = "";
			$this->enabled = false;
		}
		else {
			$this->password = $password;
			$this->enabled = true;
		}
	}
	
	/**
	 * Gets password
	 * @return string
	 */
	public function GetPassword() {
		return $this->password;
	}
	
	/**
	 * Sets password
	 * @param string $password
	 */
	public function SetPassword($password) {
		$this->password = $password;
	}
	
	/**
	 * Enable this credentials
	 */
	public function Enable() {
		$this->enabled = true;
	}
	
	/**
	 * Disable this credentials
	 */
	public function Disable() {
		$this->enabled = false;
	}

	/**
	 * Sets this credentials enabled status
	 * @param bool $status Status to set
	 */
	public function SetEnabledStatus($status) {
		$this->enabled = (bool)$status;
	}
	
	/**
	 * Checks if credentials are valid and enabled
	 * @return bool
	 */
	public function IsEnabled() {
		return $this->enabled && !empty($this->password);
	}
}
?>