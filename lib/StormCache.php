<?php

/****************************************************************************************/
/*                                 Storm Cache                                          */
/*          An advanced library to extend and handle PECL-Memcached                     */
/****************************************************************************************/
/* * @author      David Carlos Manuelda                                                 */
/* * @email       StormByte@gmail.com                                                   */
/* * @version     3.1.1                                                                 */
/* * @date        04/24/2014                                                            */
/* * @license     See LICENSE.md for details                                            */
/* * @requirements                                                                      */
/* *  - PHP >= 5.5 (for exception handling introduced in PHP 5.5)                       */
/* *  - PECL-Memcached (optional, required only if you configure pool servers)          */
/* *  - OpenSSL support (optional, for encryption features)                             */
/****************************************************************************************/

require_once 'StormCacheInternals.php'; // Internal classes required for functionality

/**
 * Class for caching data in RAM using PECL-Memcached.
 *
 * This library provides advanced features such as:
 * - Multiple pool handling.
 * - Lazy resource instantiation.
 * - Namespace support for keyset expiration.
 * - Optional encryption using OpenSSL.
 *
 * @package StormCache
 * @version 3.1.1
 * @license See LICENSE.md for details
 * @requirements PHP >= 5.5, PECL-Memcached, OpenSSL (optional)
 * @author  David Carlos Manuelda
 */
class StormCache {
    const DefaultPoolName = "default";

    /**
     * Configured pools.
     * @var StormCachePool[]
     */
    private $pools;

    /**
     * Singleton instance of StormCache.
     * @var StormCache|null
     */
    private static $_instance = NULL;

    /**
     * Encryption credentials.
     * @var StormCryptCredentials
     */
    private $encryptCredentials;

    /**
     * Private constructor to initialize the StormCache object.
     * Adds the default pool automatically.
     */
    private function __construct() {
        $this->pools = array();
        $this->encryptCredentials = new StormCryptCredentials();
        $this->AddPool(self::DefaultPoolName);
    }

