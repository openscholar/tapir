<?php

class Tapir {
  private $auth;
  private $auth_opts;
  private $APIs;
  private $parameters; //all parameters - data and args
  private $config;

  public function __construct($api, $settings = array()) {
    $this->parameters = array();
    
    //load conf from file
    if (is_string($api)) {
      $filename = __DIR__ . '/api/' . $api . '.json';
      if (is_readable($filename) && ($file = file_get_contents($filename))) {
        $api = json_decode($file, TRUE);
        if (!$api) {
          throw new Exception('Could not load json file: ' . $file);
          return FALSE;
        }
      }
    }
    
    foreach ($api['apis'] as $name => $calls) {
      $this->APIs[$name] = new API($this, $calls);
    }
    
    unset($api['apis']);
    $this->config = $api + $settings;
  }

  public function setParameters($params = array()) {
    $this->parameters = $params;
  }

  public function getParameters() {
    return $this->parameters;
  }

  public function conf($var) {
    return (isset($this->config[$var])) ? $this->config[$var] : NULL;
  }
  
  /**
   * Tapir doesn't have its own cache yet, but whatever uses tapir is welcome to cache data here.
   * Set a cache_get_method and cache_set_method when constructing tapir. 
   **/
  private function cache_get($url, $parameters) {
    $method = $this->conf('cache_get_method');
    return (is_callable($method)) ? $method($url, $parameters) : NULL;
  }
  
  private function cache_set($url, $parameters, $data, $headers = NULL) {
    $method = $this->conf('cache_set_method');
    return (is_callable($method)) ? $method($url, $parameters, $data, $headers) : NULL;
  }

  //use basic auth with username and password.
  public function useBasicAuth($username, $password) {
    if (isset($this->auth)) {
      throw new Exception('Authentication has already been set.');
    }

    $this->auth = 'basic';
    $this->auth_opts = array('username' => $username, 'password' => $password);
  }

  //should this be a separate object?  feels like it could be overridable for different auth or transport options.
  public function useOAuth($consumer_key, $consumer_secret, $token, $secret) {
  	$return = array();
    if (isset($this->auth)) {
      throw new Exception('Authentication has already been set.');
    }
    
    require_once(__DIR__ . '/oauth-php/OAuth.php');
    $this->auth = 'oauth';
    
    //create consumer
    $consumer = new OAuthConsumer($consumer_key, $consumer_secret);
    
    //fetch token and secret if they aren't set.  return them as well so client can store them.
//    if (!$token || !$secret) {     
//      $request = OAuthRequest::from_consumer_and_token($consumer, null, "GET", $this->config['oauth_url'], array());
//      $sigmethod = new OAuthSignatureMethod_HMAC_SHA1();
//      $request->sign_request($sigmethod, $consumer, null);
//      $url = $request->to_url();
//      $token_request = $this->query('get', $url, array(), FALSE);
//      $tokens = array();
//      parse_str($token_request, $tokens);      
//      $token = $tokens['oauth_token'];
//      $secret = $tokens['oauth_token_secret'];
//           
//      $return = array(
//      	'token' => $token,
//      	'secret' => $secret,
//      );
//    }

    $auth = new OAuthToken($token, $secret);
    $this->auth_opts = array(
      'consumer' => $consumer,
      'token' => $auth,
    );

    return $return;
  }

  public function buildQuery($method, $url, $parameters = array()) {
    if ($this->auth == 'oauth') {
      $request = OAuthRequest::from_consumer_and_token($this->auth_opts['consumer'], $this->auth_opts['token'], $method, $url);
      $request->sign_request(new OAuthSignatureMethod_PLAINTEXT(), $this->auth_opts['consumer'], $this->auth_opts['token']);

      foreach ($parameters as $key => $val) {
        $request->set_parameter($key, $val);
      }

      $return = $request->to_url();
    } else {
      //no auth specified.
      $return =  $url . '?' . http_build_query($parameters);
    }

    return $return;

  }

