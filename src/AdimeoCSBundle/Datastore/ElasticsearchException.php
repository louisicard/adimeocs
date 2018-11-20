<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 15/11/2018
 * Time: 17:22
 */

namespace AdimeoCSBundle\Datastore;


class ElasticsearchException extends \Exception
{
  public function __construct($message, $code = 0, \Exception $previous = null) {
    parent::__construct($message, $code, $previous);
  }

  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

}