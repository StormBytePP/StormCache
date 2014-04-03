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

require_once 'StormCacheInternals.php'; // This file is needed to get the internal classes needed for it to work

/**
 * Class for caching data in ram
 *
 * @author	David Carlos Manuelda <stormbyte@gmail.com>
 * @package StormCache
 * @version	3.0.0
 */
class StormCache {
	const DefaultPoolName = "default";
	
	/**
	 * Pools configured
	 * @var StormCachePool[]
	 */
	private $pools;
	
	/**
	 * Instance variable
	 * @var StormCache
	 */
	private static $_instance=NULL;
	
	/**
	 * Creates a new StormCache object
	 */
	private function __construct() {
		$this->pools=array();
		$this->AddPool(self::DefaultPoolName);
	}
	
	/**
	 * Gets StormCache instance
	 * @return StormCache
	 */
	public static function GetInstance() {
		if (is_null(self::$_instance)) self::$_instance=new StormCache();
		return self::$_instance;
	}
	
	/**
	 * Adds a pool
	 * @param string $name Pool's Name
	 * @throws PoolNameConflict
	 */
	public function AddPool($name) {
		$lowername= strtolower($name);
		if (array_key_exists($lowername, $this->pools)) throw new PoolNameConflict($name);
		$this->pools["$lowername"]=new StormCachePool($lowername);
	}
	
	/**
	 * Adds a memcache server
	 * @param string $serverIP Server IP
	 * @param string $serverPORT Server Port
	 * @param int $serverWEIGHT The weight of the server relative to the total weight of all the servers in the pool. This controls the probability of the server being selected for operations. This is used only with consistent distribution option and usually corresponds to the amount of memory available to memcache on that server.
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected)
	 */
	public function AddPoolServer($serverIP, $serverPORT, $serverWEIGHT, $poolNAME=self::DefaultPoolName) {
		if (!array_key_exists($poolNAME, $this->pools)) throw new PoolNotFound($poolNAME);
		$this->pools["$poolNAME"]->AddServer($serverIP, $serverPort, $serverWEIGHT);
	}
	
	/**
	 * Gets data
	 * @param string $key Key to retrieve
	 * @param mixed &$data Ref variable to store data (not touched if failed)
	 * @param string $poolNAME Pool Name
	 * @throws CacheNotEnabled When cache is not enabled (no servers have been added to ANY pool)
	 * @throws PoolNotFound When specified pool is not found
	 * @throws PoolNoServersConfigured When selected pool have no servers
	 * @throws PoolItemNotFound When item have NOT been found
	 */
	public function Get($key, &$data, $poolNAME=self::DefaultPoolName) {
		$lowername=  strtolower($poolNAME);
		if (!$this->IsEnabled()) throw new CacheNotEnabled();
		if (!array_key_exists($lowername, $this->pools)) throw new PoolNotFound($lowername);
		if (!$this->pools["$lowername"]->IsEnabled()) throw new PoolNoServersConfigured($lowername);
		$tmp=NULL;
		$this->pools["$lowername"]->Get($key, $tmp);
		if ($tmp===FALSE) throw new PoolItemNotFound($key);
		$data=$tmp;
	}
	
	/**
	 * Sets data (it does not throw any exception to improve code quality when using the lib)
	 * @param string $key Key Key to store data in server
	 * @param mixed $data Data Data to store (do NOT store a boolean FALSE, because it will be stored but appear as failed when get)
	 * @param string|array|NULL $namespaces Namespace to bind data to (if applicable)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected)
	 * @return bool Operation Status
	 */
	public function Set($key, $data, $namespaces=NULL, $expire, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lowername=  strtolower($poolNAME);
		if (array_key_exists($lowername, $this->pools)) {
			$result=$this->pools["$lowername"]->Set($key, $data, $namespaces, $expire);
		}
		return $result;
	}
	
	/**
	 * Sets multiple data at once (SetMulti do NOT support namespace currently)
	 * @param array $items Items to be added in the form key => data (do NOT store boolean false in data)
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected)
	 * @return bool Operation Status
	 */
	public function SetMulti($items, $expire=self::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lowername=  strtolower($poolNAME);
		if (array_key_exists($poolNAME, $this->pools)) {
			$result=$this->pools["$lowername"]->SetMulti($items, $expire);
		}
		return $result;
	}
	
	/**
	 * Replaces data in cache
	 * @param string $key Key
	 * @param mixed $data Data to store
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected)
	 */
	public function Replace($key, $data, $expire = self::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lowername=  strtolower($poolNAME);
		if (!array_key_exists($lowername, $this->pools)) {
			$result=$this->pools["$lowername"]->Replace($key, $data, $expire);
		}
		return $result;
	}
	