  public function query($method, $url, $parameters = array(), $return_json = TRUE) {
    $original_url = $url;
    if ($cached = $this->cache_get($original_url, $parameters)) {
      return $cached;
    }
    
    $ch = curl_init();

    //for basic auth, use HTTP/Request2.  It handles PUT better.
    //maybe switch to it if everything else does better this way?
    if ($this->auth == 'basic' && $method == 'put') {
      //curl_setopt($ch, CURLOPT_USERPWD, $this->auth_opts['username'] . ':' . $this->auth_opts['password']);

      $request = new HTTP_Request2($url, HTTP_Request2::METHOD_PUT);
      $request->setAuth($this->auth_opts['username'], $this->auth_opts['password'], HTTP_Request2::AUTH_BASIC);
      $request->setHeader('Content-type: application/json');
      $request->setBody(json_encode($parameters));
      $request->setConfig(array('ssl_verify_peer'=>FALSE, 'ssl_verify_host'=>FALSE));

      $response = $request->send();
      $body = $response->getBody();

      return json_decode($body);
    }



    $methods = array(
      //'put' => array(CURLOPT_PUT, TRUE),
      'patch' => array(CURLOPT_CUSTOMREQUEST, 'PATCH'),
      'post' => array(CURLOPT_POST, TRUE),
      'get' => array(CURLOPT_HTTPGET, TRUE),
      'delete' => array(CURLOPT_CUSTOMREQUEST, 'DELETE'),  
     );
    
    //set http method
    list($opt, $val) = $methods[strtolower($method)];
    curl_setopt($ch, $opt, $val);

    //set password if able
    if ($this->auth == 'basic') {
      curl_setopt($ch, CURLOPT_USERPWD, $this->auth_opts['username'] . ':' . $this->auth_opts['password']);
    }

    //set any other options for this method
    switch (strtolower($method)) {
      case 'put':
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        break;

      case 'post':
      case 'patch':
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        //http://stackoverflow.com/questions/11532363/does-php-curl-support-patch
        break;
      case 'get':
        if ($parameters) {
          $url .= '&' . http_build_query($parameters);
        }
        break;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    
    $response = curl_exec($ch);
    curl_close($ch);
    list ($header, $data) = explode("\r\n\r\n", $response, 2);
    
    
//    dpm($url);
//    dpm($data);
//    dpm($parameters);
    
    if ($return_json) {
      $this->cache_set($original_url, $parameters, json_decode($data), $header);
      return ($data && $json = json_decode($data)) ? $json : FALSE;
    }
    
    $this->cache_set($original_url, $parameters, $data, $header);
    return $data;
  }

  public function api($api) {
    if (isset($this->APIs[$api])) {
      return  $this->APIs[$api];
    } else {
      throw new Exception('Unregistered API: ' . $api);
    }
  }

}


class APICall {
  private $method = null;
  private $url; 
  private $data; //data must be passed in post body, not as a parameter

  public function __construct($call) {
    $this->method = $call['method'];
    $this->url = $call['url'];
    $this->data = (isset($call['data'])) ? $call['data'] : NULL;
  }

  public function method() { return $this->method; }

  public function url() { return $this->url; }

  public function data($parameters) {
    if ($this->data) {
      $use_params = array_combine($this->data, $this->data);
      return array_intersect_key($parameters, $use_params);
    } else {
      return $parameters; //no limit, just keep them.
    }
  }

  public function query_args($parameters) {
    return ($this->data) ? array_diff_key($parameters, array_flip($this->data)) : array();
  }
}


class API {
  private $tapirService;

  public function __construct($tapirService, $calls) {
    $this->tapirService = $tapirService;
    $this->APICalls = array();
    foreach ($calls as $name => $call) {
      $this->addCall($name, $call);
    }
  }

  /**
   * @function page
   *
   * Loops over call() to fetch all pages.
   * //Generators aren't availble everywhere yet.  Iterators are 5+
   *
   * //Try adding var to compare pages and current count of items.  That's for 2.0
   */
  public function page($cmd, $parameters = array(), $start = 0, $end = 1,  $page = 'page') { //}, $count_var = NULL, $container_var = NULL) {
    if ($start > $end) {
      throw new Exception('Start page must be lower than end page.');
    }

    $result = array();
    for ($i = $start; $i <= $end; $i++) {
      $parameters[$page] = $i;
      $response = $this->call($cmd, $parameters);
      $this->tapirService;
      if ($container = $this->tapirService->conf('data_container')) {
        $result = array_merge($result, $response->{$container});
      } else {
        $result[] = $response;
      }
    }

    return $result;
  }

  public function call($cmd, $parameters = array()) {
    $tapir = $this->tapirService;
    $parameters += $tapir->getParameters();

    if (!isset($this->APICalls[$cmd])) {
      throw new Exception('Call does not exist: ' . $cmd);
    }


    $call = $this->APICalls[$cmd];
    $url = $this->getUrl($call->url(), $parameters);
    $url = $tapir->buildQuery($call->method(), $url, $call->query_args($parameters));

    $result = $tapir->query($call->method(), $url, $call->data($parameters));
    return $result;
  }

  public function addCall($name, $call) {
    $this->APICalls[$name] = new APICall($call);
  }

  private function getUrl($url, &$parameters) {
    $pattern = '/{.*?}/';
    $tokens = array();
    preg_match_all($pattern, $url, $tokens);
    $tokens = preg_replace('/[{}]/', '', $tokens[0]); //strip {}

    foreach ($tokens as $token) {
      //$token = trim($token, '{}');
      if (!isset($parameters[$token])) {
        throw new Exception('Parameter error.  "'.$token.'" is required.');
      }
      $url = str_replace('{'.$token.'}', $parameters[$token], $url);
      unset($parameters[$token]);
    }

    return $url;
  }


}
