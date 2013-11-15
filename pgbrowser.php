<?php
/**
 * PGBrowser - A 'pretty good' mechanize-like php library for managing cookies and submitting forms.
 * Website: https://github.com/monkeysuffrage/pgbrowser
 *
 * <pre>
 * require 'pgbrowser.php';
 * 
 * $b = new PGBrowser();
 * $page = $b->get('http://www.google.com/');
 * echo $page->title;
 * </pre>
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @link http://code.nabla.net/doc/gantry4/class-phpQueryObject.html phpQueryObject
 * @link http://simplehtmldom.sourceforge.net/manual_api.htm SimpleHtmlDom
 *
 * @package PGBrowser
 * @author P Guardiario <pguardiario@gmail.com>
 * @version 0.3
 */

require_once dirname(__FILE__) . '/phpuri.php';

/**
 * PGBrowser
 * @package PGBrowser
 */
class PGBrowser{ 
  /**
   * The curl handle
   * @var mixed
   */
  public $ch;

  /**
   * The parser to use (phpquery/simple-html-dom)
   * @var string
   */
  public $parserType;

  /**
   * If true, requests will be cached in a folder named "cache"
   * @var bool
   */
  public $useCache = false;

  /**
   * If true, relative href and src attributes will be converted to absolute
   * @var bool
   */
  public $convertUrls = false;
  private $lastUrl;
  private $visited;

  /**
   * Return a new PGBrowser object
   * @param string $parserType the type of parser to use (phpquery/simple-html-dom)
   */
  function __construct($parserType = null){
    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_USERAGENT, "PGBrowser/0.0.1 (http://github.com/monkeysuffrage/pgbrowser/)");
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate,identity');
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
      "Accept-Charset:	ISO-8859-1,utf-8;q=0.7,*;q=0.7",
      "Accept-Language:	en-us,en;q=0.5",
      "Connection: keep-alive",
      "Keep-Alive: 300",
      "Expect:"
    ));
    curl_setopt($this->ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($this->ch, CURLOPT_HEADER, true);
    $this->parserType = $parserType;
    if(function_exists('gc_enable')) gc_enable();
  }

  // private methods

  private function clean($str){
    return preg_replace(array('/&nbsp;/'), array(' '), $str);
  }

  private function cacheFilename($url){
    return 'cache/' . md5($url);
  }

  private function saveCache($url, $response){
    if(!is_dir('cache')) @mkdir('cache', 0777);
    file_put_contents($this->cacheFilename($url), $response);
  }

  // public methods

  /**
   * Delete the cached version of an url
   * @param string $url
   */
  public function deleteCache($url){
    unlink($this->cacheFilename(($url)));
  }

  /**
   * Set a curl option
   * @param int $key
   * @param string $value
   */
  public function setopt($key, $value){
    curl_setopt($this->ch, $key, $value);
  }

  /**
   * Set a proxy
   * @param string $host
   * @param string $port
   * @param string $user
   * @param string $password
   */
  public function setProxy($host, $port, $user = NULL, $password = NULL){
    curl_setopt($this->ch, CURLOPT_PROXY, "http://$host:$port");
    if(!empty($user)) curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, "$user:$password");
  }

  /**
   * Set the user agent
   * @param string $user_agent
   */
  public function setUserAgent($user_agent){
    curl_setopt($this->ch, CURLOPT_USERAGENT, $user_agent);
  }

  /**
   * Set curl timeout in milliseconds
   * @param int $timeout
   */
  public function setTimeout($timeout){
    curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
  }

  /**
   * Todo: fill this out
   */
  public function visited($url){
    if(!isset($this->visited)) $this->visited = array();
    if(array_search($url, $this->visited) !== false) return true;
    $this->visited[] = $url;
    return false;
  }

  /**
   * Pretend to 'get' an url but mock it using a local file.
   * @param string $url
   * @param string $filename
   * @return PGPage
   */
  public function mock($url, $filename) {
    $response = file_get_contents($filename);
    $page = new PGPage($url, $this->clean($response), $this);
    $this->lastUrl = $url;
    return $page;
  }

  /**
   * Set curl headers
   */
  public function setHeaders($headers){
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
  }

  /**
   * Get an url
   * @param string $url
   * @return PGPage
   */
  public function get($url) {
    if($this->useCache && file_exists($this->cacheFilename($url))){
      $response = file_get_contents($this->cacheFilename($url));
      $page = new PGPage($url, $this->clean($response), $this);
    } else {
      curl_setopt($this->ch, CURLOPT_URL, $url);
      if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
      curl_setopt($this->ch, CURLOPT_POST, false);
      $response = curl_exec($this->ch);
      $page = new PGPage($url, $this->clean($response), $this);
      if($this->useCache) $this->saveCache($url, $response);
    }
    $this->lastUrl = $url;
    return $page;
  }

 /**
  * Post to an url
  * @param string $url url to post
  * @param string $body post body
  * @param array  $headers http headers
  * @return PGPage
  */
  public function post($url, $body, $headers = array('Content-Type	application/x-www-form-urlencoded')) {
    if($this->useCache && file_exists($this->cacheFilename($url . $body))){
      $response = file_get_contents($this->cacheFilename($url . $body));
      $page = new PGPage($url, $this->clean($response), $this);
    } else {
      if($headers) $this->setHeaders($headers);
      curl_setopt($this->ch, CURLOPT_URL, $url);
      if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
      curl_setopt($this->ch, CURLOPT_POST, true);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS,$body);
      $response = curl_exec($this->ch);
      $page = new PGPage($url, $this->clean($response), $this);
      if($this->useCache) $this->saveCache($url . $body, $response);
      if($headers) $this->setHeaders(preg_replace('/(.*?:).*/','\1', $headers)); // clear headers
    }
    $this->lastUrl = $url;
    return $page;
  }
}

