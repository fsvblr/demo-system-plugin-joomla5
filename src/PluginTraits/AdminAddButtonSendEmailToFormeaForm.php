<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

namespace Bis\Plugin\System\Formeacustom\PluginTraits;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Language\Text;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Adding a button to the "Formea" form toolbar
 * for sending email(s) with an invitation to complete the form.
 *
 * @since   4.0.0
 */
trait AdminAddButtonSendEmailToFormeaForm
{
	/**
	 * Adding a button to the "Formea" form toolbar
	 * for sending email(s) with an invitation to complete the form.
	 *
	 * @param   AfterRenderEvent  $event
	 *
	 * @return void
	 */
    public function AdminAddButtonSendEmailToFormeaForm(AfterRenderEvent $event)
    {
	    $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('administrator')) {
            return;
        }

        try {
            $document = $app->getDocument();
        } catch (Exception $e) {
            $document = null;
        }

        if (!($document instanceof HtmlDocument)) {
            return;
        }

	    $option = $app->getInput()->get('option');
	    $view = $app->getInput()->get('view');
	    $layout = $app->getInput()->get('layout');
	    $id = $app->getInput()->getInt('id');
	    $valid = false;

	    if ($option == 'com_formea' && $view == 'formea' && $layout == 'edit' && !empty($id)) {
		    $valid = true;
	    }

	    if(!$valid) {
		    return;
	    }

		$html = '<joomla-toolbar-button id="toolbar-invite">
					<button type="button" class="btn-invite btn btn-info" id="inviteBtn" 
						title="'.Text::_('JGLOBAL_OPENS_IN_A_NEW_WINDOW').'">
				    	<span class="fa-solid fa-at" aria-hidden="true"></span>
				    	'.Text::_('PLG_SYSTEM_FORMEACUSTOM_ADMIN_FORMEA_BTN_INVITE_LABEL').'
				    </button>
				</joomla-toolbar-button>';

	    $buffer = $app->getBody();

		$buffer = preg_replace('/(<joomla-toolbar-button\s+id="toolbar-help")/',
			$html.'$1', $buffer);

	    $app->setBody($buffer);

	    $event->setArgument('result', true);
    }

	/**
	 * @param integer $form_id
	 *
	 * @return array|mixed
	 */
	private function getDataInviteForm($form_id=0)
	{
		if(!$form_id) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		$query->select($db->qn(['fc.id','fc.user_id','fc.user_email','fc.invitation_sent','fc.invitation_date']))
			->select($db->qn('u.name', 'user_name'))
			->from($db->qn('#__formeacustom_forms', 'fc'))
			->join('LEFT', $db->qn('#__users', 'u'), $db->qn('u.id') . ' = ' . $db->qn('fc.user_id'))
			->where($db->qn('fc.form_id') . ' = :formId')
			->order($db->qn('fc.invitation_sent') . ' ASC')
			->order($db->qn('fc.user_email') . ' ASC')
			->bind(':formId', $form_id, ParameterType::INTEGER);
		try {
			$users = $db->setQuery($query)->loadObjectList();
		} catch (ExecutionFailureException $e) {
			return [];
		}

		if(empty($users)) {
			return [];
		}

		return $users;
	}

	/**
	 * @param integer $form_id
	 *
	 * @return string
	 */
	private function renderInviteForm($form_id=0)
	{
		$displayData = [];
		$displayData['form_id'] = $form_id;
		$displayData['users'] = $this->getDataInviteForm($form_id);

		$layoutOutput = '';
		$path = JPATH_ROOT . '/plugins/system/formeacustom/layouts/dialog.invite.php';

		if (!file_exists($path)) {
			return $layoutOutput;
		}

		ob_start();
		include $path;
		$layoutOutput .= ob_get_clean();

		return $layoutOutput;
	}
}
