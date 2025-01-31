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
use Joomla\CMS\Event\Application\BeforeRenderEvent;

/**
 * Updating form submission data.
 *
 * @since   4.0.0
 */
trait SavePageSubmission
{
	/**
	 * Updating form submission data.
	 *
	 * @param   BeforeRenderEvent  $event
	 *
	 * @return void
	 */
    public function SavePageSubmission(BeforeRenderEvent $event)
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

		if(!empty($submissionData['task'])) {
			if($submissionData['task'] == 'validateform.page') {
				// Save the intermediate page of a multi page form
				$this->SaveSubmissionCurrentPage();
			} else if($submissionData['task'] == 'formea.submit') {
				// This is the FULL form submission. One page or multi page form.
				// We do nothing to save. But we need to update the mapping.
				$session->set('formeacustom.form.submission.update', true);
			}
		}

		$event->setArgument('result', true);
    }
}
