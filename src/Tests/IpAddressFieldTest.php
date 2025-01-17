<?php

namespace Drupal\field_ipaddress\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests IP address field functionality.
 *
 * @group field_ipaddress
 */
class IpAddressFieldTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'entity_test', 'field_ipaddress', 'field', 'field_ui');

  /**
   * Field name
   *
   * @var string
   */
  protected $field_name = 'field_testip';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(array(
      'access content',
      'view test entity',
      'administer entity_test content',
      'administer entity_test form display',
      'administer content types',
      'administer node fields',
    ));
    $this->drupalLogin($web_user);

    $this->fieldStorage = FieldStorageConfig::create(array(
      'field_name' => $this->field_name,
      'entity_type' => 'entity_test',
      'type' => 'ipaddress',
      'settings' => array(),
    ));
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $this->field->save();

    // Create a form display for the default form mode.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent($this->field_name, array(
        'type' => 'ipaddress_default',
      ))
      ->save();
  }

  /**
   * Tests date field functionality.
   */
  function testIpAddressField() {
    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$this->field_name}[0][value]", '', "IP address element found ({$this->field_name}[0][value])");

    // First put in buggy data
    $edit = array(
      "{$this->field_name}[0][value]" => 'A255.255.255.255 - 255.255.255.255',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText('Please provide a valid', 'Buggy input has been caught.');

    // Second, put in too big span
    $edit = array(
      "{$this->field_name}[0][value]" => '1.1.1.1 - 255.255.255.255',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText('Please make sure', 'To big IP range has been caught.');

    // Put in some data
    $edit = array(
      "{$this->field_name}[0][value]" => '255.255.255.255 - 255.255.255.255',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));


    preg_match('|entity_test/manage/(\d+)|', $this->url, $match);
    $this->assert(isset($match[1]), "URL matched after entity form submission ({$this->url})");
    $id = $match[1];
    $this->assertText(t('entity_test @id has been created.', array('@id' => $id)));
    $this->assertText($value, 'Data is present on page after form submission.');

    // Make sure we can query the data

    $greater_than_value = '255.255.255.254';
    $query = \Drupal::entityQuery('entity_test')
      ->condition($this->field_name . '.ip_from', $greater_than_value, '>');

    $nids = $query->execute();
    $this->assert((count($nids) == 1), 'Entity query matches number of created entities.');
  }
}
