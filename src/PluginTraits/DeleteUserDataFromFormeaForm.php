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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Event\User;
use Joomla\Database\ParameterType;

/**
 * Removing additional fields from the "Formea" form
 * related to a user when deleting a user.
 *
 * @since   4.0.0
 */
trait DeleteUserDataFromFormeaForm
{
	/**
	 * Removing additional fields from the "Formea" form
	 * related to a user when deleting a user.
	 *
	 * @param   User\AfterDeleteEvent  $event
	 *
	 * @return bool
	 */
    public function DeleteUserDataFromFormeaForm(User\AfterDeleteEvent $event)
    {
	    $app = $this->getApplication();

	    if (!($app instanceof CMSApplication)) {
		    return false;
	    }

	    if(!$app->isClient('administrator')) {
		    return false;
	    }

	    $user = $event->getUser();
	    $success = $event->getDeletingResult();

        if(!$success || empty($user['id'])) {
            return false;
        }

	    $db = $this->getDatabase();
	    $query = $db->getQuery(true)
		    ->delete($db->qn('#__formeacustom_forms'))
			->where($db->qn('user_id') . ' = :userId')
			->bind(':userId', $user['id'], ParameterType::INTEGER);
	    $db->setQuery($query);

	    try {
		    $db->execute();
	    } catch (\RuntimeException $e) {
		    $app->enqueueMessage($e->getMessage(), 'error');
	    }

	    return true;
    }
}
