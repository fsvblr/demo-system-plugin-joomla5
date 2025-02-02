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
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Language\Text;

/**
 * Checking access to a form displayed using a shortcode.
 *
 * @since   4.0.0
 */
trait CheckFormAccessByShortCode
{
    /**
     * Checking access to a form displayed using a shortcode.
     *
     * @param   AfterRenderEvent  $event
     *
     * @return void
     */
    public function CheckFormAccessByShortCode(AfterRenderEvent $event)
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

        $option = $app->getInput()->get('option');
        $task = $app->getInput()->get('task');

        if ($option == 'com_content' && $task == 'edit') {
            return;
        }

        $buffer = $app->getBody();

        $pattern = '/\[formea id=\s*(\d+)\s*]/';
        if (preg_match_all($pattern, $buffer, $matches)) {
            $ids = $matches[1];
            if (!empty($ids)) {
                for ($i=0, $count=count($ids); $i < $count; $i++) {
                    if (is_numeric($ids[$i])) {
                        $form_id = (int)$ids[$i];
                        if ($form_id > 0) {
                            if(!$this->CheckFormAccess($form_id)) {
                                $form_pattern = "/\[formea id=\s*$form_id\s*]/";
                                $buffer = preg_replace($form_pattern, Text::_('PLG_SYSTEM_FORMEACUSTOM_ERROR_NO_ACCESS_TO_FORM'), $buffer);
                            }
                        }
                    }
                }
            }
        }

        $pattern = '/\[formea_ms=\s*(\d+)\s*]/';
        if (preg_match_all($pattern, $buffer, $matches)) {
            $ids = $matches[1];
            if (!empty($ids)) {
                for ($i=0, $count=count($ids); $i < $count; $i++) {
                    if (is_numeric($ids[$i])) {
                        $form_id = (int)$ids[$i];
                        if ($form_id > 0) {
                            if(!$this->CheckFormAccess($form_id)) {
                                $form_pattern = "/\[formea_ms=\s*$form_id\s*]/";
                                $buffer = preg_replace($form_pattern, Text::_('PLG_SYSTEM_FORMEACUSTOM_ERROR_NO_ACCESS_TO_FORM'), $buffer);
                            }
                        }
                    }
                }
            }
        }

        $app->setBody($buffer);

        $event->setArgument('result', true);
    }
}