/**
 * PGPage
 * @package PGBrowser
 */
class PGPage{
  /**
   * The last url visited
   * @var string
   */
  public $url;

  /**
   * The parent PGBrowser object
   * @var PGBrowser
   */
  public $browser;

  /**
   * The DOM object constructed from the response
   * @var DomDocument
   */
  public $dom;

  /**
   * The DomXPath object associated with the Dom
   * @var DomXPath
   */
  public $xpath;

  /**
   * The PGForm objects associated with the page
   * @var array
   */
  public $_forms;

  /**
   * The html title tag contents
   * @var string
   */
  public $title;

  /**
   * The http status code of the response
   * @var string
   */
  public $status;

  /**
   * The http headers
   * @var string
   */
  public $headers = array();

  /**
   * The body of the page
   * @var string
   */
  public $body;

  /**
   * The html body of the page
   * @var string
   */
  public $html;

  /**
   * The parser can be a phpQueryObject, SimpleHtmlDom object or null
   * @var mixed
   */
  public $parser;

  /**
   * The type of parser (simple, phpquery)
   * @var string
   */
  public $parserType;

  /**
   * @param string $url The page url
   * @param string $response The http response
   * @param PGBrowser $browser The parent PGBrowser object
   * @return PGPage
   */

  public $is_xml;

  function __construct($url, $response, $browser){
    $this->url = $url;
    $this->html = $response;
    $this->parseResponse($response);
    $this->is_xml = (isset($this->headers['Content-Type']) && preg_match('/\bxml\b/i', $this->headers['Content-Type'])) ? true : false;

    $this->browser = $browser;
    $this->dom = new DOMDocument();
    if($this->is_xml){
      @$this->dom->loadXML($this->html);
    } else {
      @$this->dom->loadHTML($this->html);
    }
    $this->xpath = new DOMXPath($this->dom);
    $this->title = ($node = $this->xpath->query('//title')->item(0)) ? $node->nodeValue : '';
    $this->forms = array();
    foreach($this->xpath->query('//form') as $form){
      $this->_forms[] = new PGForm($form, $this);
    }
    if($browser->convertUrls) $this->convertUrls();
    $this->setParser($browser->parserType, $this->html);
    if(function_exists('gc_collect_cycles')) gc_collect_cycles();
  }

