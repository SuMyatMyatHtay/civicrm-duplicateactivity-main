<?php

require_once 'duplicateactivity.civix.php';

$SaveAsDraftClicked = FALSE;
$SaveClicked = FALSE;

use CRM_duplicateactivity_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function duplicateactivity_civicrm_config(&$config): void
{
  _duplicateactivity_civix_civicrm_config($config);
}

/**
 * Lifecycle hook :: install().
 * Implements hook_civicrm_install().
 * 
 * Draft Activity Status will be added if it exists.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function duplicateactivity_civicrm_install(): void
{
  _duplicateactivity_civix_civicrm_install();
}

/**
 * Lifecycle hook :: uninstall().
 * Implements hook_civicrm_uninstall().
 * 
 * Draft Activity Staus will be deleted if it exists.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function duplicateactivity_civicrm_uninstall(): void
{
  _duplicateactivity_civix_civicrm_uninstall();
}

/**
 * Lifecycle hook :: enable().
 * Implements hook_civicrm_enable().
 *
 * Draft Status will be enabled.
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function duplicateactivity_civicrm_enable(): void
{
  _duplicateactivity_civix_civicrm_enable();
}

/**
 * Lifecycle hook :: disable().
 * Implements hook_civicrm_disable().
 *
 * Draft Status will be disabled.
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function duplicateactivity_civicrm_disable(): void
{
  _duplicateactivity_civix_civicrm_disable();
}

function getDraftStatusID()
{
  $optionGroup = civicrm_api4('OptionGroup', 'get', [
    'select' => [
      'id',
    ],
    'where' => [
      ['title', '=', 'Activity Status'],
    ],
    'checkPermissions' => FALSE,
  ], 0);

  $ActivityStatusGroupID = $optionGroup['id'];

  $optionValues = civicrm_api4('OptionValue', 'get', [
    'select' => [
      'value',
    ],
    'where' => [
      ['option_group_id', '=', $ActivityStatusGroupID],
      ['label', '=', 'Draft'],
    ],
    'checkPermissions' => FALSE,
  ], 0);

  return $optionValues['value'];
}

function getSelectedStatusID()
{
  $settings = [];

  $sql = "SELECT * FROM civicrm_save_as_draft";
  $result = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

  while ($result->fetch()) {
    // Store each setting in the associative array.
    $settings[$result->param_name] = $result->param_value;
  }

  return $settings['selected_status'];
}

/**
 * Function to duplicate an activity.
 *
 * @param int $originalActivityId The ID of the original activity to duplicate.
 */
