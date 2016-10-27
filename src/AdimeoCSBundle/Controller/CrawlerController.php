<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 21/10/2016
 * Time: 17:02
 */

namespace AdimeoCSBundle\Controller;


use AdimeoCSBundle\Callback\Callback;
use AdimeoCSBundle\Crawl\DomainCrawler;
use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Process\Process;

class CrawlerController extends Controller
{
  /**
   * @Route("/", name="homepage")
   */
  public function indexAction(Request $request)
  {

    $dm = new DatastoreManager();
    $dm->init();

    $items = DatastoreItem::loadByProperties(array(
      'flag' => DomainCrawler::CRAWL_SIGNATURE_FLAG
    ));

    $info = array();
    foreach($items as $item){
      $pids = '';
      exec('ps aux | grep -i "' . $item->getKey() . '" | grep -v "grep"', $pids);
      $owner = NULL;
      if(!empty($pids)){
        $raw = preg_split('/[ ]+/', $pids[0]);
        if(count($raw) > 0){
          $owner = $raw[0];
        }
      }
      $info[] = array(
        'item' => $item,
        'running' => !empty($pids),
        'owner' => $owner
      );
    }

    global $kernel;
    $callbacks = $kernel->getContainer()->getParameter('adimeocs.callbacks');
    $callbackChoices = array(
      'Select a callback type' => ''
    );
    foreach($callbacks as $callback){
      $callbackChoices[$callback] = $callback;
    }

    $form = $this->createFormBuilder()
      ->add('domain', TextType::class, array(
        'label' => 'Domain',
        'required' => true,
      ))
      ->add('scheme', TextType::class, array(
        'label' => 'Scheme',
        'required' => true,
      ))
      ->add('authorizedDomains', TextType::class, array(
        'label' => 'Authorized domains (comma separated)',
        'required' => false,
      ))
      ->add('maxPages', NumberType::class, array(
        'label' => 'Maximum pages to crawl',
        'data' => -1,
        'required' => false,
      ))
      ->add('callback', ChoiceType::class, array(
        'multiple' => false,
        'expanded' => false,
        'choices' => $callbackChoices,
        'required' => true
      ));
    if($request->get('callback') != null || isset($request->get('form')['callback'])){
      $currentCallback = $request->get('callback') != null ? $request->get('callback') != null : $request->get('form')['callback'];
      foreach($callbacks as $callback){
        if($callback == $currentCallback){
          $obj = $kernel->getContainer()->get($callback);
          foreach($obj->getSettingsFields() as $field){
            $form = $form->add($field, TextType::class, array(
              'label' => 'Callback settings field "' . $field . '"',
              'required' => true
            ));
          }
          break;
        }
      }
    }
    $form = $form
      ->add('submit', SubmitType::class, array(
        'label' => 'Test'
      ))->getForm();

    $form->handleRequest($request);

    if($form->isValid()){
      $dc = new DomainCrawler('', '', '');
      $jsonFile = tempnam(sys_get_temp_dir(), 'adimeocs');
      $data = $form->getData();
      if(isset($data['callback'])){
        $obj = $kernel->getContainer()->get($callback);
        $data['callback'] = get_class($obj);
      }
      if(isset($data['authorizedDomains'])){
        $authorizedDomains = array_map('trim', explode(',', $data['authorizedDomains']));
        $data['authorizedDomains'] = $authorizedDomains;
      }
      file_put_contents($jsonFile, json_encode($data));
      $cmd = $dc->getCommand() . ' < ' . $jsonFile;
      popen($cmd . ' &', 'w');
      usleep(1000 * 1000);
      unlink($jsonFile);
      return $this->redirectToRoute('homepage');
    }

    // replace this example code with whatever you need
    return $this->render('AdimeoCSBundle::default/index.html.twig', [
      'info' => $info,
      'form' => $form->createView(),
      'formAjaxCallback' => $this->generateUrl('homepage')
    ]);
  }

  /**
   * @Route("/kill/{pid}", name="acs_kill")
   */
  public function killAction(Request $request, $pid){
    exec('kill -9 ' . $pid);
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