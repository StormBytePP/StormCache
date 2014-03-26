<?php

/******* USAGE EXAMPLE WITH COMMENTS *********/

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
$cache->CreatePool($userPoolName);

// Configuring newly created pool
$cache->AddPoolServer("127.0.0.1", 11233, 0, $userPoolName);

// Now you have 2 separate pools (each one with a different memcache server)
// HINT: In your code, to disable cache, just comment any AddPoolServer function call
//		That will not affect any other portion of your code, even if you use this library

// Get data example
$data=NULL; //Variable initialization
try {
	$cache->Get("ServerData", $data);
} catch (Exception $ex) {
	//Key does NOT exist in cache
	//Grab data here, example, database call
	$data=some_database_call();
	$cache->Set("ServerData", $data, NULL, 24*3600); //Store data in default pool, without namespace during 24 hours
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

// It has more functions self documented, take a look at documentation (located in doc folder)


?>