<?php

namespace AdimeoCSBundle\Datastore;

use AdimeoCSBundle\Crawl\CurlClient;
use AdimeoCSBundle\Crawl\DomainCrawler;

class DatastoreManager
{

  private $esUrl;

  public function __construct()
  {
    global $kernel;
    $this->esUrl = $kernel->getContainer()->getParameter('adimeo_cs.es_url');
  }

  public function init()
  {
    if (!$this->dmIndexExists()) {
      $this->createDmIndex();
    }
    if (!$this->dmMappingExists()) {
      $this->createDmMapping();
    }
  }

  private function execute($uri, $method = 'GET', $body = [], $encodeBody = true) {
    $client = new CurlClient($this->esUrl . $uri);
    $client->setMethod($method);
    if($body != null && !empty($body)) {
      $client->setBody($encodeBody ? json_encode($body) : $body);
      $client->setHeaders(array('Content-Type: application/json; charset=utf-8'));
    }
    $r = $client->getResponse();
    if($r['code'] >= 200 && $r['code'] < 400) {
      return json_decode($r['data'], TRUE);
    }
    else {
      throw new ElasticsearchException('Elasticsearch error : (' . $r['code'] . ') ' . $r['data'], $r['code'], null);
    }
  }

  private function dmIndexExists()
  {
    try {
      $this->execute('/.adimeocs');
      return true;
    }
    catch(ElasticsearchException $ex) {
      if($ex->getCode() == 404)
        return false;
      else {
        throw $ex;
      }
    }
  }

  private function dmMappingExists()
  {
    try {
      $r = $this->execute('/.adimeocs/_mapping');
      return isset($r['.adimeocs']['mappings']['datastore']);
    }
    catch(ElasticsearchException $ex) {
      if($ex->getCode() == 404)
        return false;
      else {
        throw $ex;
      }
    }
  }

  private function createDmIndex()
  {
    $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/adimeocs_dm_index_settings.json');
    $this->execute('/.adimeocs', 'PUT', array('settings' => json_decode($settingsDefinition, TRUE)));
    $this->execute('/.adimeocs/_flush');
  }

  private function createDmMapping()
  {
    $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/adimeocs_dm_mapping_definition.json');
    $this->execute('/.adimeocs/_mapping/datastore', 'PUT', array('properties' => json_decode($mappingDefinition, TRUE)));
    $this->execute('/.adimeocs/_flush');
  }

  public function saveDocument($doc, $id = NULL, $noFlush = FALSE){
    $uri = '/.adimeocs/datastore';
    if($id != NULL){
      $uri .= '/' . $id;
    }
    $r = $this->execute($uri, $id != NULL ? 'PUT' : 'POST', $doc);

    if(!$noFlush) {
      $this->execute('/.adimeocs/_flush');
    }
    if(isset($r['_id'])){
      return $r['_id'];
    }
    return false;
  }

  public function getItemById($id){
    $r = $this->execute('/.adimeocs/datastore/' . $id);
    if(isset($r['_source'])){
      return $r['_source'];
    }
    return null;
  }

  public function delete($id){
    $r =  $r = $this->execute('/.adimeocs/datastore/' . $id, 'DELETE');
    $this->execute('/.adimeocs/_flush');
    return $r;
  }

  public function searchByTerms($params){
    $body = array(
      'query' => array(
        'bool' => array(
          'must' => array(
            array(
              'match_all' => array('boost' => 1)
            )
          )
        )
      ),
      'sort' => array(
        'datetime' => 'asc'
      ),
      'size' => 100
    );
    foreach($params as $k => $v){
      $body['query']['bool']['must'][] = array(
        'term' => array(
          $k => $v
        )
      );
    }
    $r = $this->execute('/.adimeocs/datastore/_search?scroll=1ms', 'GET', $body);
    if(isset($r['_scroll_id'])){
      $scrollId = $r['_scroll_id'];
      $hits = array();
      while(count($r['hits']['hits']) > 0){
        foreach($r['hits']['hits'] as $hit){
          $hits[] = array_merge($hit['_source'], array('_id' => $hit['_id']));
        }
        $r = $this->execute('/_search/scroll', 'POST', array(
          'scroll_id' => $scrollId,
          'scroll' => '1m'
        ));
      }
      return $hits;
    }
    return [];
  }

  /**
   * @param DatastoreItem[] $items
   */
  public function bulkDelete($items){
    $bulkString = '';

    foreach ($items as $item) {
      $bulkString .= json_encode(array('delete' => array('_id' => $item->getId()))) . "\n";
    }
    $this->execute('/.adimeocs/datastore/_bulk', 'POST', $bulkString, false);
  }

  public function flush() {
    $this->execute('/.adimeocs/_flush');
  }

  public function getCrawlStats($tag) {
    $json = '{
        "query": {
            "term": {
                "tag": "' . $tag . '"
            }
        },
        "aggs": {
            "flag": {
                "terms": {
                    "field": "flag"
                }
            }
        }
    }';
    $r = $this->execute('/.adimeocs/datastore/_search', 'GET', json_decode($json, TRUE));
    $res = array();
    $total = 0;
    if(isset($r['aggregations']['flag']['buckets'])) {
      foreach($r['aggregations']['flag']['buckets'] as $bucket) {
        switch($bucket['key']) {
          case DomainCrawler::CRAWL_STATUS_DONE:
            $res['CRAWL_STATUS_DONE'] = $bucket['doc_count'];
            $total += $bucket['doc_count'];
            break;
          case DomainCrawler::CRAWL_STATUS_PROCESSING:
            $res['CRAWL_STATUS_PROCESSING'] = $bucket['doc_count'];
            $total += $bucket['doc_count'];
            break;
          case DomainCrawler::CRAWL_STATUS_NEW:
            $res['CRAWL_STATUS_NEW'] = $bucket['doc_count'];
            $total += $bucket['doc_count'];
            break;
        }
      }
      $res['TOTAL'] = $total;
    }
    return $res;
  }


}