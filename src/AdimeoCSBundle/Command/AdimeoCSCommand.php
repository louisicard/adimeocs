<?php

namespace AdimeoCSBundle\Command;

use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class AdimeoCSCommand extends ContainerAwareCommand
{

  protected function configure() {
    $this
      ->setName('acs:test')
      ->setDescription('Testing Adimeo Crawling Services')
    ;
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    (new DatastoreManager())->init();
    /*
    $bin = PHP_BINARY;
    if(!is_executable($bin)){
      $bin = PHP_BINDIR . '/php';
    }
    $console = __DIR__ . '/../../../bin/console';
    $cmd = '"' . $bin . '" "' . $console . '" acs:crawl';
    $process = new Process($cmd);
    $params = array(
      'domain' => 'www.core-techs.fr',
      'scheme' => 'http',
      'authorized_domains' => array('core-techs.fr', 'www.core-techs.fr'),
      'max' => -1
    );
    $process->setInput(json_encode($params));
    $process->start();
    $output->write('Je passe à autre chose');
    while($process->isRunning()){
      $ps = new Process('ps aux | grep '  .$process->getPid());
      $ps->run();
      $output->writeln($process->getPid());
      $output->writeln($ps->getOutput());
      usleep(100000);
    }
    /*$process->wait(function($type, $buffer){
      print($buffer . PHP_EOL);
      print ('je finis');
    });*/
    /*$item = new DatastoreItem();
    $item->setTag('je teste');
    $item->save();
    $items  = DatastoreItem::loadByProperties(array(
      'tag' => 'je teste'
    ));
    var_dump($items);
    */
    //$item = DatastoreItem::load('AVfUlYfDrmP_KDzm9bcT');
    //var_dump($item->toJSON());
    $str = '<p>17 avril 2016 par <a href="http://www.rogard.blog.sacd.fr/author/pascal-rogard/" title="Articles par Pascal Rogard" rel="author">Pascal Rogard</a>  - <a href="http://www.rogard.blog.sacd.fr/category/weblog/" rel="category tag">Weblog</a></p>';
    preg_match_all('/(?P<day>\d{1,2}) (?<month>[^ ]*) (?<year>\d{4})/', $str, $matches);
    if(isset($matches['day'][0]) && isset($matches['month'][0]) && isset($matches['year'][0])){
      $months = array(
        'janvier' => '01',
        'février' => '02',
        'mars' => '03',
        'avril' => '04',
        'mai' => '05',
        'juin' => '06',
        'juillet' => '07',
        'août' => '08',
        'septembre' => '09',
        'octobre' => '10',
        'novembre' => '11',
        'décembre' => '12'
      );
      $date = $matches['day'][0] . '/' . $months[$matches['month'][0]] . '/' . $matches['year'][0];
      var_dump($date);
    }
  }

}