	/**
	 * Expires namespaces
	 * @param string|array $namespaces
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 */
	public function ExpireNamespace($namespaces, $poolNAME=self::DefaultPoolName) {
		$lowername=  strtolower($poolNAME);
		if (array_key_exists($lowername, $this->pools)) {
			$this->pools["$lowername"]->ExpireNamespace($namespaces);
		}
	}
	
	/**
	 * Flush all data from cache
	 * @param string|array|NULL $poolNAME Pool Name to flush data from (NULL to flush all)
	 */
	public function Flush($poolNAME=self::DefaultPoolName) {
		if (is_null($poolNAME)) {
			foreach ($this->pools as $poolN => $IGNORED) {
				$this->Flush($poolN);
			}
		}
		else if (is_array($poolNAME)) {
			foreach ($poolNAME as $poolN) {
				$this->Flush($poolN);
			}
		}
		else if (array_key_exists(strtolower($poolNAME), $this->pools)){
			$lower=  strtolower($poolNAME);
			$this->pools["$lower"]->Flush();
		}
	}
	
	/**
	 * Deletes a stored key data
	 * @param string $key 
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 * @return bool Operation Status
	 */
	public function Delete($key, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lower=  strtolower($poolNAME);
		if (!array_key_exists($lower, $this->pools)) {
			$result=$this->pools["$lower"]->Delete($key);
		}
		return $result;
	}
	
	/**
	 * Deletes multiple items in cache
	 * @param string[] $keys Keys to delete
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 * @return bool Operation Status
	 */
	public function DeleteMulti($keys, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lower= strtolower($poolNAME);
		if (array_key_exists($lower, $this->pools)) {
			$result=$this->pools["$lower"]->DeleteMulti($keys);
		}
		return $result;
	}
	
	/**
	 * Touch data (sets new expiration time)
	 * @param string $key Key to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 * @return bool Operation Status
	 */
	public function Touch($key, $expire, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lower=  strtolower($poolNAME);
		if (array_key_exists($lower, $this->pools)) {
			$result=$this->pools["$lower"]->Touch($key, (int)$expire);
		}
		return $result;
	}
	
	/**
	 * Touch multiple data (sets new expiration time)
	 * @param string[] $keys Keys to be affected
	 * @param int $expire Expire time seconds if less than 30 days or timestamp if it is greater
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 * @return bool Operation Status
	 */
	public function TouchMulti($keys, $expire, $poolNAME=self::DefaultPoolName) {
		$result=FALSE;
		$lower=  strtolower($poolNAME);
		if (array_key_exists($lower, $this->pools)) {
			$result=$this->pools["$lower"]->TouchMulti($keys, $expire);
		}
		return $result;
	}
	
	/**
	 * Gets pool stats
	 * @param string $poolNAME Pool Name (if not specified, default pool is selected) 
	 * @param string|NULL $poolNAME Pool Name (if not specified, all pools are selected) 
	 * @return array|null
	 */
	public function GetStats($poolNAME=self::DefaultPoolName) {
		$result=NULL;
		$lower=  strtolower($poolNAME);
		if (is_null($poolNAME)) {
			$result=array();
			foreach ($this->pools as $poolN => $pool) {
				$result["$lower"]=$pool->GetStats();
			}
		}
		else if (array_key_exists($lower, $this->pools)) {
			$result=$this->pools["$lower"]->GetStats();
		}
		return $result;
	}
	
	/**
	 * Gets cache hits
	 * @param string|NULL $poolNAME Pool Name (if not specified, all pools are selected) 
	 * @return int
	 */
	public function GetHits($poolNAME=self::DefaultPoolName) {
		$hits=0;
		$lower=  strtolower($poolNAME);
		if (is_null($poolNAME)) {
			foreach ($this->pools as $pool) {
				$hits+=$pool->GetHits();
			}
		}
		else if (array_key_exists($lower, $this->pools)) {
			$hits+=$this->pools["$lower"]->GetHits();
		}
		return $hits;
	}
	
	/**
	 * Gets cache misses
	 * @param string|NULL $poolNAME Pool Name (if not specified, all pools are selected) 
	 * @return int
	 */
	public function GetMisses($poolNAME=self::DefaultPoolName) {
		$hits=0;
		$lower=  strtolower($poolNAME);
		if (is_null($poolNAME)) {
			foreach ($this->pools as $pool) {
				$hits+=$pool->GetMisses();
			}
		}
		else if (array_key_exists($lower, $this->pools)) {
			$hits+=$this->pools["$lower"]->GetMisses();
		}
		return $hits;
	}
	
	/**
	 * Is Cache enabled? (If it has no servers, then it is not enabled)
	 * @return bool
	 */
	public function IsEnabled() {
		$result=FALSE;
		foreach ($this->pools as $pool) {
			$result|=$pool->IsEnabled();
		}
		return $result;
	}
}

?>
