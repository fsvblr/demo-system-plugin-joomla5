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
use Joomla\CMS\Event\Module\RenderModuleEvent;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Checking access to a form displayed using a module.
 *
 * @since   4.0.0
 */
trait CheckFormAccessByModule
{
	/**
	 * Checking access to a form displayed using a module.
	 *
	 * @param   RenderModuleEvent  $event
	 *
	 * @return void
	 */
    public function CheckFormAccessByModule(RenderModuleEvent $event)
    {
	    $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('site')) {
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

	    $module = $event->getModule();

		if($module->module == 'mod_formea_form') {
			$params = new Registry($module->params);
			$form_id = $params->get('form_id', 0);
			if(!$this->CheckFormAccess($form_id)) {
				$module->content = Text::_('PLG_SYSTEM_FORMEACUSTOM_ERROR_NO_ACCESS_TO_FORM');
			}
		}

	    $event->setArgument('result', true);
    }
}
