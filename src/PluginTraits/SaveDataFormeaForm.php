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
use Joomla\CMS\Event\Table\AfterStoreEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\TableInterface;
use Joomla\Component\Formea\Administrator\Table\FormeaTable;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Saving additional fields of the "Formea" form.
 *
 * @since   4.0.0
 */
trait SaveDataFormeaForm
{
	/**
	 * Saving additional fields of the "Formea" form.
	 *
	 * @param   AfterStoreEvent  $event
	 *
	 * @return bool
	 */
    public function SaveDataFormeaForm($event)
    {
	    if (!($this->getApplication() instanceof CMSApplication)) {
		    return true;
	    }

	    if(!$this->getApplication()->isClient('administrator')) {
		    return true;
	    }

	    // Extract arguments
	    /** @var TableInterface $table */
	    $table  = $event['subject'];
	    $result = $event['result'];

	    if (!$result || !is_object($table)) {
		    $event->setArgument('result', false);
		    return false;
	    }

	    if ($table instanceof FormeaTable) {
		    $typeAlias = $table->getTypeAlias();

		    if($typeAlias != 'com_formea.forms') {
			    return true;
		    }

		    $form_id = $table->getId();
	    } else {
		    return true;
	    }

        if(empty($form_id)) {
	        $event->setArgument('result', false);
	        return false;
        }

		$app = $this->getApplication();
		$input = $app->getInput();
		$formData = $input->get('formeacustom', [], 'ARRAY');

		if(empty($formData['users'])) {
			return true;
		}

	    $submission_deadline = trim($formData['submission_deadline']);
		// This field is not required
		if(!empty($submission_deadline)) {
			if(!preg_match('/\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/', $formData['submission_deadline'])) {
				$event->setArgument('result', false);
				return false;
			}
		}

	    $usersSite = [];        // ids
		$usersInvitedOld = [];  // emails
		$usersInvitedNew = [];  // emails

		foreach($formData['users'] as $user) {
			$user = trim($user);
			if(is_numeric($user)) {
				$usersSite[] = (int)$user;
			} else {
				if(strpos($user, '#new#') === 0) {
					$user = substr($user, 5);
					if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
						$usersInvitedNew[] = $user;
					}
				} else {
					if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
						$usersInvitedOld[] = $user;
					}
				}
			}
		}

	    $usersInvited = array_values(array_unique(array_merge($usersInvitedOld, $usersInvitedNew)));

	    $db = $this->getDatabase();
	    $query = $db->getQuery(true);

	    $query->select($db->qn(['user_id','user_email','invitation_sent','invitation_date','token']))
		    ->from($db->qn('#__formeacustom_forms'))
		    ->where($db->qn('form_id') . ' = :formId')
		    ->where($db->qn('user_id') . '> 0')
		    ->bind(':formId', $form_id, ParameterType::INTEGER);
	    try {
		    $oldDataByUserId = $db->setQuery($query)->loadObjectList('user_id');
	    } catch (ExecutionFailureException $e) {
		    $event->setArgument('result', false);
		    return false;
	    }

	    $query->clear();
	    $query->select($db->qn(['user_id','user_email','invitation_sent','invitation_date','token']))
		    ->from($db->qn('#__formeacustom_forms'))
		    ->where($db->qn('form_id') . ' = :formId')
		    ->where($db->qn('user_id') . '=' . $db->q(0))
		    ->bind(':formId', $form_id, ParameterType::INTEGER);
	    try {
		    $oldDataByUserEmail = $db->setQuery($query)->loadObjectList('user_email');
	    } catch (ExecutionFailureException $e) {
		    $event->setArgument('result', false);
		    return false;
	    }

	    $query->clear();
	    $conditions = array(
		    $db->qn('form_id') .'='. $db->q((int)$form_id)
	    );
	    $query->delete($db->qn('#__formeacustom_forms'))
		    ->where($conditions);
	    $db->setQuery($query)
		    ->execute();

	    $formTable = new FormeacustomFormTable(
		    Factory::getContainer()->get('DatabaseDriver'),
		    $this->getDispatcher()
	    );

		if(!empty($usersSite)) {
			foreach($usersSite as $userId) {
				$addUser = [];
				$addUser['id'] = 0;
				$addUser['form_id'] = (int)$form_id;
				$addUser['user_id'] = (int)$userId;
				$addUser['user_email'] = !empty($oldDataByUserId[$userId]->user_email)
					? $oldDataByUserId[$userId]->user_email
					: $this->getUserEmailById($userId);
				$addUser['submission_deadline'] = $submission_deadline;
				$addUser['invitation_sent'] = !empty($oldDataByUserId[$userId]->invitation_sent)
					? $oldDataByUserId[$userId]->invitation_sent
					: 0;
				$addUser['invitation_date'] = !empty($oldDataByUserId[$userId]->invitation_date)
					? $oldDataByUserId[$userId]->invitation_date
					: '';
				$addUser['token'] = !empty($oldDataByUserId[$userId]->token)
					? $oldDataByUserId[$userId]->token
					: $this->generateToken();

				try {
					$formTable->save($addUser);
				} catch (\Exception $e) {
					$app->enqueueMessage($e->getMessage(), 'error');
					$event->setArgument('result', false);
					return false;
				}
			}
		}

		if(!empty($usersInvited)) {
			foreach($usersInvited as $email) {
				$newRow = false;
				// It is possible that the user already exists on the site with UserId
				// and was mistakenly added by email, not found in the user list.
				$userExist = $this->getUserIdByEmail($email); // => user->id
				if(!empty($userExist) && is_numeric($userExist)) {
					// Maybe we have already added this user?
					if(!in_array($userExist, $usersSite)) {
						$addUser = [];
						$addUser['id'] = 0;
						$addUser['form_id'] = (int)$form_id;
						$addUser['user_id'] = (int)$userExist;
						$addUser['user_email'] = $email;
						$addUser['submission_deadline'] = $submission_deadline;
						$addUser['invitation_sent'] = !empty($oldDataByUserId[$userExist]->invitation_sent)
							? $oldDataByUserId[$userExist]->invitation_sent
							: 0;
						$addUser['invitation_date'] = !empty($oldDataByUserId[$userExist]->invitation_date)
							? $oldDataByUserId[$userExist]->invitation_date
							: '';
						$addUser['token'] = !empty($oldDataByUserId[$userExist]->token)
							? $oldDataByUserId[$userExist]->token
							: $this->generateToken();

						$newRow = true;
					}
				} else {
					$addUser = [];
					$addUser['id'] = 0;
					$addUser['form_id'] = (int)$form_id;
					$addUser['user_id'] = 0;
					$addUser['user_email'] = $email;
					$addUser['submission_deadline'] = $submission_deadline;
					$addUser['invitation_sent'] = !empty($oldDataByUserEmail[$email]->invitation_sent)
						? $oldDataByUserEmail[$email]->invitation_sent
						: 0;
					$addUser['invitation_date'] = !empty($oldDataByUserEmail[$email]->invitation_date)
						? $oldDataByUserEmail[$email]->invitation_date
						: '';
					$addUser['token'] = !empty($oldDataByUserEmail[$email]->token)
						? $oldDataByUserEmail[$email]->token
						: $this->generateToken();

					$newRow = true;
				}

				if($newRow) {
					try {
						$formTable->save($addUser);
					} catch (\Exception $e) {
						$app->enqueueMessage($e->getMessage(), 'error');
						$event->setArgument('result', false);
						return false;
					}
				}
			}
		}

	    $event->setArgument('result', true);
	    return true;
    }

	/**
	 * @param integer $id
	 *
	 * @return mixed|string
	 */
	private function getUserEmailById($id=0)
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		$query->select($db->qn('email'))
			->from($db->qn('#__users'))
			->where($db->qn('id') . ' = :userId')
			->bind(':userId', $id, ParameterType::INTEGER);
		try {
			$userEmail = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			return '';
		}

		return $userEmail ?: '';
	}

	/**
	 * @param string $email
	 *
	 * @return int|mixed
	 */
	private function getUserIdByEmail($email='')
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		$query->select($db->qn('id'))
			->from($db->qn('#__users'))
			->where($db->qn('email') . '=' . $db->q($email));

		try {
			$userId = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			return 0;
		}

		return $userId ?: 0;
	}

}
