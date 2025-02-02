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
use Joomla\CMS\Event\User;
use Joomla\CMS\Factory;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Checking and updating user data
 * in the form's data after user registration.
 *
 * @since   4.0.0
 */
trait UpdateUserDataInFormeaForm
{
    /**
     * Checking and updating user data
     *  in the form's data after user registration.
     *
     * @param   User\AfterSaveEvent  $event
     *
     * @return bool
     */
    public function UpdateUserDataInFormeaForm(User\AfterSaveEvent $event)
    {
        $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return false;
        }

        if(!$app->isClient('site')) {
            return false;
        }

        $user = $event->getUser();
        $isnew = $event->getIsNew();
        $success = $event->getSavingResult();

        if(!$isnew || !$success || empty($user['id']) || empty($user['email'])) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->qn('#__formeacustom_forms'))
            ->where($db->qn('user_id') . '=' . $db->q(0))
            ->where($db->qn('user_email') . ' = :userEmail')
            ->bind(':userEmail', $user['email'], ParameterType::STRING);
        try {
            $rows = $db->setQuery($query)->loadObjectList();
        } catch (ExecutionFailureException $e) {
            $event->setArgument('result', false);
            return false;
        }

        if(empty($rows)) {
            $event->setArgument('result', true);
            return true;
        }

        $formTable = new FormeacustomFormTable(
            Factory::getContainer()->get('DatabaseDriver'),
            $this->getDispatcher()
        );

        foreach($rows as $row) {
            $row->user_id = (int)$user['id'];
            try {
                $formTable->save($row);
            } catch (\Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'error');
                $event->setArgument('result', false);
                return false;
            }
        }

        $event->setArgument('result', true);
        return true;
    }
}
