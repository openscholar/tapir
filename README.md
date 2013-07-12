Tapir
=====

About
-----

Tapir is so named because it's one of the few words in /usr/share/dict/words with 'api' in it.  

Tapir is a quick and dirty attempt to make a PHP library that makes speaking to various APIs easy.  It is loosely inspired by https://github.com/bradfeehan/desk-php in that I wanted to interact with the API in the same way, but didn't want to go into the specifics of representing the structure of the data that will be returned.  Essentially tapir lets you name URLs and their substitution patterns.

Usage
-----
APIs can be added by creating a .json file in the api/ folder.  See desk.json for the best example.  The general form for entries here is:

```json
"case":{
  "update":{
    "url":"https://{subdomain}.desk.com/api/v2/cases/{id}",
    "method":"patch",
    "data":["custom_fields"]
  }
}
```

This specifies an API called case.  It contains all the API calls that interact with cases.  One of them is called update.  It alters an existing case.  
* The only thing that is required in it is the url.  
* Fields wrapped with curly braces will be substituted out with items from the query.  
* The method is set to patch to indicate that this is using HTTP's PATCH.  Default is get.
* The data parameter specifies fields that will be sent in the body of the request, not as URL parameters.
* Any fields that are left will be passed in as URL parameters.

Interacting with Tapir is also kept brief.  You will need to instantiate Tapir around one of the APIs.

```PHP
$desk = new Tapir('desk');
```

Then call the api and call methods.  To use case update as above,

```PHP
$desk->api('case')->call('update', array('id' => 123, 'custom_fields' => array('my_custom_field' => 'foobar')));
```

Would update case 123 by setting its custom field (creatively titled my_custom_field) to foobar.

Advanced Usage
--------------

* Fixed parameters

Parameters that apply to all calls in an API can be added with `setParameters`.  The best example of this is desk's subdomain parameter.

* Caching

Repetitive API calls should be cached.  Since Tapir was built to be used by a Drupal module, I wanted to be able to use Drupal's own DB for caching and it didn't make sense to assume we had DB access.  But it also didn't make sense to make Tapir dependent on Drupal.  

To use caching, including a settings array when instantiating Tapir.  Include a value for `cache_get_method` and `cache_set_method`.  These are the names of functions that Tapir can call on to get and set the cache.  `cache_get_method` takes a $url and $paramter argument.  `cache_set_method` takes those as well, plus the data to cache and (optionally) headers returned by the api call.

* Authorization

To use basic authorization, use the `useBasicAuth` method, giving it your username and password as arguments.

OAuth is also available and takes you consumer key, consumer secret, token, and secret as arguments.  Requesting the token and secret given the consumer pair is not yet available.

* Pagination

**In progress**

To make calls to a paginated resource, you can use the `page` method instead of `call`.  It takes a cmd and parameters arg as usual, as well as start and end values, and an arg that will be used as the page variable.

Ideally this should be replaced with a generator, but as long as Drupal supports PHP below 5.5, that's not an option. 

Dependencies
------------

For oauth, requires php-oauth which is included.  

For HTTP_PUT requests, PEAR's HTTP::Request2 is required.  That's archived and included as well.