    /**
     * Gets the singleton instance of StormCache.
     *
     * @return StormCache The singleton instance.
     */
    public static function GetInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new StormCache();
        }
        return self::$_instance;
    }

    /**
     * Adds a new pool.
     *
     * @param string $name The name of the pool.
     * @throws PoolNameConflict If a pool with the same name already exists.
     */
    public function AddPool($name) {
        $lowername = strtolower($name);
        if (array_key_exists($lowername, $this->pools)) {
            throw new PoolNameConflict($name);
        }
        $this->pools[$lowername] = new StormCachePool($lowername);
    }

    /**
     * Adds a Memcached server to a pool.
     *
     * @param string $serverIP The server's IP address.
     * @param int $serverPORT The server's port.
     * @param int $serverWEIGHT The weight of the server relative to others in the pool.
     * @param string $poolNAME The name of the pool (default is "default").
     * @throws PoolNotFound If the specified pool does not exist.
     */
    public function AddPoolServer($serverIP, $serverPORT, $serverWEIGHT, $poolNAME = self::DefaultPoolName) {
        $lower = strtolower($poolNAME);
        if (!array_key_exists($lower, $this->pools)) {
            throw new PoolNotFound($poolNAME);
        }
        $this->pools[$lower]->AddServer($serverIP, $serverPORT, $serverWEIGHT);
    }

    /**
     * Retrieves data from the cache.
     *
     * @param string $key The key to retrieve.
     * @param mixed &$data Reference variable to store the retrieved data.
     * @param string $poolNAME The name of the pool (default is "default").
     * @throws CacheNotEnabled If caching is not enabled (no servers added to any pool).
     * @throws PoolNotFound If the specified pool does not exist.
     * @throws PoolNoServersConfigured If the selected pool has no servers configured.
     * @throws PoolItemNotFound If the item is not found in the cache.
     * @throws PoolItemNotEncrypted If the item is not encrypted but encryption is enabled.
     * @throws PoolItemDecryptFailed If decryption of the item fails.
     * @throws PoolItemEncrypted If the item is encrypted but encryption is disabled.
     */
    public function Get($key, &$data, $poolNAME = self::DefaultPoolName) {
        $lowername = strtolower($poolNAME);
        if (!$this->IsEnabled()) throw new CacheNotEnabled();
        if (!array_key_exists($lowername, $this->pools)) throw new PoolNotFound($lowername);
        if (!$this->pools[$lowername]->IsEnabled()) throw new PoolNoServersConfigured($lowername);
        $tmp = FALSE;
        $this->pools[$lowername]->Get($key, $tmp);
        if ($tmp === FALSE) throw new PoolItemNotFound($key);
        // Check encryption
        if ($this->IsEncryptionEnabled()) {
            if (substr($tmp, 0, 9) != "ENCRYPTED") throw new PoolItemNotEncrypted($key);
            $tmp = $this->DecryptData($tmp);
            if ($tmp === FALSE) throw new PoolItemDecryptFailed($key);
        } else if (substr($tmp, 0, 9) == "ENCRYPTED") throw new PoolItemEncrypted($key);
        $data = $tmp;
    }

    /**
     * Stores data in the cache.
     *
     * @param string $key The key to store the data under.
     * @param mixed $data The data to store.
     * @param string|array|null $namespaces The namespace(s) to bind the data to (optional).
     * @param int $expire Expiration time in seconds (or timestamp if greater than 30 days).
     * @param string $poolNAME The name of the pool (default is "default").
     * @return bool True if the operation was successful, false otherwise.
     */
    public function Set($key, $data, $namespaces = NULL, $expire = StormCachePool::DefaultCacheExpiryTime, $poolNAME = self::DefaultPoolName) {
        $result = FALSE;
        $lowername = strtolower($poolNAME);
        if (array_key_exists($lowername, $this->pools)) {
            if ($this->IsEncryptionEnabled()) $data = $this->EncryptData($data);
            $result = $this->pools[$lowername]->Set($key, $data, $namespaces, $expire);
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
    public function SetMulti($items, $expire=StormCachePool::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
        $result=FALSE;
        $lowername=  strtolower($poolNAME);
        if (array_key_exists($poolNAME, $this->pools)) {
            if ($this->IsEncryptionEnabled()) $data=$this->EncryptData($data);
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
    public function Replace($key, $data, $expire=StormCachePool::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
        $result=FALSE;
        $lowername=  strtolower($poolNAME);
        if (array_key_exists($lowername, $this->pools)) {
            if ($this->IsEncryptionEnabled()) $data=$this->EncryptData($data);
            $result=$this->pools["$lowername"]->Replace($key, $data, $expire);
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
     * @param string $poolNAME Pool Name (if not specified, default pool is selected)
     * @return bool Operation Status
     */
    public function SetReplace($key, $data, $namespaces=NULL, $expire=StormCachePool::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
        $result=FALSE;
        $lowername=  strtolower($poolNAME);
        if (array_key_exists($lowername, $this->pools)) {
            if ($this->IsEncryptionEnabled()) $data=$this->EncryptData($data);
            $result=$this->pools["$lowername"]->Set($key, $data, $namespaces, $expire);
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
        if (array_key_exists($lower, $this->pools)) {
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
    public function Touch($key, $expire=StormCachePool::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
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
    public function TouchMulti($keys, $expire=StormCachePool::DefaultCacheExpiryTime, $poolNAME=self::DefaultPoolName) {
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

    /**
     * Set encryption password (and implicitelly enable encryption features)
     * @param string $password Password for encrypting
     */
    public function SetEncryptionCredentials($password) {
        if (empty($password))
            $this->encryptCredentials->Disable();
        else {
            $this->encryptCredentials->SetPassword($password);
            $this->encryptCredentials->Enable();
        }
    }

    /**
     * Is encryption enabled?
     * @return bool
     */
    public function IsEncryptionEnabled() {
        return $this->encryptCredentials->IsEnabled()
;	}

    /**
     * Encrypts data
     * @param mixed $data
     * @return string|FALSE base64 data if encryption enabled or false if not
     */
    private function EncryptData($data) {
        $result=FALSE;
        if ($this->IsEncryptionEnabled()) {
            $serializedData=  serialize($data);
            $IV=  openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $dataHash=hash("SHA256", $serializedData);
            $encryptedData = openssl_encrypt("$dataHash$serializedData", 'aes-256-cbc', $this->encryptCredentials->GetPassword(), 0, $IV);
            $result=  "ENCRYPTED:".base64_encode($IV).":".base64_encode($encryptedData);
        }
        return $result;
    }

    /**
     * Decrypts data
     * @param string $data Base64 data
     * @return mixed|FALSE Data if success or FALSE if error
     */
    private function DecryptData($data) {
        $result=FALSE;
        //First we check if we have all data needed:
        // HEADER "ENCRYPTED" size: 9
        // IV size: 16
        // HASH size: 64
        $tmpArr = explode(":", $data);
        if (count($tmpArr) == 3 && $tmpArr[0] == "ENCRYPTED") {
            $IV = base64_decode($tmpArr[1]);
            $decryptedRaw = openssl_decrypt(base64_decode($tmpArr[2]), 'aes-256-cbc', $this->encryptCredentials->GetPassword(), 0, $IV);
            $decryptedHash = substr($decryptedRaw, 0, 64);
            $decryptedData = substr($decryptedRaw, 64);
            if (hash("SHA256", $decryptedData) == $decryptedHash)
                $result = unserialize ($decryptedData);
        }
        return $result;
    }
}

?>
