/***********************************************************************/
/*********************** Storm Cache ***********************************/
/******* An advanced library to extend and handle PECL-Memcached *******/
/***********************************************************************/
Author: David Carlos Manuelda aka StormByte
Email: StormByte@gmail.com
Version: 3.1.0
Release Date: 4/6/2016

Hint:
	If you find this library useful, and/or it saved you developing time/costs, please
consider making a donation (by contacting me via email) to support this library development
as well as other libraries I release in GPL format.

Information:
	This library is created to be an effective wrapper against PECL-Memcached,
in order to have a solid and easy to use API adding the following benefits:
		* One, or more pools handling via the same class: This will simplify your code
		by avoiding you to use a class for each pool which can be hard to maintain.
		Furthermore, it also defines a "default" pool, so in case you only use 1 pool
		(most of the cases), your code complexity does not grow.
		* This library do not instantiate its Memcached resource until you add servers to
		any pool: This gives you the benefit that you don't need to alter your code to
		completelly disable cache, and you can avoid PHP errors when PECL-Memcached is
		not installed but you are still using this class (without adding a server to any pool)
		This way, to completelly disable cache without touching your code, just comment the lines
		in which you are adding servers to pool(s)
		* This library implements namespaces for an easy control of a keyset expiring (for example)
		Just use a namespace when you set data to bind that data to a namespace.
		Example: You can bind several user data keys to a namespace, and, when user is deleted,
		you can expire the whole namespace to delete all bound keys.
		* It uses OO exceptions only in Creating pools, adding servers, and getting data,
		but not everywhere: This way, the code for getting/setting items in cache is trivial (see Usage.php)

Requirements:
	- PHP-5 (Exceptions where first implemented in PHP5, so this is the minimum version supported)
	- PECL-Memcached:
		Only required when you efectivelly use cache by binding servers to any pool (as explained
		above, it can still be used without cache, it will not fail, but it will not store anthing
		in memcached)