function duplicateActivity($originalActivityId)
{
  // Fetch all custom field names from civicrm_custom_field table
  $customGroupResult = civicrm_api4('CustomGroup', 'get', [
    'select' => ['name'],
    'checkPermissions' => FALSE,
  ]);

  $customSetNames = [];
  foreach ($customGroupResult as $customField) {
    $customSetNames[] = $customField['name'];
  }

  // Log the API response for debugging
  // Civi::log()->debug("Custom Fields API Response: " . print_r($customGroupResult, TRUE));
  // Civi::log()->debug("Custom Fields Result: " . print_r($customSetNames, TRUE));


  // Get the details of the original activity.
  $originalActivityResult = civicrm_api4('Activity', 'get', [
    'select' => [
      '*',
      'source_contact_id',
      'target_contact_id',
      'assignee_contact_id',
      'custom.*',
    ],
    'where' => [
      ['id', '=', $originalActivityId],
    ],
    'checkPermissions' => FALSE,
  ], 0);

  // Log the API response for debugging
  // civi::log()->debug("Original Activity API Response: " . print_r($originalActivityResult, TRUE));

  // Extract the original activity data.
  $originalActivity = $originalActivityResult->getArrayCopy();

  // Check if the original activity data is available
  /* if (!$originalActivity) {
    CRM_Core_Session::setStatus(ts('Original activity not found.'), ts('Error'), 'error');
    return;
  } */

  // Check and log the structure of the originalActivity
  //Civi::log()->debug("Original Activity Data Structure", ['data' => $originalActivity]);
  if (empty($originalActivity)) {
    CRM_Core_Session::setStatus(ts('Original activity not found.'), ts('Error'), 'error');
    return;
  }

  //$originalActivity = $originalActivity['values'][0];

  // Get the source contact ID/ target contact ID from the original activity.
  $sourceContactId = $originalActivity['source_contact_id'];
  $targetContactId = $originalActivity['target_contact_id'];
  $assigneeContactId = $originalActivity['assignee_contact_id'];
  // civi::log()->debug("Source Contact ID: " . $sourceContactId);
  // civi::log()->debug("Target Contact ID: " . print_r($targetContactId, TRUE));
  // civi::log()->debug("Assignee Contact ID: " . $assigneeContactId);

  // Prepare custom field values for the new activity
  $customFieldValues = [];
  // civi::log()->debug("originalActivity Values: ", ['data' => $originalActivity]);

  foreach ($originalActivity as $fieldName => $fieldValue) {
    //this is checking whether there are custom fees in that activity : Su 
    // if (strpos($fieldName, 'custom_') === 0) {
    //   $customFieldValues[$fieldName] = $fieldValue;
    // }
    foreach ($customSetNames as $customSetName) {
      if (strpos($fieldName, $customSetName) === 0) {
        $customFieldValues[$fieldName] = $fieldValue;
        break;
      }
    }
  }
  // civi::log()->debug("Custom Field Values: ", ['data' => $customFieldValues]);
  // civi::log()->debug("Attachment response : " . $originalActivity['attachment']);
  // Create a new activity using the details of the original activity.
  $newActivityValues = [
    'activity_type_id' => $originalActivity['activity_type_id'],
    'subject' => $originalActivity['subject'],
    'details' => $originalActivity['details'],
    'location' => $originalActivity['location'],
    'duration' => $originalActivity['duration'],
    'priority_id' => $originalActivity['priority_id'],
    'campaign_id' => $originalActivity['campaign_id'],
    'attachment' => $originalActivity['attachment'],
    'activity_date_time' => $originalActivity['activity_date_time'],
    'phone_id' => $originalActivity['phone_id'],
    'phone_number' => $originalActivity['phone_number'],
    'status_id' => $originalActivity['status_id'],
    'parent_id' => $originalActivity['parent_id'],
    'is_current_revision' => $originalActivity['is_current_revision'],

    'source_contact_id' => $sourceContactId,
    'target_contact_id' => $targetContactId,
    'assignee_contact_id' => $assigneeContactId,

    // Include custom fields in the new activity creation
    // 'custom' => $customFieldValues,
  ];
  $newActivityValues = array_merge($newActivityValues, $customFieldValues);

  $newActivity = civicrm_api4('Activity', 'create', [
    'values' => $newActivityValues,
    'checkPermissions' => TRUE,
  ]);

  // Log the new activity response for debugging.
  // civi::log()->debug("New Activity API Response: " . print_r($newActivity, true));
  $newActivityResult = $newActivity->first();
  $newActivityId = $newActivityResult['id'];
  // civi::log()->debug("newActivityId : " . $newActivityId);

  try {
    //Checking which case is the activity under from
    $caseActivityResult = civicrm_api4('CaseActivity', 'get', [
      'select' => ['case_id'],
      'where' => [
        ['activity_id', '=', $originalActivityId],
      ],
      'checkPermissions' => FALSE,
    ], 0);

    // Log the API response for debugging
    //civi::log()->debug("caseActivityResult: " . print_r($caseActivityResult, TRUE));
    // civi::log()->debug("caseActivityResult caseID heehee: " . $caseActivityResult['case_id']);
    $caseId = $caseActivityResult['case_id'];
    // civi::log()->debug("caseId : " . $caseId);



    $caseActivityLinkResult = civicrm_api4('CaseActivity', 'create', [
      'values' => [
        'case_id' => $caseId,
        'activity_id' => $newActivityId,
      ]
    ]);
    // civi::log()->debug("Case Activity Link Response : " . print_r($caseActivityLinkResult, TRUE));

  } catch (Exception $e) {
    // civi::log()->debug("No CaseActivity found for the original activity.");
  }
  CRM_Core_Session::setStatus(ts('Activity is duplicated.'), ts('Success'), 'success');

  /* if (!empty($newActivity['id'])) {
    // Redirect or do something else after successfully duplicating the activity.
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/activity/view', "action=view&id={$newActivity['id']}&reset=1"));
  } else {
    // Handle error if the duplication fails.
    CRM_Core_Session::setStatus(ts('Failed to duplicate activity.'), ts('Error'), 'error');
  } */
}

