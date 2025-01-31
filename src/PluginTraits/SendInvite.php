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

use Bis\Plugin\System\Formeacustom\Table\FormeacustomFormTable;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Send an invite to the user to complete the form.
 *
 * @since   4.0.0
 */
trait SendInvite
{
	/**
	 * Send an invite to the user to complete the form.
	 *
	 * @param   AfterRouteEvent  $event
	 *
	 * @return void
	 */
    public function SendInviteUserCompleteForm(AfterRouteEvent $event)
    {
	    $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('administrator')) {
            return;
        }

	    if(!$app->checkToken()) {
			return;
	    }

	    $input = $app->getInput();
	    $task = $input->get('task');

	    if($task != 'plg_system_formeacustom.invite') {
		    return;
	    }

		$ids    = (array) $input->get('cid', [], 'int');
	    // Remove zero values resulting from input filter
	    $ids = array_filter($ids);
		$form_id = $input->getInt('form_id');

		if(empty($ids) || empty($form_id)) {
			return;
		}

	    $db = $this->getDatabase();
	    $query = $db->getQuery(true);

	    $query->select('fc.*')
		    ->select($db->qn('u.name', 'user_name'))
		    ->from($db->qn('#__formeacustom_forms', 'fc'))
		    ->join('LEFT', $db->qn('#__users', 'u'), $db->qn('u.id') . ' = ' . $db->qn('fc.user_id'))
		    ->where($db->qn('fc.id') . " IN ('".implode("','", $ids)."')")
		    ->where($db->qn('fc.form_id') . ' = :formId')
		    ->bind(':formId', $form_id, ParameterType::INTEGER);
	    try {
		    $users = $db->setQuery($query)->loadObjectList();
	    } catch (ExecutionFailureException $e) {
		    return;
	    }

		if(empty($users)) {
			return;
		}

		$query->clear();
	    $query->select($db->qn(['title', 'alias']))
		    ->from($db->qn('#__formea_forms'))
		    ->where($db->qn('id') . ' = :id')
		    ->bind(':id', $form_id, ParameterType::INTEGER);
	    $db->setQuery($query);
	    $formData = $db->loadObject();

		$form_alias = !empty($formData->alias) ? $formData->alias : '';

	    $linkMode = $app->get('force_ssl', 0) == 2 ? Route::TLS_FORCE : Route::TLS_IGNORE;
		$email_layout = JPATH_ROOT . '/plugins/system/formeacustom/layouts/email.invite.php';

	    $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer($app->getConfig());
	    $mailer->setSubject(Text::sprintf('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_SUBJECT', $formData->title));

	    $formTable = new FormeacustomFormTable(
		    Factory::getContainer()->get('DatabaseDriver'),
		    $this->getDispatcher()
	    );

		foreach($users as $user) {
			$displayData = (array)$user;
			$displayData['token'] = $this->getTokenForDisplay($form_id, $user->user_id, $user->user_email);
			// error :
			/*$displayData['formLink'] = Route::link(
				'site',
				'index.php?option=com_formea&view=formea&id='.$form_id.'&token=' . $displayData['token'],
				false,
				$linkMode,
				true
			);*/
			$displayData['formLink'] = Uri::root().'index.php?option=com_formea&view=formea&id='.$form_id.'&token='.$displayData['token'];
			$displayData['formTitle'] = !empty($formData->title) ? $formData->title : '';

			$body = '';
			if (file_exists($email_layout)) {
				ob_start();
				include $email_layout;
				$body .= ob_get_clean();
			}

			try {
				$mailer->clearAllRecipients();
				$mailer->clearAttachments();
				$mailer->clearReplyTos();
				$mailer->setBody($body);
				$mailer->isHtml(true);
				$mailer->addRecipient($displayData['user_email']);
				//$mailer->addBcc($app->get('mailfrom'));
				$sendResult = $mailer->send();
			} catch (\Exception $exception) {
				$sendResult = false;
			}

			if ($sendResult) {
				unset($user->user_name);
				$user->invitation_sent = 1;
				$user->invitation_date = date('Y-m-d H:i:s');
				try {
					$formTable->save($user);
				} catch (\Exception $e) {
					$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_ERROR_SEND_EMAIL', $user->user_email), 'warning');
				}
			} else {
				$app->enqueueMessage(Text::sprintf('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_ERROR_SEND_EMAIL', $user->user_email), 'warning');
			}
		}

	    $app->enqueueMessage(Text::_('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_MSG_SUCCESS'));

	    $event->setArgument('result', true);

		$app->redirect(Route::_('index.php?option=com_formea&view=formea&layout=edit&id='.$form_id, false));
    }
}
