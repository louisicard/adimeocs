<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 17/10/2016
 * Time: 23:25
 */

namespace AdimeoCSBundle\Crawl;


use AdimeoCSBundle\Datastore\DatastoreItem;

class PageCrawler
{

  /**
   * @var DatastoreItem
   */
  private $datastoreItem;

  /**
   * PageCrawler constructor.
   * @param DatastoreItem $datastoreItem
   */
  public function __construct(DatastoreItem $datastoreItem)
  {
    $this->datastoreItem = $datastoreItem;
  }

  /**
   * @return DatastoreItem
   */
  public function getDatastoreItem()
  {
    return $this->datastoreItem;
  }

  /**
   * @param DatastoreItem $datastoreItem
   */
  public function setDatastoreItem($datastoreItem)
  {
    $this->datastoreItem = $datastoreItem;
  }

  /**
   * @return string
   */
  public function getUrl(){
    return $this->datastoreItem->getKey();
  }

  public function getCommand(){
    $bin = PHP_BINARY;
    if(!is_executable($bin)){
      $bin = PHP_BINDIR . '/php';
    }
    $console = __DIR__ . '/../../../bin/console';
    $cmd = '"' . $bin . '" "' . $console . '" acs:crawl page';
    return $cmd;
  }

  public function crawl(){
    $doc = $this->getPageContent();
    if($doc == null){
      $doc = array();
    }
    $doc['data_store_item'] = json_decode($this->datastoreItem->toJSON());
    print json_encode($doc);
  }

  private function getPageContent(){
    $client = new CurlClient($this->getUrl());
    $data = $client->getResponse();

    if(isset($data['code']) && $data['code'] == 200) {

      $crawl = isset($data['headers']['content-type']) && strpos($data['headers']['content-type'], 'text/html') !== FALSE;

      if ($crawl) {
        $xml = Tools::getCleanHTMLToXML($data['data']);
        $xpath = $xml->xpath('//a[@href]');
        $internal_links = array();
        $external_links = array();
        foreach ($xpath as $elem) {
          $uri = (string)$elem->attributes()['href'];
          $url_data = Tools::processUri($uri, Tools::getDomain($this->getUrl()), Tools::getScheme($this->getUrl()));
          if ($url_data != NULL) {
            $url_data['url'] = trim(trim($url_data['url']), '/');
            if ($url_data['type'] == 'external') {
              if (!in_array($url_data['url'], $external_links)/* && !Tools::isDomainExcluded(Tools::getDomain($url_data['url']))*/) {
                $external_links[] = $url_data['url'];
              }
            } elseif ($url_data['type'] == 'internal') {
              if (!in_array($url_data['url'], $internal_links)) {
                $internal_links[] = $url_data['url'];
              }
            }
          }
        }
        $document = array(
          'internal_links' => $internal_links,
          'external_links' => $external_links,
          'crawl_time' => date('Y-m-d\TH:i:s'),
          'domain' => Tools::getDomain($this->getUrl()),
          'body' => Tools::extractTextFromHTML($data['data']),
          'html' => Tools::cleanNonUTF8($data['data']),
          'headers' => $data['headers'],
        );
        $xpath = $xml->xpath('//title');
        if (count($xpath) > 0) {
          $document['title'] = trim((string)$xpath[0]);
        }
        return $document;
      }
    }
    return null;
  }


}