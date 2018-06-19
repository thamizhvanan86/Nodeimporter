<?php

/**
 * @file
 * Contains \Drupal\pageimporter\Form\BaseUrlForm.
 */

namespace Drupal\pageimporter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class BaseUrlForm.
 *
 * @package Drupal\pageimporter\Form
 */
class BaseUrlForm extends ConfigFormBase {

  
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pageimporter.baseurl',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'baseurl_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pageimporter.baseurl');
    $form['baseurl'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('baseurl'),
      '#description' => $this->t(''),
      '#default_value' => $config->get('baseurl'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('pageimporter.baseurl')
      ->set('baseurl', $form_state->getValue('baseurl'))
      ->save();

    $client = \Drupal::httpClient();
    $baseurl = $this->config('pageimporter.baseurl')->get('baseurl');

    try {
      $response = \Drupal::httpClient()->get($baseurl, array('headers' => array('Accept' => 'text/plain')));
      $data = $response->getBody();

      if (empty($data)) {
        drupal_set_message('Empty response.');
      }
      else {
        $this->createPage($data);
      }
    }
    catch (RequestException $e) {
      watchdog_exception('pageimporter', $e);
    }
  }

  /**
   * Create nodes from JSON feed.
   */
  public static function createPage() {
    
    $baseurl = \Drupal::configFactory()->getEditable('pageimporter.baseurl')->get('baseurl');    
    if(!empty($baseurl)){
        $json = file_get_contents($baseurl);
        $jsonout = json_decode($json, TRUE);

        //$nodes = \Drupal\node\Entity\Node::loadMultiple();
        
        $result = \Drupal::entityQuery('node')
          ->condition('type', 'candidatura_talent_clue')
          ->execute();
        $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
        $entities = $storage_handler->loadMultiple($result);
        $storage_handler->delete($entities);
           
        foreach ($jsonout as $art => $array2) 
        {

          foreach($array2 as $key => $value)
          {  
            $jobcode = '';

            if(empty($value['job_code']))
            {
              $jobcode = "No especificado";
            }
            else
            {
              $jobcode = $value['job_code']; 
            }

            $query = \Drupal::entityQuery('taxonomy_term');
            $term = $query
            ->condition('vid',array('paises_candidaturas','disciplinas_candidaturas','candidatura_industria','listado_de_codigo_iso','moneda_candidaturas'), 'IN')
            ->condition('name',array($value['country'],$jobcode,$value['industry'],$value['country_iso'],$value['salary_currency']), 'IN')
            ->execute(); 
            $taxonomy_terms = array();
            
              foreach($term as $term_id) 
              {
                $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term_id);
                $term_name = $terms->getName();
                //$terms->setName($term_name);
                //$terms->save();
                $taxonomy_terms[] = array('id' => $term_id, 'terms' => $term_name);

              }

              foreach ($taxonomy_terms as $tax) {
                
                               
                if($tax['terms']===$value['country'])
                {
                  $country = $tax['terms'];
                  $ctid = $tax['id'];
                }
                if($tax['terms']===$jobcode)
                {
                  $discipline = $tax['terms'];
                  $did = $tax['id'];
                }
                if($tax['terms']===$value['industry'])
                {
                  $industry = $tax['terms'];
                  $iid = $tax['id'];
                }
                if($tax['terms']===$value['country_iso'])
                {
                  $country_iso = $tax['terms'];
                  $isoid = $tax['id'];
                }
                if($tax['terms']===$value['salary_currency'])
                {
                  $salary_currency = $tax['terms'];
                  $sid = $tax['id'];
                }
                
              }
              
              if($country!=$value['country'])
              {
                $country = Term::create([ 'name' => $value['country'], 'vid' => 'paises_candidaturas'])->save();
              }
              if($discipline!=$value['discipline'])
              {
                $discipline = Term::create([ 'name' => $jobcode, 'vid' => 'disciplinas_candidaturas'])->save() ;
              }
              if($industry!=$value['industry'])
              {
                $industry = Term::create([ 'name' => $value['industry'], 'vid' => 'candidatura_industria'])->save();
              }
              if ($country_iso!=$value['country_iso']) 
              {
                $country_iso = Term::create([ 'name' => $value['country_iso'], 'vid' => 'listado_de_codigo_iso'])->save();  
              }
              if ($salary_currency!=$value['salary_currency'])             
              {
                $salary_currency = Term::create([ 'name' => $value['salary_currency'], 'vid' => 'moneda_candidaturas'])->save();
              }
              
         
              $date = str_replace('/', '-', $value['date']);
              $dt = date("Y-m-d", strtotime($date) );
              $cid = substr($value['url'], 39, 8);

                $node = Node::create(array(
                'type' => 'candidatura_talent_clue',
                'format' => 'full_html',
                'field_candidatura_referencia' => $cid,
                'field_candidatura_fuente' => $value['url'],
                'field_candidatura_fuente' => $value['apply_url'],
                'field_candidatura_fecha_public' => $dt,
                'body' => [
                  'value' => $value['body'],
                  'format' => 'full_html',
                ],
                'field_candidatura_ubicacion' => $value['city'],
                'field_candidatura_contrato_tipo' => $value['contract'],
                'field_candidaturas_disciplina' => [['target_id' => $did, 'name' => $discipline]],
                'field_disciplina_con_bg' => [['target_id' => $did, 'name' => $discipline]],
                'field_disciplina_icono' => [['target_id' => $did, 'name' => $discipline]],
                'field_candidatura_industria' => [['target_id' => $iid, 'name' => $industry]],
                'field_candidatura_requermientos' => [
                  'value' => $value['requirements'],
                  'format' => 'full_html',
                ],
                'field_candidatura_salario' => $value['salary'],
                'field_salario' => $value['salary'],   
                'field_candidatura_vacantes' => $value['vacancy'],
                'field_candidatura_pais' => [['target_id' => $ctid, 'name' => $country]],
                'title' => $value['title'],
                'field_candidatura_iso' => [['target_id' => $isoid, 'name' => $country_iso]],
                'field_candidatura_moneda' => [['target_id' => $sid, 'name' => $salary_currency]],
                //'field_casos_de_estudio_img' => 'image',
                ));
                 //$node = node_submit($node);

                 $node->save();
                 //$node->field_candidatura_iso->target_id = $isoid;
                 //$node->field_candidatura_moneda->target_id = $sid;
                 //$node->save();
                 
              if (isset($node)) 
              {
                 drupal_set_message('Successfully Inserted.');
              }   
              
          } 

      }
  
  }
    
    die();              
         
  }
    
  }
 

