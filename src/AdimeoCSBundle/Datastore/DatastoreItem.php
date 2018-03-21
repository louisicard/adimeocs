<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 17/10/2016
 * Time: 21:12
 */

namespace AdimeoCSBundle\Datastore;


use Symfony\Component\Validator\Constraints\DateTime;

class DatastoreItem
{

  /**
   * @var string
   */
  private $id;

  /**
   * @var string
   */
  private $class;

  /**
   * @var \DateTime
   */
  private $datetime;

  /**
   * @var string
   */
  private $key;

  /**
   * @var string
   */
  private $domain;

  /**
   * @var string
   */
  private $tag;

  /**
   * @var integer
   */
  private $flag;

  /**
   * @var string
   */
  private $searchable;

  /**
   * @var mixed
   */
  private $data;

  /**
   * DatastoreItem constructor.
   */
  public function __construct()
  {
    $this->datetime = new \DateTime();
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param string $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getClass()
  {
    return $this->class;
  }

  /**
   * @param string $class
   */
  public function setClass($class)
  {
    $this->class = $class;
  }

  /**
   * @return \DateTime
   */
  public function getDatetime()
  {
    return $this->datetime;
  }

  /**
   * @param \DateTime $datetime
   */
  public function setDatetime($datetime)
  {
    $this->datetime = $datetime;
  }

  /**
   * @return string
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * @param string $key
   */
  public function setKey($key)
  {
    $this->key = $key;
  }

  /**
   * @return string
   */
  public function getTag()
  {
    return $this->tag;
  }

  /**
   * @param string $tag
   */
  public function setTag($tag)
  {
    $this->tag = $tag;
  }

  /**
   * @return int
   */
  public function getFlag()
  {
    return $this->flag;
  }

  /**
   * @param int $flag
   */
  public function setFlag($flag)
  {
    $this->flag = $flag;
  }

  /**
   * @return string
   */
  public function getSearchable()
  {
    return $this->searchable;
  }

  /**
   * @param string $searchable
   */
  public function setSearchable($searchable)
  {
    $this->searchable = $searchable;
  }

  /**
   * @return mixed
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * @param mixed $data
   */
  public function setData($data)
  {
    $this->data = $data;
  }

  /**
   * @return string
   */
  public function getDomain()
  {
    return $this->domain;
  }

  /**
   * @param string $domain
   */
  public function setDomain($domain)
  {
    $this->domain = $domain;
  }

  public static function load($id){
    $dm = new DatastoreManager();
    $r = $dm->getItemById($id);
    unset($dm);
    if($r == null)
      return null;
    return static::instantiate($r, $id);
  }

  /**
   * @param mixed $params
   * @return DatastoreItem[]
   */
  public static function loadByProperties($params){
    $dm = new DatastoreManager();
    $r = $dm->searchByTerms($params);
    $items = [];
    foreach($r as $res){
      $items[] = static::instantiate($res, $res['_id']);
    }
    unset($dm);
    return $items;
  }

  /**
   * @param mixed $r
   * @param string $id
   * @return DatastoreItem
   */
  private static function instantiate($r, $id){
    $item = new DatastoreItem();
    $item->id = $id;
    if(isset($r['class'])){
      $item->class = $r['class'];
    }
    if(isset($r['datetime'])){
      $item->datetime = \DateTime::createFromFormat('Y-m-d\TH:i:s', $r['datetime']);
    }
    if(isset($r['key'])){
      $item->key = $r['key'];
    }
    if(isset($r['flag'])){
      $item->flag = $r['flag'];
    }
    if(isset($r['data'])){
      $item->data = json_decode($r['data'], TRUE);
    }
    if(isset($r['searchable'])){
      $item->searchable = $r['searchable'];
    }
    if(isset($r['tag'])){
      $item->tag = $r['tag'];
    }
    if(isset($r['domain'])){
      $item->domain = $r['domain'];
    }
    return $item;
  }

  public static function instantiateFromArray($array){
    $item = new DatastoreItem();
    if(isset($array['class'])){
      $item->class = $array['class'];
    }
    if(isset($array['datetime'])){
      if(is_array($array['datetime'])){
        if(isset($array['datetime']['date'])) {
          $item->datetime = \DateTime::createFromFormat('Y-m-d H:i:s.u', $array['datetime']['date']);
        }
      }else {
        $item->datetime = \DateTime::createFromFormat('Y-m-d\TH:i:s', $array['datetime']);
      }
    }
    if(isset($array['key'])){
      $item->key = $array['key'];
    }
    if(isset($array['flag'])){
      $item->flag = $array['flag'];
    }
    if(isset($array['data'])){
      $item->data = $array['data'];
    }
    if(isset($array['searchable'])){
      $item->searchable = $array['searchable'];
    }
    if(isset($array['tag'])){
      $item->tag = $array['tag'];
    }
    if(isset($array['domain'])){
      $item->domain = $array['domain'];
    }
    if(isset($array['id'])){
      $item->id = $array['id'];
    }
    return $item;
  }

  public function save($noFlush = FALSE){
    $dm = new DatastoreManager();
    $id = $dm->saveDocument(array(
      'class' => $this->class,
      'datetime' => $this->datetime != null ? $this->datetime->format('Y-m-d\TH:i:s') : null,
      'key' => $this->key,
      'flag' => $this->flag,
      'data' => json_encode($this->data),
      'searchable' => $this->searchable,
      'tag' => $this->tag,
      'domain' => $this->domain,
    ), $this->id, $noFlush);
    $this->id = $id;
    unset($dm);
    return $id;
  }

  public function delete(){
    $dm = new DatastoreManager();
    $dm->delete($this->id);
    unset($dm);
  }

  public function toJSON(){
    $json = new \stdClass();
    foreach(get_class_vars(static::class) as $field => $null){
      if(property_exists($this, $field) && $this->{$field} != null){
        $json->{$field} = $this->{$field};
      }
    }
    return json_encode($json);
  }


}