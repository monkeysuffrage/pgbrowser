<?php
/**
 * PGBrowser - A 'pretty good' mechanize-like php library for managing cookies and submitting forms.
 * Website: https://github.com/monkeysuffrage/pgbrowser
 *
 *
 * <b>-------------------------------------------------------------------------------------------
 * THIS IS A FORK FROM:
 * https://github.com/monkeysuffrage/pgbrowser/commit/d7c37ba86d3fb798daa5f783169da7da6bc3a94d
 * -------------------------------------------------------------------------------------------</b>
 *
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
 * @version 0.5
 */

namespace PGuardiario;

use DOMDocument;
use DOMXPath;
use phpQuery;
use phpQueryObject;
use phpUri;

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
    $this->setParser($browser->parserType, $this->html, $this->is_xml);
    if(function_exists('gc_collect_cycles')) gc_collect_cycles();
  }

  /**
   * Clean up some messes
   */
  function __destruct(){
    if($this->browser->parserType == PGBrowser::PHPQUERY){
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
    return preg_match('/^[\.#]?\//', $q);
  }

  private function setParser($parserType, $body, $is_xml){
    switch($parserType){
      case PGBrowser::SIMPLEHTMLDOM:
		  $this->parserType = PGBrowser::SIMPLEHTMLDOM;
		  $this->parser = ($is_xml ? str_get_xml($body) : str_get_html($body));
		  break;

      case PGBrowser::SIMPLEHTMLDOM_ADVC:
		  $this->parserType = PGBrowser::SIMPLEHTMLDOM;
		  $this->parser = str_get_html($body);
		  break;

      case PGBrowser::PHPQUERY:
		  $this->parserType = PGBrowser::PHPQUERY;
		  $this->parser = phpQuery::newDocumentHTML($body);
		  break;
    }
  }

  // public methods

  /**
   * Return the nth form on the page
   * @param int $n The nth form
   * @return PGForm
   */
  public function forms($n = null){
    if (is_numeric($n)) {
	    return $this->_forms[func_get_arg(0)];
	}
	else
	{
		return $this->_forms;
	}
  }

  /**
   * Return the first form
   * @return PGForm
   */
  public function form(){
    return $this->_forms[0];
  }

  
  /**
   * Return the matching nodes of the expression (xpath or css)
   * @param string $query the expression to search for
   * @param string $dom the context to search
   * @return DomNodeList|phpQueryObject|SimpleHtmlDom
   */
  public function search($query, $dom = null){
    if($this->is_xpath($query))
		return $dom ? $this->xpath->query($query, $dom) : $this->xpath->query($query);
    switch($this->parserType){
      case PGBrowser::SIMPLEHTMLDOM:
        $doc = $dom ? $dom : $this->parser;
        return $doc->find($query);

      case PGBrowser::PHPQUERY:
        phpQuery::selectDocument($this->parser);
        $doc = $dom ? pq($dom) : $this->parser;
        return $doc[$query];

      default:
		return $this->xpath->query($query, $dom);
    }
  }
}
