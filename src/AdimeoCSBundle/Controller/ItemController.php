<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 23/05/2018
 * Time: 14:37
 */

namespace AdimeoCSBundle\Controller;


use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends Controller
{

  /**
   * @Route("/details/{id}", name="item_details")
   */
  public function itemDetailsAction(Request $request, $id) {

    $dm = new DatastoreManager();
    $dm->init();

    $item = $dm->getItemById($id);
    $pids = '';
    exec('ps aux | grep -i "' . $item['key'] . '" | grep -v "grep"', $pids);
    $owner = NULL;
    if(!empty($pids)){
      $raw = preg_split('/[ ]+/', $pids[0]);
      if(count($raw) > 0){
        $owner = $raw[0];
      }
    }
    $info = array(
      'item' => $item,
      'running' => !empty($pids),
      'owner' => $owner
    );
    dump($info);
    dump(json_decode($item["data"], TRUE));
    dump($dm->getCrawlStats($item['tag']));

    return $this->render('AdimeoCSBundle::default/details.html.twig', [
      'info' => $info,
      'data' => json_decode($item["data"], TRUE),
      'stats' => $dm->getCrawlStats($item['tag'])
    ]);

  }

}