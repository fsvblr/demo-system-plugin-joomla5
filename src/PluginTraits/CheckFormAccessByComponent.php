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
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * Checking access to a form displayed using a component.
 *
 * @since   4.0.0
 */
trait CheckFormAccessByComponent
{
	/**
	 * Checking access to a form displayed using a component.
	 *
	 * @param   BeforeRenderEvent  $event
	 *
	 * @return void
	 */
    public function CheckFormAccessByComponent(BeforeRenderEvent $event)
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
	    } catch (\Exception $e) {
		    $document = null;
	    }

	    if (!($document instanceof HtmlDocument)) {
		    return;
	    }

	    $input = $app->getInput();
	    $option = $input->get('option');
	    $view = $input->get('view');
	    $id = $input->getInt('id', 0);

		//admin: form preview
	    $alias = $input->get('alias');
		$tmpl = $input->get('tmpl');
		$layout = $input->get('layout');

		if($option == 'com_formea' && $view == 'formea' && $id > 0) {
			if(!($tmpl == 'component' && $layout == 'modal' && !empty($alias))) { //admin: form preview
				if(!$this->CheckFormAccess($id)) {
					$app->enqueueMessage(Text::_('PLG_SYSTEM_FORMEACUSTOM_ERROR_NO_ACCESS_TO_FORM'), 'error');
					$this->getApplication()->redirect(Route::_('/', false));
				}
			}
		}

	    $event->setArgument('result', true);
    }
}