  /**
   * Clean up some messes
   */
  function __destruct(){
    if($this->browser->parserType == 'phpquery'){
      $id = phpQuery::getDocumentID($this->parser);
      phpQuery::unloadDocuments($id);
    }
  }

  /**
   * Parse an http response into status, headers and body
   * @param string $response
   */
  function parseResponse($response){
    // This might look weird but it needs to be mb safe.
    $fp = fopen("php://memory", 'r+');
    fputs($fp, $response);
    rewind($fp);

    $line = fgets($fp);
    while(preg_match('/connection established/i', $line)){
      $line = fgets($fp);
      $line = fgets($fp);
    }
    if(preg_match('/^HTTP\/\d\.\d (\d{3}) /', $line, $m)) $this->status = $m[1];

    while($line = fgets($fp)){
      if(!preg_match('/^(.*?): ?(.*)/', $line, $m)) break;
      $this->headers[$m[1]] = trim($m[2]);
    }

    $this->html = $this->body = stream_get_contents($fp);
    fclose($fp);
  }

  private function convertUrls(){
    $uri = phpUri::parse($this->url);
    foreach($this->xpath->query('//img[@src]') as $el){
      $el->setAttribute('src', $uri->join($el->getAttribute('src')));
    }
    foreach($this->xpath->query('//a[@href]') as $el){
      $el->setAttribute('href', $uri->join($el->getAttribute('href')));
    }
    $this->html = $this->is_xml ? $this->dom->saveXML() : $this->dom->saveHTML();
  }

  private function is_xpath($q){
    return preg_match('/^\.?\//', $q);
  }

  private function setParser($parserType, $body){
    switch(true){
      case preg_match('/simple/i', $parserType): $this->parserType = 'simple'; $this->parser = str_get_html($body); break;
      case preg_match('/phpquery/i', $parserType): $this->parserType = 'phpquery'; $this->parser = @phpQuery::newDocumentHTML($body); break;
    }
  }

  // public methods

  /**
   * Return the nth form on the page
   * @param int $n The nth form
   * @return PGForm
   */
  public function forms(){
    if(func_num_args()) return $this->_forms[func_get_arg(0)];
    return $this->_forms;
  }

  /**
   * Return the first form
   * @return PGForm
   */
  public function form(){
    return $this->_forms[0];
  }

  /**
   * Return the first matching node of the expression (xpath or css)
   * @param string $query the expression to search for 
   * @param string $dom the context to search
   * @return DomNode|phpQueryObject|SimpleHtmlDom
   */
  public function at($query, $dom = null){
    if($this->is_xpath($query)) return $this->search($query, $dom)->item(0);
    switch($this->parserType){
      case 'simple':
        $doc = $dom ? $dom : $this->parser;
        return $doc->find($query, 0);
      case 'phpquery': 
        $dom = $this->search($query, $dom)->eq(0);
        return (0 === $dom->size() && $dom->markupOuter() == '') ? null : $dom;
      default: return $this->search($query, $dom)->item(0);
    }
  }

  /**
   * Return the matching nodes of the expression (xpath or css)
   * @param string $query the expression to search for 
   * @param string $dom the context to search
   * @return DomNodeList|phpQueryObject|SimpleHtmlDom
   */
  public function search($query, $dom = null){
    if($this->is_xpath($query)) return $dom ? $this->xpath->query($query, $dom) : $this->xpath->query($query);
    switch($this->parserType){
      case 'simple':
        $doc = $dom ? $dom : $this->parser;
        return $doc->find($query);
      case 'phpquery':
        phpQuery::selectDocument($this->parser);
        $doc = $dom ? pq($dom) : $this->parser;
        return $doc->find($query);
      default: return $this->xpath->query($query, $dom);
    }
  }
}

/**
 * PGForm
 * @package PGBrowser
 */
class PGForm{
  /**
   * The form node
   * @var DomNode
   */
  public $dom;
  
  /**
   * The parent PGPage object
   */
  public $page;
  
  /**
   * The GrandParent PGBrowser object
   */
  public $browser;
  
