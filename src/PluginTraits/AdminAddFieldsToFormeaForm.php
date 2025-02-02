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

/**
 * Add additional fields to the "Formea" form.
 *
 * @since   4.0.0
 */
trait AdminAddFieldsToFormeaForm
{
    /**
     * Add additional fields to the "Formea" form.
     *
     * @param   AfterRenderEvent  $event
     *
     * @return void
     */
    public function AdminAddFieldsToFormeaForm(AfterRenderEvent $event)
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
        $valid = false;
        if ($option == 'com_formea' && $view == 'formea' && $layout == 'edit' && !empty($this->formeacustomForm)) {
            $valid = true;
        }

        if(!$valid) {
            return;
        }

        $buffer = $app->getBody();

        $buffer = preg_replace('/id="jform_limit_submission"([^>]+)>\s*<\/div>\s*<\/div>/',
            'id="jform_limit_submission"$1></div></div>'
            . $this->formeacustomForm['users']
            . $this->formeacustomForm['submission_deadline'],
            $buffer);

        $app->setBody($buffer);

        $event->setArgument('result', true);
    }
}