/**
 * Implementation of hook_civicrm_buildForm
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function duplicateactivity_civicrm_buildForm($formName, &$form)
{
  $editContactActivity = $formName == 'CRM_Activity_Form_Activity' && $form->getAction() == CRM_Core_Action::UPDATE;
  $addContactActivity = $formName == 'CRM_Activity_Form_Activity' && $form->getAction() == CRM_Core_Action::ADD;
  $editCaseActivity = $formName == 'CRM_Case_Form_Activity' && $form->getAction() == CRM_Core_Action::UPDATE;
  $addCaseActivity = $formName == 'CRM_Case_Form_Activity' && $form->getAction() == CRM_Core_Action::ADD;

  if ($editContactActivity || $addContactActivity || $editCaseActivity || $addCaseActivity) {
    // $message = array(
    //   'info' => ts('This is a custom message to display.'),
    // );

    // $js = array(
    //   'onclick' => "alert(" . json_encode($message['info']) . "); return false;"
    // );  
    foreach ($form->_elementIndex as $element => $index) {
      if ($element == 'buttons') {
        if (
          $form->_elements[$index]->_elements['0']->_attributes['value'] != 'Delete' &&
          $form->_action != 4
        ) {
          $form->addButtons(
            array(
              array(
                'type' => 'upload',
                'name' => ts('Save'),
                'isDefault' => TRUE
              ),
              array(
                'type' => 'upload',
                'name' => ts('Save as Draft'),
                'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
                'subName' => 'draft',
                'icon' => 'fa-floppy-o'
                // 'js' => $js,
              ),
              array(
                'type' => 'upload',
                'name' => ts('Duplicate'),
                'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
                'subName' => 'duplicate',
                'icon' => 'fa-copy'
              ),
              array(
                'type' => 'cancel',
                'name' => ts('Cancel')
              )
            )
          );
        }
      }
    }

    // Set a GLOBAL that tells the hook_civicrm_pre() if the Save as Draft/ "Duplicate" button has been clicked
    $buttonName = $form->controller->getButtonName();
    if ($buttonName == $form->getButtonName('upload', 'draft')) {
      global $SaveAsDraftClicked;
      $SaveAsDraftClicked = TRUE;
    } elseif ($buttonName == $form->getButtonName('upload')) {
      global $SaveClicked;
      $SaveClicked = TRUE;
    } elseif ($buttonName == $form->getButtonName('upload', 'duplicate')) {
      // Get the original activity ID dynamically.
      $originalActivityId = $form->getVar('_activityId');
      CRM_Core_Session::setStatus("Original Activity ID: $originalActivityId"); // Add this line for debugging.
      if ($originalActivityId) {
        // Call the duplicateActivity function with the dynamic ID.
        duplicateActivity($originalActivityId);
      } else {
        // Handle error if the original activity ID is not available.
        CRM_Core_Session::setStatus(ts('Original activity ID not found.'), ts('Error'), 'error');
      }
    }
  }

  if ($editContactActivity || $addContactActivity) {
    Civi::resources()->addScriptFile('saveasdraft', 'disable.js');
  }
}

/**
 * Implements hook_civicrm_pre().
 *
 * @param string $op
 * @param string $objectName
 * @param int $id
 * @param array $params
 */
function duplicateactivity_civicrm_pre($op, $objectName, $id, &$params)
{
  global $SaveAsDraftClicked;
  global $SaveClicked;
  global $DuplicateClicked;

  if ($op == 'create' && $objectName == 'Activity') {
    if ($SaveAsDraftClicked == TRUE) {
      $statusID = getDraftStatusID();
      // Clear assignee_contact_id if "Save as Draft" button is clicked
      $params['assignee_contact_id'] = "";
      $params['status_id'] = $statusID;
    } elseif ($SaveClicked == TRUE && $params['status_id'] == getDraftStatusID()) {
      $SelectedStatusID = getSelectedStatusID();
      $params['status_id'] = $SelectedStatusID;
    }
  } elseif ($op == "edit" && $objectName == 'Activity') {
    if ($SaveAsDraftClicked == TRUE) {
      $statusID = getDraftStatusID();
      // Clear assignee_contact_id if "Save as Draft" button is clicked
      $params['assignee_contact_id'] = "";
      $params['status_id'] = $statusID;
    } elseif ($SaveClicked == TRUE && $params['status_id'] == getDraftStatusID()) {
      $SelectedStatusID = getSelectedStatusID();
      $params['status_id'] = $SelectedStatusID;
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function duplicateactivity_civicrm_navigationMenu(&$menu)
{
  _duplicateactivity_civix_insert_navigation_menu(
    $menu,
    'Administer/System Settings',
    array(
      'label' => ts('Save as Draft Settings'),
      'name' => 'save_as_draft',
      'url' => 'civicrm/saveasdraft?reset=1',
      'permission' => 'administer CiviCRM',
      'operator' => 'OR',
      'separator' => 0,
    )
  );
  _duplicateactivity_civix_navigationMenu($menu);
}