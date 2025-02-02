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
use Joomla\CMS\Event\Table\AfterDeleteEvent;
use Joomla\CMS\Table\TableInterface;
use Joomla\Component\Formea\Administrator\Table\FormeaTable;
use Joomla\Database\ParameterType;

/**
 * Removing additional fields from the "Formea" form when deleting it.
 *
 * @since   4.0.0
 */
trait DeleteFormDataFromFormeaForm
{
    /**
     * Removing additional fields from the "Formea" form when deleting it.
     *
     * @param   AfterDeleteEvent $event
     *
     * @return bool
     */
    public function DeleteFormDataFromFormeaForm($event)
    {
        $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return false;
        }

        if(!$app->isClient('administrator')) {
            return false;
        }

        // Extract arguments
        /** @var TableInterface $table */
        $table  = $event['subject'];
        $pk = $event['pk'];

        if (!$pk || !is_object($table)) {
            return false;
        }

        if ($table instanceof FormeaTable) {
            $typeAlias = $table->getTypeAlias();

            if($typeAlias != 'com_formea.forms') {
                return false;
            }
        } else {
            return true;
        }

        if(empty($pk)) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->qn('#__formeacustom_forms'))
            ->where($db->qn('form_id') . ' = :formId')
            ->bind(':formId', $pk, ParameterType::INTEGER);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\RuntimeException $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        return true;
    }
}
