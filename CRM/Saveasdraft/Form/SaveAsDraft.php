<?php

use CRM_Saveasdraft_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Saveasdraft_Form_SaveAsDraft extends CRM_Core_Form {

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {

    $sql = "SELECT * FROM civicrm_save_as_draft ic";
    $result = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

    $params = array();
    while ($result->fetch()) {
      $params[$result->param_name] = $result->param_value;
    }
    
    // Populate the setting with the saved settings from the db
    $defaults = array(
      'selected_status' => isset($params['selected_status']) ? $params['selected_status'] : '',
    );

    $this->setDefaults($defaults);

    $this->add('select',
    'selected_status',
    E::ts('Select the status to set after Draft'),
    $this->getActivityStatusOptions(),
    FALSE,
    ['class' => 'crm-select2', 'placeholder' => "- select -"]);
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ],
    ]);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $postedVals = array(
      'selected_status' => null,
    );

    $values = $this->exportValues();

    $values['selected_status'] = is_array($values['selected_status']) 
        ? implode(',', $values['selected_status']) 
        : (string)$values['selected_status'];

    $postedVals['selected_status'] = $values['selected_status'];

    $sql =  "TRUNCATE TABLE civicrm_save_as_draft";
    CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

    foreach ($postedVals as $key => $value) {
      $sql =  "INSERT INTO civicrm_save_as_draft(param_name, param_value) VALUES('$key', '$value')";
      CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);
    }

    // Notify the user of success
    CRM_Core_Session::setStatus(E::ts('Your settings have been saved.'), '', 'success'); 
    parent::postProcess();
  }

  /**
   * Fetch all activity statuses from CiviCRM.
   *
   * @return array
   */
  public function getActivityStatusOptions(): array {
    // Fetch activity status options using CiviCRM API
    $result = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => 'activity_status',
      'options' => ['limit' => 0],
    ]);

    // Build options array for multi-select field
    $options = [];
    foreach ($result['values'] as $status) {
      $options[$status['value']] = $status['label'];
    }

    return $options;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames(): array {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
