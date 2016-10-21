<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 21/10/2016
 * Time: 17:02
 */

namespace AdimeoCSBundle\Controller;


use AdimeoCSBundle\Crawl\DomainCrawler;
use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class CrawlerController extends Controller
{
  /**
   * @Route("/", name="homepage")
   */
  public function indexAction(Request $request)
  {

    $items = DatastoreItem::loadByProperties(array(
      'flag' => DomainCrawler::CRAWL_SIGNATURE_FLAG
    ));

    $info = array();
    foreach($items as $item){

      $info[] = array(
        'item' => $item,
        'running' => posix_kill($item->getKey(), 0)
      );
    }

    // replace this example code with whatever you need
    return $this->render('default/index.html.twig', [
      'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..') . DIRECTORY_SEPARATOR,
      'info' => $info
    ]);
  }

  /**
   * @Route("/kill/{pid}", name="acs_kill")
   */
  public function killAction(Request $request, $pid){
    posix_kill($pid, 9);
    return $this->redirectToRoute('homepage');
  }

  /**
   * @Route("/cleanup/{tag}", name="acs_cleanup")
   */
  public function cleanupAction(Request $request, $tag){

    $sig = DatastoreItem::loadByProperties(array(
      'flag' => DomainCrawler::CRAWL_SIGNATURE_FLAG,
      'tag' => $tag
    ));
    if(count($sig) > 0){
      $sig[0]->delete();
    }

    $items = DatastoreItem::loadByProperties(array(
      'tag' => $tag
    ));
    $dm = new DatastoreManager();
    $dm->bulkDelete($items);

    return $this->redirectToRoute('homepage');
  }

}