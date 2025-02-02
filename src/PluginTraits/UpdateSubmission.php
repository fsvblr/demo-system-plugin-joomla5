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
use Joomla\CMS\Document\JsonDocument;
use Joomla\CMS\Event\Application\AfterRenderEvent;

/**
 * Updating form submission data.
 *
 * @since   4.0.0
 */
trait UpdateSubmission
{
    /**
     * Updating form submission data.
     *
     * @param   AfterRenderEvent  $event
     *
     * @return void
     */
    public function UpdateSubmission(AfterRenderEvent $event)
    {
        if (!($this->getApplication() instanceof CMSApplication)) {
            return;
        }

        if(!$this->getApplication()->isClient('site')) {
            return;
        }

        try {
            $document = $this->getApplication()->getDocument();
        } catch (\Exception $e) {
            $document = null;
        }

        if (!($document instanceof HtmlDocument) && !($document instanceof JsonDocument)) {
            return;
        }

        $session = $this->getApplication()->getSession();

        $submissionData = $session->get('formeacustom.form.submission.data');
        $submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];

        $updateSubmission = $session->get('formeacustom.form.submission.update');

        if($updateSubmission) {
            $session->remove('formeacustom.form.submission.update');

            $update = new \stdClass();
            $update->submission_id = null;
            $update->form_id = !empty($submissionData['form_id']) ? $submissionData['form_id'] : null;
            $this->UpdateSubmissionData($update);
        }

        $session->remove('formeacustom.form.submission.data');

        $event->setArgument('result', true);
    }
}
