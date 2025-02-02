<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

namespace Bis\Plugin\System\Formeacustom\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of Formea form users field.
 *
 * @since  3.1
 */
class FormusersField extends ListField
{
    /**
     * List of users who have access to the form.
     *
     * @var    string
     * @since  3.1
     */
    public $type = 'Formusers';

    /**
     * Method to get the field input for a Formusers field.
     *
     * @return  string  The field input.
     *
     * @since   3.1
     */
    protected function getInput()
    {
        $dataOptions = $this->getOptionsCustom();
        $options = !empty($dataOptions['options']) ? $dataOptions['options'] : [];
        $options = array_merge(parent::getOptions(), $options);
        $selected = !empty($dataOptions['selected']) ? $dataOptions['selected'] : [];

        $attr = '';
        $attr .= $this->multiple ? ' multiple' : '';

        $attr2  = '';
        $attr2 .= ' id="formusers_'.$this->id.'"';
        $attr2 .= ' class="' . $this->class . ' formusers-fancy-select"';
        $attr2 .= ' placeholder="' . htmlspecialchars(($this->hint ?: Text::_('PLG_SYSTEM_FORMEACUSTOM_FIELD_FORMUSERS_TYPE_OR_SELECT_SOME_USERS')), ENT_QUOTES, 'UTF-8') . '" ';
        $attr2 .= ' allow-custom';
        $attr2 .= ' new-item-prefix="#new#"';

        if ((bool) $this->required) {
            $attr  .= ' required class="required"';
            $attr2 .= ' required';
        }

        $html = HTMLHelper::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $selected, $this->id);

        Text::script('JGLOBAL_SELECT_NO_RESULTS_MATCH');
        Text::script('JGLOBAL_SELECT_PRESS_TO_SELECT');

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select')
            ->useScript('webcomponent.field-user')
            ->addInlineStyle('
                .formusers-field { 
                    display:flex; 
                    flex-direction:column; 
                    align-items:flex-end; 
                    margin-top:-32px;
                }
            ');

        $userButton = $this->getUserButton($this->id);

        return '<div class="formusers-field">
                    '.$userButton.'
                    <joomla-field-fancy-select '.$attr2.'>'.$html.'</joomla-field-fancy-select>
                </div>';
    }

    /**
     * Method to get a list of users
     *
     * @return  array[]
     */
    private function getOptionsCustom()
    {
        $app = Factory::getApplication();
        $form_id = $app->getInput()->getInt('id');
        $return = [
            'options' => [],
            'selected' => [],
        ];

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->qn('id', 'value'),
                $db->qn('name', 'text'),
            ]
        )
            ->from($db->qn('#__users'))
            ->where($db->qn('block') . ' = 0')
            ->order($db->qn('name') . ' ASC')
        ;
        $db->setQuery($query);
        try {
            $siteUsers = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return $return;
        }

        $query->clear();
        $query->select(
                [
                    $db->qn('a.user_id'),
                    $db->qn('a.user_email'),
                ]
            )
            ->from($db->qn('#__formeacustom_forms', 'a'))
            ->where($db->qn('a.form_id') . ' = :formId')
            ->bind(':formId', $form_id, ParameterType::INTEGER)
            ->order($db->qn('a.user_id') . ' DESC')
        ;
        $db->setQuery($query);
        try {
            $formUsers = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return $return;
        }

        if(empty($siteUsers) && empty($formUsers)) {
            return $return;
        }

        $return['options'] = $siteUsers;

        if(!empty($formUsers)) {
            foreach($formUsers as $formUser) {
                if(!empty($formUser->user_id)) {
                    $return['selected'][] = $formUser->user_id;
                } else {
                    $addDummyUser = new \stdClass();
                    $addDummyUser->value = $formUser->user_email;
                    $addDummyUser->text = $formUser->user_email;
                    $return['options'][] = $addDummyUser;
                    $return['selected'][] = $formUser->user_email;
                }
            }
        }

        return $return;
    }

    private function getUserButton($fieldId='')
    {
        $fieldId = htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8');
        $uri = new Uri('index.php?option=com_users&view=users&layout=modal&tmpl=component&required=0');
        $uri->setVar('field', $fieldId);
        $html = '';

        $html .= '<joomla-field-user class="field-user-wrapper" id="field-user-wrapper_'.$fieldId.'" 
                        url="'.(string) $uri.'"
                        modal-title="'.Text::_('JLIB_FORM_CHANGE_USER').'"
                        input=".field-user-input"
                        input-name=".field-user-input-name"
                        button-select=".button-select">
                    <div class="input-group">
                        <input type="hidden" name="dummy_user_name" id="dummy_user_name_'.$fieldId.'" class="field-user-input-name" value="" />
                        <button type="button" class="btn btn-primary button-select" title="'.Text::_('JLIB_FORM_CHANGE_USER').'">
                            <span class="icon-user icon-white" aria-hidden="true"></span>
                            <span class="visually-hidden">'.Text::_('JLIB_FORM_CHANGE_USER').'</span>
                        </button>
                    </div>
                    <input type="hidden" id="dummy_user_id_'.$fieldId.'" name="dummy_user_id" value="" class="field-user-input" />
                </joomla-field-user>';

        $html .= '<script>
            (function() {
                window.addEventListener("load", function() {
                    var parentEl = document.getElementById("dummy_user_id_'.$fieldId.'").closest(".formusers-field").querySelector("joomla-field-fancy-select"),
                    dropdownEl = parentEl.querySelector(".choices__list--dropdown"),
                    inputEl = parentEl.querySelector(".choices__inner>input.choices__input");
                    
                    // joomla-field-fancy-select.js : line 200
                    const highlighted = Array.from(dropdownEl.querySelectorAll(".is-highlighted"));
                    highlighted.forEach(choice => {
                        choice.classList.remove("is-highlighted");
                        choice.setAttribute("aria-selected", "false");
                    });
                
                    // Insert into multiselect when selecting a user in a modal window:
                    document.getElementById("dummy_user_id_'.$fieldId.'").addEventListener("change", function(event) {
                        setTimeout(function() {
                            inputEl.value = document.getElementById("dummy_user_name_'.$fieldId.'").value;
                            inputEl.dispatchEvent(new KeyboardEvent("keydown", {bubbles: true, keyCode: 13}));
                            
                            disableInvite();
                        }, 100);
                    });
                    
                    // multiselect
                    document.getElementById("'.$fieldId.'").addEventListener("change", function(event) {
                        setTimeout(function() {
                            disableInvite();
                        }, 100);
                    });
                    
                    inputEl.addEventListener("input", function(event) {
                        event.target.style.width = "auto";
                        disableInvite();
                    });
                    // Check if we have inserted a string of several emails separated by commas:
                    inputEl.addEventListener("keydown", function(event) {
                        if(event.key === "Enter") {
                            let tag = event.target.value;
                            if(tag.includes(",")) {
                                event.stopPropagation();
                                let tags = tag.split(",");
                                tags.forEach(tag => {
                                    tag = tag.trim();
                                    if(tag) {
                                        inputEl.value = tag;
                                        inputEl.dispatchEvent(new KeyboardEvent("keydown", {bubbles: true, keyCode: 13}));
                                    }
                                });
                            }
                        }
                    });
                    
                    function disableInvite() {
                        let inviteBtn = document.getElementById("inviteBtn");
                        if(inviteBtn) {
                            inviteBtn.setAttribute("disabled", "disabled");
                        }
                    }
                });
            })();
        </script>';

        return $html;
    }
}
