Tapir
=====

About
-----

Tapir is so named because it's one of the few words in /usr/share/dict/words with 'api' in it.  

Tapir is a quick and dirty attempt to make a PHP library that makes speaking to various APIs easy.  It is loosely inspired by https://github.com/bradfeehan/desk-php in that I wanted to interact with the API in the same way, but didn't want to go into the specifics of representing the structure of the data that will be returned.  Essentially tapir lets you name URLs and their substitution patterns.

Usage
-----
APIs can be added by created a .json file in the api/ folder.  See desk.json for the best example.  The general form for entries here is:

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

Dependencies
------------

For oauth, requires php-oauth which is included.  

For HTTP_PUT requests, PEAR's HTTP::Request2 is required.  That's archived and included as well.