  /**
   * The form fields as an associative array
   * @var array
   */
  public $fields;
  
  /**
   * The form's action attribute
   * @var string
   */
  public $action;
  
  /**
   * The form's method attribute
   * @var string
   */
  public $method;
  
  /**
   * The form's enctype attribute
   * @var string
   */
  public $enctype;

  /**
   * @param DomDocument $dom The DomNode of the form
   * @param PGPage $page The parent PGPage object
   * @return PGForm
   */
  function __construct($dom, $page){
    require_once  'phpuri.php';

    $this->page = $page;
    $this->browser = $this->page->browser;
    $this->dom = $dom;
    $this->method = strtolower($this->dom->getAttribute('method'));
    if(empty($this->method)) $this->method = 'get';
    $this->enctype = strtolower($this->dom->getAttribute('enctype'));
    if(empty($this->enctype)) $this->enctype = '';
    $this->action = phpUri::parse($this->page->url)->join($this->dom->getAttribute('action'));
    $this->initFields();    
  }

  // private methods

  private function initFields(){
    $this->fields = array();
    foreach($this->page->xpath->query('.//input|.//select', $this->dom) as $input){
      $set = true;
      $value = $input->getAttribute('value');
      $type = $input->getAttribute('type');
      $name = $input->getAttribute('name');
      $tag = $input->tagName;
      switch(true){
        case $type == 'submit':
        case $type == 'button':
          continue 2; break;
        case $type == 'checkbox':
          if(!$input->getAttribute('checked')){continue 2; break;}
          $value = empty($value) ? 'on' : $value; break;
        case $tag == 'select':
          if($input->getAttribute('multiple')){
            // what to do here?
            $set = false;
          } else {
            if($selected = $this->page->xpath->query('.//option[@selected]', $input)->item(0)){
              $value = $selected->getAttribute('value');
            } else if($option = $this->page->xpath->query('.//option[@value]', $input)->item(0)){
              $value = $option->getAttribute('value');
            } else {
              $value = '';
            }
          }
      }
      if($set) $this->fields[$name] = $value;
    }
  }

  // public methods

  /**
   * Set a form key/value
   * @param string $key
   * @param string $value
   */
  public function set($key, $value){
    $this->fields[$key] = $value;
  }

  private function generate_boundary(){
    return "--". substr(md5(rand(0,32000)),0,10);
  }

  private function multipart_build_query($fields, $boundary = null){
    $retval = '';
    foreach($fields as $key => $value){
      $retval .= "--" . $boundary . "\nContent-Disposition: form-data; name=\"$key\"\n\n$value\n";
    }
    $retval .= "--" . $boundary . "--";
    return $retval;
  }

  /**
   * Submit the form and return a PGPage object
   * @return PGPage
   */
  public function submit(){
    $body = http_build_query($this->fields, '', '&');
    switch($this->method){
      case 'get':
        $url = $this->action .'?' . $body;
        return $this->browser->get($url);
      case 'post':
        if('multipart/form-data' == $this->enctype){
          $boundary = $this->generate_boundary();
          $body = $this->multipart_build_query($this->fields, $boundary);
          return $this->browser->post($this->action, $body, array("Content-Type: multipart/form-data; boundary=$boundary"));
        } else {
          return $this->browser->post($this->action, $body, array("Content-Type: application/x-www-form-urlencoded"));
        }
      default: echo "Unknown form method: $this->method\n";
    }
  }

  /**
   * Submit the form with the doPostBack action of an asp(x) form
   * @example http://scraperblog.blogspot.co.uk/2012/11/introducing-pgbrowser.html
   * @param string $attribute the href or onclick that contains the doPostBack action
   * @return PGPage
   */
  public function doPostBack($attribute){
    preg_match_all("/['\"]([^'\"]*)['\"]/", $attribute, $m);  
    $this->set('__EVENTTARGET', $m[1][0]);
    $this->set('__EVENTARGUMENT', $m[1][1]);
    // $this->set('__ASYNCPOST', 'true');
    return $this->submit();
  }
}
?>