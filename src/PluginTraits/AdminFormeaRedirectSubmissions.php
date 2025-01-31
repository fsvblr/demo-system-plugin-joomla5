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
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Router\Route;

/**
 * Redirect in the admin panel from the list of submissions to the custom list.
 *
 * @since   4.0.0
 */
trait AdminFormeaRedirectSubmissions
{
	/**
	 * Redirect in the admin panel from the list of submissions to the custom list.
	 *
	 * @param   AfterRouteEvent  $event
	 *
	 * @return void
	 */
    public function AdminFormeaRedirectSubmissions(AfterRouteEvent $event)
    {
	    $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('administrator')) {
            return;
        }

	    $input = $app->getInput();
	    $option = $input->get('option');
	    $view = $input->get('view');
	    $task = $input->get('task');
	    $layout = $input->get('layout');

	    if($option === 'com_formea' && $view === 'submissions' && empty($task) && empty($layout)) {
			$app->redirect(Route::_('index.php?option=com_formeacustom&view=submissions', false));
	    }

	    $event->setArgument('result', true);
    }
}
