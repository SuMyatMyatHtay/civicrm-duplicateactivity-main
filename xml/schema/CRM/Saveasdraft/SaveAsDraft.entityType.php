<?php
// This file declares a new entity type. For more details, see "hook_civicrm_entityTypes" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
return [
  [
    'name' => 'SaveAsDraft',
    'class' => 'CRM_Saveasdraft_DAO_SaveAsDraft',
    'table' => 'civicrm_save_as_draft',
  ],
];
