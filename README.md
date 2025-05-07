# Storm Cache

An advanced library to extend and handle PECL-Memcached.

---

**Author:** David Carlos Manuelda aka StormByte  
**Email:** [StormByte@gmail.com](mailto:StormByte@gmail.com)  
**Version:** 3.1.0  
**Release Date:** 4/6/2016  

---

## Hint

If you find this library useful, and/or it saved you development time/costs, please consider making a donation (by contacting me via email) to support this library's development as well as other libraries I release in GPL format.

---

## Information

This library is created to be an effective wrapper around PECL-Memcached, providing a solid and easy-to-use API with the following benefits:

- **Multiple Pools Handling:**  
  Simplifies your code by allowing one or more pools to be handled via the same class. This avoids the need for a separate class for each pool, which can be hard to maintain. Additionally, a "default" pool is defined for cases where only one pool is used, keeping code complexity low.

- **Lazy Resource Instantiation:**  
  The Memcached resource is not instantiated until servers are added to a pool. This allows you to disable caching completely without altering your code and avoids PHP errors when PECL-Memcached is not installed but the class is still used (without adding servers to any pool).  
  To disable caching, simply comment out the lines where servers are added to pools.

- **Namespace Support:**  
  Implements namespaces for easy control of keyset expiration. For example, you can bind several user data keys to a namespace, and when the user is deleted, expire the entire namespace to delete all bound keys.

- **Selective Use of Exceptions:**  
  Uses object-oriented exceptions only in creating pools, adding servers, and getting data. This ensures that the code for getting/setting items in the cache remains trivial (see the usage example below).

---

## Requirements

- **PHP 5:**  
  Exceptions were first implemented in PHP 5, making it the minimum supported version.

- **PECL-Memcached:**  
  Required only when you effectively use caching by binding servers to any pool. If no servers are added, the library will not fail but will not store anything in Memcached either.

---

## Class functions
Take a look at `StormCache` class documentation

## Usage Example

Below is an example of how to use the `StormCache` library. This example demonstrates its key features, including multiple pools, lazy resource instantiation, encryption, and namespace handling.

```php
<?php

/******* USAGE EXAMPLE WITH COMMENTS *********/

/** BENEFITS:
 * This library uses late resource instantiation technique,
 * like instantiating Memcached resource only when you add servers
 * to a pool.
 * Also, it supports encryption with MCrypt, and it only uses it when you
 * configure a password (it will throw an exception if MCrypt is not installed)
 * This way, you are safe to use this library, and your code becomes portable
 * even if destination servers does not have memcached or mcrypt installed!
 * 
 * Like mentioned before, you can safelly disable cache by commenting the lines
 * in which you add servers to pools to completelly disable memcached, without
 * modifying any part of your code, and without having further errors (see get
 * examples)
 */
// Requiring library file
require_once 'StormCache.php';

// Getting and instance to cache
$cache=  StormCache::GetInstance();

// A pool called StormCache::DefaultPoolName ("default") is created automatically

// At this point, it is safe to use this library, even if you don't have PECL-Memcached installed

// Configuring "default" pool
$cache->AddPoolServer("127.0.0.1", 11211);

// Creating another pool
$userPoolName="User Important Data";
$cache->AddPool($userPoolName);

// Configuring newly created pool
$cache->AddPoolServer("127.0.0.1", 11233, 0, $userPoolName);

// Now you have 2 separate pools (each one with a different memcache server)
// HINT: In your code, to disable cache, just comment any AddPoolServer function call
//		That will not affect any other portion of your code, even if you use this library

// Since version 2.1.0, it supports encryption:
// To use it, just set a password to the StormCache instance like:
$cache->SetEncryptionPassword("mypasswordforencrypt1");

// Get data example
// You can use general exception to catch all exceptions, or
// specific exceptions to catch and react on other possibilities
$data=NULL; //Variable initialization
try {
	$cache->Get("ServerData", $data, "POOL_NAME");
} catch (CacheNotEnabled $ex) {
	//Do something when cache is not enabled
} catch (PoolNotFound $ex) {
	//Do something when pool is not found
	//Maybe you mispelled the pool's name or forgot to add the pool
} catch (PoolItemNotEncrypted $ex) {
	//Do something when item was NOT encrypted by encryption is configured
	//This can happen if you enabled encryption while having old data
	//In this case, you should force a new cache encrypted data
} catch (PoolItemEncrypted $ex) {
	//This will happen when item is encrypted BUT encryuption is disabled
	//You will likelly set new plain data in this case
} catch (PoolItemDecryptFailed $ex) {
	//Data is corrupted and/or password is wrong
} catch (PoolItemNotFound $ex) {
	//Item was not found in cache
}

//Most of the cases, you don't need to catch all possible exceptions
//and catch only the general one, for example
try {
	$cache->Get("KEY", $data, "POOL_NAME");
} catch (Exception $ex) {
	//General exception to catch all possible exceptions
	//Most of the cases, you don't need to catch exceptions one by one
	//Grab data here, example, database call
	$data=some_database_call();
	$ok=$cache->Set("ServerData", $data, NULL, 24*3600); //Store data in default pool, without namespace during 24 hours
	//Since you caught only general exceptions, you can't know here if cache is enabled
	//For example, to correctly rely on cache Set, you can use its return value. Example:
	if (!$ok) {
		//Item was not stored in cache, if item is valuable enough, you may want
		//to force store it in database
		some_database_call($data);
	}
}

// To use another pool, just specify pool name in Get/Set member function
// As it can be seen, if you will only use 1 pool, you don't need any special parameter

// Namespace handling example
$data=NULL; //Variable initialization
$userID=1;
try {
	$cache->Get("UserData:$userID", $data);
} catch (Exception $ex) {
	//Key does NOT exist in cache
	//Grab data here, example, database call
	$data=some_database_call($userID);
	$cache->Set("UserData:$userID", $data, "UserNamespace:$userID", 24*3600); //Store data in default pool, bound to "UserNamespace:1" namespace during 24 hours
}

// If you need to delete all user's data from cache, follow this example:
user_delete_function($userID);
$cache->ExpireNamespace("UserNamespace:$userID"); // Remove all data from this namespace

// Following this library's syntax, if you need the namespace in another pool than "default", just set that parameter to pool's name

// By following this try/catch structure in getting/setting data, you are safe to disable cache (as mentioned before) without having to change the rest of your code!

// It has more functions self documented, take a look at documentation
?>
```