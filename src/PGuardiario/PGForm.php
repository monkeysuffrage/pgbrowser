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
use DOMNode;
use phpUri;

/**
 * PGForm
 * @package PGBrowser
 */
class PGForm{
  /**
   * The form node
   * @var DOMNode
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
   * @param DOMDocument $dom The DOMNode of the form
   * @param PGPage $page The parent PGPage object
   * @return PGForm
   */
  function __construct($dom, $page){

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

/*
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
*/

  /**
   * Submit the form and return a PGPage object
   * @return PGPage
   */
  public function submit($headers = array()){
    $body = http_build_query($this->fields, '', '&');
    switch($this->method){
      case 'get':
        $url = $this->action .'?' . $body;
        return $this->browser->get($url);
      case 'post':
        if('multipart/form-data' == $this->enctype){
          //$boundary = $this->generate_boundary();
          //$body = $this->multipart_build_query($this->fields, $boundary);
          // let curle mandle multipart
          return $this->browser->post($this->action, $this->fields, array());
        } else {
          return $this->browser->post($this->action, $body, array_merge(array("Content-Type: application/x-www-form-urlencoded"), $headers));
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
