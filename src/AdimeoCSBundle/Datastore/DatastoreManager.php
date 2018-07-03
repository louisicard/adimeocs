<?php

namespace AdimeoCSBundle\Datastore;

use AdimeoCSBundle\Crawl\DomainCrawler;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class DatastoreManager
{

  /**
   * @var Client
   */
  private $client;

  public function __construct()
  {
    global $kernel;
    $esUrl = $kernel->getContainer()->getParameter('adimeo_cs.es_url');
    $clientBuilder = new ClientBuilder();
    $clientBuilder->setHosts([$esUrl]);
    $this->client = $clientBuilder->build();
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

  private function dmIndexExists()
  {
    return $this->client->indices()->exists(array(
      'index' => '.adimeocs'
    ));
  }

  private function dmMappingExists()
  {
    return count($this->client->indices()->getMapping(array(
      'index' => '.adimeocs',
      'type' => 'datastore',
    ))) > 0;
  }

  private function createDmIndex()
  {
    $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/adimeocs_dm_index_settings.json');
    $params = array(
      'index' => '.adimeocs',
      'body' => array(
        'settings' => json_decode($settingsDefinition, TRUE)
      ),
    );
    $this->client->indices()->create($params);
    $this->client->indices()->flush();
  }

  private function createDmMapping()
  {
    $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/adimeocs_dm_mapping_definition.json');
    $params = array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'body' => array(
        'properties' => json_decode($mappingDefinition, TRUE)
      ),
    );
    $this->client->indices()->putMapping($params);
    $this->client->indices()->flush();
  }

  public function saveDocument($doc, $id = NULL, $noFlush = FALSE){
    $params = array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'body' => $doc
    );
    if($id != NULL){
      $params['id'] = $id;
    }
    $r = $this->client->index($params);
    if(!$noFlush) {
      $this->client->indices()->flush(array(
        'index' => '.adimeocs',
      ));
    }
    if(isset($r['_id'])){
      return $r['_id'];
    }
    return false;
  }

  public function getItemById($id){
    $r = $this->client->search(array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'body' => array(
        'query' => array(
          'ids' => array(
            'values' => array($id)
          )
        )
      )
    ));
    if(isset($r['hits']['hits'][0]['_source'])){
      return $r['hits']['hits'][0]['_source'];
    }
    return null;
  }

  public function delete($id){
    $r = $this->client->delete(array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'id' => $id
    ));
    $this->client->indices()->flush(array(
      'index' => '.adimeocs',
    ));
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
    $r = $this->client->search(array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'body' => $body,
      'scroll' => '1ms'
    ));
    if(isset($r['_scroll_id'])){
      $scrollId = $r['_scroll_id'];
      $hits = array();
      while(count($r['hits']['hits']) > 0){
        foreach($r['hits']['hits'] as $hit){
          $hits[] = array_merge($hit['_source'], array('_id' => $hit['_id']));
        }
        $r = $this->client->scroll(array(
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
    $params['index'] = '.adimeocs';
    $params['type']  = 'datastore';
    $params['body']  = $bulkString;
    $this->client->bulk($params);
  }

  function __unset($name)
  {
    unset($this->client);
    parent::__unset($name);
  }

  public function flush() {
    $this->client->indices()->flush(array(
      'index' => '.adimeocs',
    ));
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
    $r = $this->client->search(array(
      'index' => '.adimeocs',
      'type' => 'datastore',
      'body' => json_decode($json, TRUE)
    ));
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