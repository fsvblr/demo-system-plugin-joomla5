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
use Joomla\CMS\Event\Application\BeforeRenderEvent;
use Joomla\CMS\Form\Form;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Render additional fields to the "Formea" form.
 *
 * @since   4.0.0
 */
trait AdminRenderFieldsToFormeaForm
{
	/**
	 * Render additional fields to the "Formea" form.
	 *
	 * @param   BeforeRenderEvent  $event
	 *
	 * @return void
	 */
    public function AdminRenderFieldsToFormeaForm(BeforeRenderEvent $event)
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
	    $id = $app->getInput()->getInt('id');
		$valid = false;

	    if ($option == 'com_formea' && $view == 'formea' && $layout == 'edit') {
		    $valid = true;
	    }

		if(!$valid) {
			return;
		}

	    $db = $this->getDatabase();
	    $query = $db->getQuery(true);
	    $query->select($db->qn('submission_deadline'))
		    ->from($db->qn('#__formeacustom_forms'))
		    ->where($db->qn('form_id') . ' = :formId')
		    ->bind(':formId', $id, ParameterType::INTEGER);
	    try {
		    $submission_deadline = $db->setQuery($query)->loadResult();
	    } catch (ExecutionFailureException $e) {
		    $submission_deadline = '';
	    }

		$data = [];
		$data['formeacustom[submission_deadline]'] = $submission_deadline ?: '';

		$form = new Form('formeacustom');
	    $form->loadFile(JPATH_SITE . '/plugins/system/formeacustom/forms/formeacustom.xml', false);
		$form->bind($data);

		$this->formeacustomForm['users'] = $form->renderField('formeacustom[users]');
	    $this->formeacustomForm['submission_deadline'] = $form->renderField('formeacustom[submission_deadline]');

	    $event->setArgument('result', true);
    }
}
