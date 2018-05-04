<?php

use CRM_Gdpr_ExtensionUtil as E;
use CRM_Gdpr_CommunicationsPreferences_Utils as U;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Gdpr_Form_UpdatePreference extends CRM_Core_Form {
  protected $settings;
  protected $commPrefSettings;
  protected $commPrefGroupsetting;
  protected $channelEleNames;
  protected $groupEleNames;

  public $containerPrefix = 'enable_';

  //for Profile form validation
  public $_id;
  public $_gid;
  public $_context;
  public $_ruleGroupID;

  public function preProcess() {
    //Retrieve contact id from URL
    $this->_cid = $this->getContactID();
    
    //Do not allow anon users to update unless have a valid checksum
    if(empty($this->_cid)){
      //Do Nothing for now
    }

    //Add Gdpr CSS file
    CRM_Core_Resources::singleton()->addStyleFile('uk.co.vedaconsulting.gdpr', 'css/gdpr.css');
    parent::preProcess();
  }
  
  public function getSettings() {
    $this->settings    = U::getSettings();
    $this->commPrefSettings = $this->settings[U::SETTING_NAME];
    $this->commPrefGroupsetting   = $this->settings[U::GROUP_SETTING_NAME];
  }

  public function buildQuickForm() {

    //Get all Communication preference settings
    $this->getSettings();
    $this->_session = CRM_Core_Session::singleton();

    $this->assign('commPrefGroupsetting', $this->commPrefGroupsetting);

    if (!empty($this->commPrefSettings['profile'])) {
      $this->buildCustom($this->commPrefSettings['profile']);
    }

    //Display Page Title from settings
    if ($pageTitle = $this->commPrefSettings['page_title']) {
      CRM_Utils_System::setTitle(ts($pageTitle));
    }

    //Display Page intro from settings.
    if ($introText = $this->commPrefSettings['page_intro']) {
      $this->assign('page_intro', $introText);
    }

    //Check the channels are enabled ?
    $channelEleNames   = array();
    $isChannelsEnabled = $this->commPrefSettings['enable_channels'];
    if ($isChannelsEnabled) {
      //Display Page intro from settings.
      if ($channelIntro = $this->commPrefSettings['channels_intro']) {
        $this->assign('channels_intro', $channelIntro);
      }

      $commPrefOpGroup = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup', U::COMM_PREF_OPTIONS, 'id', 'name');
      $commPrefOptions = array('' => ts('--Select--')) + CRM_Core_BAO_OptionValue::getOptionValuesAssocArray($commPrefOpGroup);

      foreach ($this->commPrefSettings['channels'] as $key => $value) {
        if ($value) {
          $name  = str_replace($this->containerPrefix, '', $key);
          $label = ucwords(str_replace('_', ' ', $name));
          $this->add('select', $key, $label, $commPrefOptions, TRUE);
          $this->channelEleNames[] = $key; 
        }
      }
    }
    
    // export form elements
    $this->assign('channelEleNames', $this->channelEleNames);
    $this->assign('containerPrefix', $this->containerPrefix);

    //Communication preference Group settings enabled ?
    $isGroupSettingEnabled = $this->commPrefSettings['enable_groups'];
    if ($isGroupSettingEnabled) {
      
      if ($groupsHeading = $this->commPrefSettings['groups_heading']) {
        $this->assign('groups_heading', $groupsHeading);
      }
      
      if ($groupsIntro = $this->commPrefSettings['groups_intro']) {
        $this->assign('groups_intro', $groupsIntro);
      }      

      //all for all groups and disable checkbox is group_enabled from settings
      $groups = U::getGroups();
      foreach ($groups as $group) {
        $container_name = 'group_' . $group['id'];
        if (!empty($this->commPrefGroupsetting[$container_name]['group_enable'])) {
          $title = $this->commPrefGroupsetting[$container_name]['group_title'];
          $groupsFromSettings[$title] = $group['id'];
          $this->add('Checkbox', $container_name, $title);
          $this->groupEleNames[] = $container_name;
        }
      }
    }
    
    //GDPR Terms and conditions
    //if already accepted then we dont this link at all
    $isContactDueAcceptance = empty($this->_cid) ?  TRUE : CRM_Gdpr_SLA_Utils::isContactDueAcceptance($this->_cid);
    if ($gdprTermsConditionsUrl = CRM_Gdpr_SLA_Utils::getTermsConditionsUrl()) {
      $this->assign('gdprTcURL', $gdprTermsConditionsUrl);
    }    
    if ($gdprTermsConditionslabel = CRM_Gdpr_SLA_Utils::getLinkLabel()) {
      $this->assign('gdprTcLabel', $gdprTermsConditionslabel);
    }
    if ($isContactDueAcceptance) {
      $termsConditionsField = $this->getTermsAndConditionFieldId();
      
      $tcFieldName  = 'custom_'.$termsConditionsField;
      $tcFieldlabel = sprintf("I have read and agree to the <a href='%s' target='_blank'>%s</a>"
        , $gdprTermsConditionsUrl
        , $gdprTermsConditionslabel
      );
      
      $this->assign('tcFieldlabel', $tcFieldlabel);
      $this->assign('tcFieldName', $tcFieldName);
      $this->assign('isContactDueAcceptance', $isContactDueAcceptance);
      
      $this->add('checkbox', $tcFieldName, $gdprTermsConditionslabel, NULL, TRUE);
    }
    else {
      $accept_activity = CRM_Gdpr_SLA_utils::getContactLastAcceptance($this->_cid);
      $accept_date = '';
      if (!empty($accept_activity['activity_date_time'])) {
        $accept_date = date('d/m/Y', strtotime($accept_activity['activity_date_time']));
      }
      $tcFieldlabel = sprintf("Here is our <a href='%s' target='_blank'>%s</a>, which you agreed to on %s."
        , $gdprTermsConditionsUrl
        , $gdprTermsConditionslabel
        , $accept_date
      );
      $this->assign('tcFieldlabel', $tcFieldlabel);
    }

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('groupEleNames', $this->groupEleNames);

    //add form rule 
    $this->addFormRule(array('CRM_Gdpr_Form_UpdatePreference', 'formRule'), $this);
    $this->addFormRule(array('CRM_Profile_Form', 'formRule'), $this);

    parent::buildQuickForm();
  }

  /**
   * Add the custom fields.
   *
   * @param int $id
   * @param string $name
   * @param bool $viewOnly
   */
  public function buildCustom($id, $name = 'custom_pre', $viewOnly = FALSE) {
    if ($id) {
      //For Profile form validation
      $this->_gid = $id;
      $dao = new CRM_Core_DAO_UFGroup();
      $dao->id = $id;
      if ($dao->find(TRUE)) {
        $this->_isUpdateDupe = 1;
        // $this->_isUpdateDupe = $dao->is_update_dupe;
        $this->_isAddCaptcha = $dao->add_captcha;
        $this->_ufGroup = (array) $dao;
      }

      $button = substr($this->controller->getButtonName(), -4);
      $contactID = $this->_cid;

      if ($contactID) {
        CRM_Core_BAO_UFGroup::filterUFGroups($id, $contactID);
      }
      
      $fields = CRM_Core_BAO_UFGroup::getFields($id, FALSE, CRM_Core_Action::ADD,
        NULL, NULL, FALSE, NULL,
        FALSE, NULL, CRM_Core_Permission::CREATE,
        'field_name', TRUE
      );

      $addCaptcha = FALSE;

      $this->assign($name, $fields);
      if (is_array($fields)) {
        foreach ($fields as $key => $field) {
          if ($viewOnly &&
            isset($field['data_type']) &&
            $field['data_type'] == 'File' || ($viewOnly && $field['name'] == 'image_URL')
          ) {
            // ignore file upload fields
            continue;
          }
          //make the field optional if primary participant
          //have been skip the additional participant.
          if ($button == 'skip') {
            $field['is_required'] = FALSE;
          }
          // CRM-11316 Is ReCAPTCHA enabled for this profile AND is this an anonymous visitor
          elseif ($field['add_captcha'] && !$contactID) {
            // only add captcha for first page
            $addCaptcha = TRUE;
          }
          $this->_mode = $contactID ? CRM_Profile_Form::MODE_EDIT : CRM_Profile_Form::MODE_CREATE;
          CRM_Core_BAO_UFGroup::buildProfile($this, $field, $this->_mode, $contactID, TRUE);

          $this->_fields[$key] = $field;
        }
      }

      if ($addCaptcha && !$viewOnly) {
        $captcha = CRM_Utils_ReCAPTCHA::singleton();
        $captcha->add($this);
        $this->assign('isCaptcha', TRUE);
      }
    }
  }

  public static function formRule($fields, $files, $self){
    $errors = array();

    foreach ($self->groupEleNames as $groupName => $groupEleName) {
      $channelArray = $groupChannelAray = array();

      //get the channel array and group channel array
      foreach ($self->channelEleNames as $channel) {
        $groupChannel = str_replace($self->containerPrefix, '', $channel);
        $channelSettingValue = $self->commPrefGroupsetting[$groupEleName][$groupChannel];
        
        if (!is_null($channelSettingValue) && $channelSettingValue != '') {
          $channelArray[$groupChannel] = ($fields[$channel] == 'YES') ? 1 : 0;
          $groupChannelAray[$groupChannel] = empty($self->commPrefGroupsetting[$groupEleName][$groupChannel]) ? 0 : 1;
        }
      }

      //check any difference then return as error
      if(!empty($fields[$groupEleName]) && ($diff = array_diff_assoc($groupChannelAray, $channelArray))){
        //do something here.
        $diff = implode(', ', array_keys($diff));
        $errors[$groupEleName] = ts("Communication Preferences {$diff} has to be selected for this group");
      }
    }

    return empty($errors) ? TRUE : $errors;
  }

  public function setDefaultValues() {
    $defaults = array();
    if (!empty($this->_cid)) {
      $contactDetails = civicrm_api3('Contact', 'getsingle', array( 'id' => $this->_cid));

      $lastAcceptance = CRM_Gdpr_SLA_Utils::getContactLastAcceptance($this->_cid);

      //Set Channel default values
      $contactPrefPrefix = 'do_not_';
      foreach ($this->commPrefSettings['channels'] as $key => $value) {
        $name  = str_replace($this->containerPrefix, '', $key);
        // Quick hack - should use U::getCommunicationPreferenceMapper();
        if ($name == "post") {
           $name = "mail";
        }
        if (!$lastAcceptance) {
          // No acceptance, and preferences are 0 then, set unknown, otherwise display yes/no
          $defaults[$key] = '';
        }
        elseif ($value) {
          $defaults[$key] = !empty($contactDetails[$contactPrefPrefix.$name]) ? 'NO' : 'YES'; 
        }
      }

      //Set Group default values
      $groups = U::getGroups();
      foreach ($groups as $group) {
        $container_name = 'group_' . $group['id'];
        if (!empty($this->commPrefGroupsetting[$container_name]['group_enable'])) {
          $contactGroupDetails = civicrm_api3('GroupContact'
            , 'get'
            , array( 'contact_id' => $this->_cid
              , 'group_id' => $group['id']
              , 'status' => 'Added',
            )
          );

          if (!empty($contactGroupDetails['id'])) {
            $defaults[$container_name] = 1;
          }
        }
      }
      
      //Set Profile defaults
      $fields = array();
      $removeCustomFieldTypes = array('Contribution', 'Membership');
      $contribFields = CRM_Contribute_BAO_Contribution::getContributionFields();

      foreach ($this->_fields as $name => $dontCare) {
        //don't set custom data Used for Contribution (CRM-1344)
        if (substr($name, 0, 7) == 'custom_') {
          $id = substr($name, 7);
          if (!CRM_Core_BAO_CustomGroup::checkCustomField($id, $removeCustomFieldTypes)) {
            continue;
          }
          // ignore component fields
        }
        elseif (array_key_exists($name, $contribFields) || (substr($name, 0, 11) == 'membership_') || (substr($name, 0, 13) == 'contribution_')) {
          continue;
        }
        $fields[$name] = 1;
      }

      if (!empty($fields)) {
        CRM_Core_BAO_UFGroup::setProfileDefaults($this->_cid, $fields, $defaults);
      }       
    }
    return $defaults;
  }

  public function getTermsAndConditionFieldId() {
    $termsConditionsField =  CRM_Gdpr_SLA_Utils::getTermsConditionsField();
    return $termsConditionsField['id']; 
  }
  
  public function postProcess() {
    $submittedValues = $this->exportValues();
    $commPrefMapper  = U::getCommunicationPreferenceMapper();
    //profile form validation will do dedupe and update id in $form
    $existingContact = $this->_cid;
    if (!empty($this->_id)) {
      $existingContact = $this->_id;
    }
    //Terms and condition Record SLA acceptance
    $termsConditionsField = $this->getTermsAndConditionFieldId();
    $tcFieldName  = 'custom_'.$termsConditionsField;
    if (!empty($submittedValues[$tcFieldName]) && !empty($existingContact)) {
      $acceptance = CRM_Gdpr_SLA_Utils::recordSLAAcceptance($existingContact);
    }

    $contactType = 'Individual';
    if ($existingContact) {
      $contactType = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $existingContact, 'contact_type');
    }
    $contactID = CRM_Contact_BAO_Contact::createProfileContact(
      $submittedValues,
      $this->_fields,
      $existingContact,
      NULL,
      NULL,
      $contactType,
      TRUE
    );

    //Prepare Comm pref params
    $commPref = array('id' => $contactID);

    //Comm pref channel Values
    foreach ($this->commPrefSettings['channels'] as $key => $value) {
      $name  = str_replace($this->containerPrefix, '', $key);
      if (!empty($submittedValues[$key])) {
        $channelValue = $submittedValues[$key];
        $commPref = array_merge($commPref, $commPrefMapper[$name][$channelValue]);
      }
    }

    //Using API to update contact
    $contact = civicrm_api3('Contact', 'create', $commPref);

    $groups = U::getGroups();
    foreach ($groups as $groupId => $group) {
      $container_name = 'group_' . $group['id'];
      if (!empty($this->commPrefGroupsetting[$container_name]['group_enable'])) {
        $groupDetails = array(
          'contact_id' => $contactID,
          'group_id'   => $group['id'],
        );
        
        $existsInGroup = civicrm_api3('GroupContact', 'get', $groupDetails);
        
        //Set status added or removed based on user selection
        $status = !empty($submittedValues[$container_name]) ? 'Added' : 'Removed';
        $groupDetails['status'] = $status;
        
        //check before Add / Remove from group.
        if ((!empty($existsInGroup['id']) && $status == 'Removed')
          OR (empty($existsInGroup['id']) && $status == 'Added')
        ) {
          $groupResult = civicrm_api3('GroupContact', 'create', $groupDetails);
        }
      }
    }

    //Create Activity for communication preference updated
    $activityTypeIds = array_flip(CRM_Core_PseudoConstant::activityType(TRUE, FALSE, FALSE, 'name'));
    if (!empty($activityTypeIds[U::COMM_PREF_ACTIVITY_TYPE])) {
      $activityParams = array(
        'activity_type_id'  => $activityTypeIds[U::COMM_PREF_ACTIVITY_TYPE],
        'source_contact_id' => $contactID,
        'target_id'         => $contactID,
        'subject'           => ts('Communication Preferences updated'),
        'activity_date_time'=> date('Y-m-d H:i:s'),
        'status_id'         => "Completed",
      );
      civicrm_api3('Activity', 'Create', $activityParams);
    }

    if (!empty($this->commPrefSettings['completion_message'])) {
      $thankYouMsg = html_entity_decode($this->commPrefSettings['completion_message']);

      //FIXME Redirect to Thank you page or destination url from setting
      CRM_Core_Session::setStatus($thankYouMsg, ts('Communication Preferences'), 'Success');
    }

    //Get the destination url from settings and redirect if we found one.
    if (!empty($this->commPrefSettings['completion_redirect'])) {
      $destinationURL = !empty($this->commPrefSettings['completion_url']) ? $this->commPrefSettings['completion_url'] : NULL;
      if (strpos($destinationURL, 'http') !== 0) {
        $destinationURL = CRM_Utils_System::url($destinationURL, NULL, TRUE);
      }
      CRM_Core_Error::debug_var('destinationURL', $destinationURL );
      CRM_Utils_System::redirect($destinationURL);
    }
    parent::postProcess();
  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
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
