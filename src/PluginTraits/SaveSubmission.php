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

use Bis\Plugin\System\Formeacustom\Table\FormeacustomSubmissionTable;
use Feseur\Library\FsrDate;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Formea\Site\Helper\FormeaGeneralHelper;
use Joomla\Component\Formea\Site\Libraries\FormeaElement;
use Joomla\Component\Formea\Site\Libraries\FormeaForm;
use Joomla\Component\Formea\Site\Libraries\FormeaSubmissions;
use Joomla\Component\Formea\Site\Libraries\FormeaTheme;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;

/**
 * Saving form submission data on the front end.
 *
 * @since   4.0.0
 */
trait SaveSubmission
{
	/**
	 * Saving form submission data on the front end.
	 *
	 * @param   AfterRouteEvent  $event
	 *
	 * @return void
	 */
    public function SaveSubmission(AfterRouteEvent $event)
    {
	    $app = $this->getApplication();

        if (!($app instanceof CMSApplication)) {
            return;
        }

        if(!$app->isClient('site')) {
            return;
        }

	    $input = $app->getInput();
	    $option = $input->get('option');
	    $view = $input->get('view');
	    $task = $input->get('task');
	    $form_id = $input->getInt('fom');

	    // The default value should be NULL. Since the first page has a value of 0:
	    $current_page = $input->getInt('current_page');

	    if($option == 'com_formea' &&
		    (
			    ($task == 'validateform.page' && $view == 'validateform')  // multi page form
			    || ($task == 'formea.submit')  // single page form OR last page of multi page form (after 'validateform.page')
		    )
	    ) {
		    if($app->checkToken()) {
			    $submissionData = $input->cookie->getRaw('formeacustom_form_'.$form_id);
			    $submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];

				if(empty($submissionData['sid'])) {  // first step of form submission
					$sid = $this->startSubmissionMapping($submissionData);
					$submissionData = array_merge($submissionData, ['sid' => $sid]);
					$cookie = json_encode($submissionData);
					$cookie_options = ['path'=>'/'];
					if(Uri::getInstance()->getScheme() === 'https') {
						$cookie_options = array_merge($cookie_options, ['secure'=>true, 'httponly'=>true]);
					}
					$app->getInput()->cookie->set('formeacustom_form_'.$form_id, $cookie, $cookie_options);
				}

				$submissionData = json_encode(array_merge($submissionData, ['current_page' => $current_page, 'task' => $task]));
			    $app->getSession()->set('formeacustom.form.submission.data', $submissionData);
		    }
	    }

	    $event->setArgument('result', true);
    }

	/**
	 * Initial entry into the mapping table of the current form submission.
	 *
	 * @param array $submissionData
	 *
	 * @return integer|null
	 */
	private function startSubmissionMapping($submissionData)
	{
		$db = $this->getDatabase();

		$submissionTable = new FormeacustomSubmissionTable(
			Factory::getContainer()->get('DatabaseDriver'),
			$this->getDispatcher()
		);

		$row = new \stdClass();
		$row->form_id = $submissionData['form_id'];
		$row->form_type = $submissionData['form_type'];  // 0 - single page form, 1 - multi page form
		$row->user_id = $submissionData['uid'];
		$row->user_email = $submissionData['email'];
		$row->modified_date = date('Y-m-d H:i:s');

		try {
			$submissionTable->save($row);
			$sid = $db->insertid();
		} catch (\Exception $e) {
			$sid = null;
			$this->getApplication()->enqueueMessage($e->getMessage(), 'error');
		}

		return $sid;
	}

	/**
	 * Save Page Submission
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function SaveSubmissionCurrentPage()
	{
		$app = $this->getApplication();
		$session = $app->getSession();
		$input = $app->getInput();

		$submissionData = $session->get('formeacustom.form.submission.data');
		$submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];

		$form_id = $submissionData['form_id'] ?: null;
		$current_page = $submissionData['current_page'] ?: 0;

		if(empty($form_id)) {
			return false;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		// Check the form type: 0 - single page form, 1 - multi page form
		$query->select($db->qn('form_type'))
			->from($db->qn('#__formea_forms'))
			->where($db->qn('id') . ' = :formId')
			->bind(':formId', $form_id, ParameterType::INTEGER);
		try {
			$form_type = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			return false;
		}

		if(empty($current_page) && (int)$form_type !== 1) {  // not a multi page form
			return false;
		}

		// form's pages / ids
		$query->clear();
		$query->select($db->qn('id'))
			->from($db->qn('#__formea_form_fieldsets'))
			->where($db->qn('form_id') . ' = :formId')
			->order($db->qn('id') . ' ASC')
			->bind(':formId', $form_id, ParameterType::INTEGER);
		try {
			$form_pages = $db->setQuery($query)->loadColumn();
		} catch (ExecutionFailureException $e) {
			return false;
		}

		if(empty($form_pages)) {
			return false;
		}

		// Checking the page number and that it's not the last page.
		// If the page is the last page, we do nothing.
		// If the page is not the last one, we will save it.
		if((int)$current_page === (count($form_pages) - 1)) {   // last page
			return true;
		}

		// Checking that the request contains valid data:
		/** @var \Joomla\Component\Formea\Site\Model\ValidateformModel $ValidateformModel */
		$ValidateformModel = $this->getApplication()->bootComponent('com_formea')->getMVCFactory()
			->createModel('Validateform', 'Site', ['ignore_request' => false]);

		$retObject = $ValidateformModel->validatePage();

		if(!$retObject->success) {
			return false;
		}

		$return_url = $input->getString('return_url');
		if (!empty($return_url)) {
			$return_url = base64_decode($return_url);
		} else {
			$return_url = Uri::root();
		}

		$formeaToken = $input->get('fomToken', null);
		$formea = $input->get('formea', [], 'array');
		$formeaFiles = $input->files->get('formea');

		if (!empty($formeaFiles)) {
			$formea = array_merge($formea, $formeaFiles);
		}

		if(empty($formea) || empty($formeaToken)) {
			return false;
		}

		// Get the fields for the current page and remove the extra ones:
		$form_pages_passed = array_slice($form_pages, 0, $current_page + 1);  // ids

		$query->clear();
		$query->select($db->qn('e.alias'))
			->from($db->qn('#__formea_form_elements', 'fe'))
			->join('INNER', $db->qn('#__formea_elements', 'e'), $db->qn('e.id') . ' = ' . $db->qn('fe.element_id'))
			->where($db->qn('fe.form_id') . ' = :formId')
			->where($db->qn('fe.page_id') . " IN ('".implode("','", $form_pages_passed)."')")
			->order($db->qn('fe.id') . ' ASC')
			->bind(':formId', $form_id, ParameterType::INTEGER);
		try {
			$aliases = $db->setQuery($query)->loadColumn();
		} catch (ExecutionFailureException $e) {
			return false;
		}

		foreach($formea as $alias => $value) {
			if(!in_array($alias, $aliases)) {
				unset($formea[$alias]);
			}
		}

		$submission_id = $this->processSubmission($formea, $form_id, $formeaToken, $return_url);

		if(!empty($submission_id)) {
			$update = new \stdClass();
			$update->submission_id = $submission_id;
			$update->form_id = $form_id;
			$this->UpdateSubmissionData($update);
		}

		return $submission_id ? true : false;
	}

	/**
	 * Process submission
	 * related to components/com_formea/src/Libraries/FormeaForm.php -> processSubmission()
	 *
	 * @param integer $formea
	 * @param integer $form_id
	 * @param string $formeaToken
	 * @param string $return_url
	 *
	 * @return bool|integer
	 */
	private function processSubmission($data, $form_id, $uniqueString, $submitted_url='')
	{
		$formeaForm = new FormeaForm(['id' => $form_id]);
		$formObject  = $formeaForm->getObject();
		/** @var FormeaTheme $themeClass */
		$themeClass = $formeaForm->getThemeClass();

		$hasGlobalError         = false;
		$submission             = new \stdClass();
		$submission->form_id    = $formeaForm->id;
		$submission_data_values = [];

		// Get the fields for the current page and remove the extra ones:
		$passed_aliases = array_keys($data);
		$whereClause = [];                      // custom
		$clause = new \stdClass();
		$clause->field = 'b.alias';
		$clause->operator = 'IN';
		$clause->value = "('".implode("','", $passed_aliases)."')";
		$whereClause[] = $clause;
		$_elements = $formeaForm->getElements($whereClause);

		$totalElements = count($_elements);
		if ($totalElements < 1) {
			return false;
		}

		/** @var FormeaElement $processedElementClass */
		$processedElementClass = [];
		$coreClasses = $formeaForm->getCoreClasses();
		$_coreClasses = array_values($coreClasses);
		$totalCoreClasses = count($_coreClasses);

		$formeaSubmissions = new FormeaSubmissions([
			'coreClasses' => $coreClasses,
			'formObject'  => $formObject
		]);

		$assignedElements  = $formeaSubmissions->prepareForValidations($_elements, $data, $uniqueString);

		$totalAssignedElements = count($assignedElements);
		for ($k = 0; $k < $totalAssignedElements; $k++)
		{
			$formeaElement        = new FormeaElement([
				'element_id'       => $assignedElements[$k]->element_id,
				'is_preview'       => false,
				'isPreview'        => false,
				'formUniqueString' => $uniqueString,
			]);
			if($formObject->params->get('submission_method','HTTP') === 'AJAX'){
				$assignedElements[$k]->error_classes = [
					'element_container'  => $themeClass->getContainerClass(true),
					'input'              => $themeClass->getErrorClass(),
					'feedback_container' => $themeClass->getFeedbackContainerClass(true),
				];
				$assignedElements[$k]->classes       = [
					'element_container'  => $themeClass->getContainerClass(false),
					'input'              => $themeClass->getNoErrorClass(),
					'feedback_container' => $themeClass->getFeedbackContainerClass(false),
				];
			}

			$__key                = $assignedElements[$k]->element_id . '_' . $assignedElements[$k]->group_id . '_' . $assignedElements[$k]->setIndex;
			$assignedElements[$k] = $formeaElement->beforeValidatingSubmission($assignedElements[$k]);

			if ($assignedElements[$k]->byPassValidation) {
				$assignedElements[$k]->validation_result = null;
			} else {
				$validate = $formeaElement->validateSubmission($assignedElements[$k]->submitted_value, $assignedElements[$k]);
				if (!$validate->success) {
					return false;
				}
				$assignedElements[$k]->set('validation_result', $validate);
				$totalValidationResults = count($validate->result);
				for ($j = 0; $j < $totalValidationResults; $j++)
				{
					if ($validate->result[$j]->result->result->hasError)
					{
						$hasGlobalError = true;
						$assignedElements[$k]->set('validation_error', true);
					}
				}
			}

			$skipValueStore = false;
			if (isset($assignedElements[$k]->skip_value_store))
			{
				$skipValueStore = $assignedElements[$k]->skip_value_store;
			}

			if (!$skipValueStore)
			{
				$details = $formeaElement->getDetails(true, true);

				$selectedDetails = FormeaGeneralHelper::getDefaultLangValField([
					'caption',
					'placeholder',
					'description'
				], $details, $formeaForm->langTag);

				$caption = $assignedElements[$k]->title;
				if (!empty($selectedDetails) && isset($selectedDetails['caption']))
				{
					$caption = $selectedDetails['caption'];
				}

				$assignedElements[$k]->set('caption', $caption);
				$assignedElements[$k] = $formeaElement->afterValidatingSubmission($assignedElements[$k]);
				$submittedValue       = $assignedElements[$k]->submitted_value;
				if ($assignedElements[$k]->is_link > 0)
				{
					$submittedValue = '';
				}
				$processedElementClass[$__key] = $formeaElement;;

				$__key = $assignedElements[$k]->element_id . '_' . $assignedElements[$k]->group_id . '_' . $assignedElements[$k]->setIndex;
				$submission_data_values[$__key] = [
					$formeaForm->id,
					$assignedElements[$k]->element_id,
					$assignedElements[$k]->alias,
					$assignedElements[$k]->title,
					$assignedElements[$k]->caption,
					$submittedValue,
					$assignedElements[$k]->is_link,
					json_encode($assignedElements[$k]->link_path),
					json_encode($assignedElements[$k]->dir_path),
					$assignedElements[$k]->group_id,
					$assignedElements[$k]->setIndex
				];
			}
		}

		if($hasGlobalError) {
			return false;
		}

		//store in DB
		$submission_data_values = array_values($submission_data_values);
		$clientIP = $_SERVER['REMOTE_ADDR'];
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$clientIP = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		$user_id = 0;
		$user_email = '';  // custom
		$userObject = $this->getApplication()->getIdentity();
		if(!$userObject->guest) {
			$user_id = $userObject->id;
			$user_email = $userObject->email;
		} else {
			$submissionData = $this->getApplication()->getSession()->get('formeacustom.form.submission.data');
			$submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];
			if(!empty($submissionData['email'])) {
				$user_email = $submissionData['email'];
			}
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		$date  = new FsrDate();

		$submission->user_id          = $user_id;
		$submission->language         = $formeaForm->langTag;
		$submission->ip_address       = $clientIP;
		$submission->state            = 1;
		$submission->user_email_sent  = 0;
		$submission->user_email       = $user_email;
		$submission->admin_email_sent = 0;
		$submission->admin_email      = null;
		$submission->submitted_url    = $submitted_url;
		$submission->created_date     = $date->toSql();
		$submission->created_by       = $user_id;

		//call core onBeforeStoreSubmission
		for ($tc = 0; $tc < $totalCoreClasses; $tc++) {
			$submission = $_coreClasses[$tc]->onBeforeStoreSubmission($submission);
		}

		$db->insertObject('#__formea_submissions', $submission);
		$submission->id = $db->insertid();

		$submissionValues = [];
		$totalSubmissionData = count($submission_data_values);
		for ($i = 0; $i < $totalSubmissionData; $i++) {
			$totalArr = count($submission_data_values[$i]);
			$v = [];
			for ($j = 0; $j < $totalArr; $j++) {
				$v[] = $db->q($submission_data_values[$i][$j]);
			}
			$v[] = $db->q($submission->id);
			$submissionValues[] = implode(',', $v);
		}

		$submissionValueColumns = [
			'form_id',
			'field_id',
			'field_name',
			'field_text',
			'field_caption',
			'field_value',
			'is_link',
			'link_path',
			'dir_path',
			'group_id',
			'setIndex',
			'submission_id'
		];

		$query->insert($db->quoteName('#__formea_submission_data'));
		$query->columns($submissionValueColumns);
		$query->values($submissionValues);
		$db->setQuery($query);
		$db->execute();

		if($hasGlobalError) {
			return false;
		}

		return (int)$submission->id;
	}

	/**
	 * Updating data after form submission.
	 *
	 * @param object $data
	 *
	 * @return bool
	 */
	private function UpdateSubmissionData($data)
	{
		if(empty($data->submission_id) && !empty($data->form_id)) {
			$data->submission_id = $this->getLastSubmission($data->form_id);
		}

		if(empty($data->submission_id)) {
			return false;
		}

		$app = $this->getApplication();
		$session = $app->getSession();

		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		// Get submission's data
		$query->select('s.*')
			->from($db->qn('#__formea_submissions', 's'))
			->where($db->qn('id') . ' = :submissionId')
			->bind(':submissionId', $data->submission_id, ParameterType::INTEGER);
		try {
			$submission = $db->setQuery($query)->loadObject();
		} catch (ExecutionFailureException $e) {
			return false;
		}

		if(empty($submission->id)) {
			return false;
		}

		// Check the form type: 0 - single page form, 1 - multi page form
		$query->clear();
		$query->select($db->qn('form_type'))
			->from($db->qn('#__formea_forms'))
			->where($db->qn('id') . ' = :formId')
			->bind(':formId', $submission->form_id, ParameterType::INTEGER);
		try {
			$form_type = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			return false;
		}

		// Get form pages:
		$query->clear();
		$query->select($db->qn('id'))
			->select($db->qn('title'))
			->from($db->qn('#__formea_form_fieldsets'))
			->where($db->qn('form_id') . ' = :formId')
			->order($db->qn('id') . ' ASC')
			->bind(':formId', $submission->form_id, ParameterType::INTEGER);
		try {
			$dataPages = $db->setQuery($query)->loadObjectList('id');
		} catch (ExecutionFailureException $e) {
			return false;
		}

		$form_pages = !empty($dataPages) ? array_keys($dataPages) : [];

		$submissionData = $session->get('formeacustom.form.submission.data');
		$submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];

		// We can't use $submissionData['current_page'] if this is the last page of a multi page form.
		// Always at this point $submissionData['current_page'] == null.
		// Because of the redirect after form submission, the flow doesn't reach BeforeRenderEvent,
		// much less AfterRenderEvent.

		if((int)$form_type === 1) {   // multi page form
			if(!is_null($submissionData['current_page'])) {  // maybe 0
				$last_page = false;
				$currentPageSubmission = $form_pages[$submissionData['current_page']];
			} else {
				$last_page = true;
				$currentPageSubmission = array_pop($form_pages);
			}
		} else {
			$last_page = true;
			$currentPageSubmission = $form_pages[0];
		}

		$user = $this->getApplication()->getIdentity();

		if($user->guest) {
			if(!empty($submissionData['email'])) {
				$user->email = $submissionData['email'];
			}
		}

		// Previous submission ID (for multi-page forms):
		$prev_submission_id = null;

		if(!empty($submissionData['sid'])) {
			// Get the previous submission ID:
			$query->clear();
			$query->select($db->qn('submission_id'))
				->from($db->qn('#__formeacustom_submissions'))
				->where($db->qn('id') . ' = :Id')
				->bind(':Id', $submissionData['sid'], ParameterType::INTEGER);
			try {
				$prev_submission_id = $db->setQuery($query)->loadResult();
			} catch (ExecutionFailureException $e) {
				$prev_submission_id = null;
			}

			// Update the data in the submission's mapping:
			$submissionTable = new FormeacustomSubmissionTable(
				Factory::getContainer()->get('DatabaseDriver'),
				$this->getDispatcher()
			);

			$row = new \stdClass();
			$row->id = (int)$submissionData['sid'];
			$row->submission_id = (int)$data->submission_id;
			$row->page_id = (int)$currentPageSubmission;
			$row->page_title = !empty($dataPages[$row->page_id]) ? $dataPages[$row->page_id]->title : '';
			$row->modified_date = date('Y-m-d H:i:s');
			if($last_page) {
				$row->form_submit = 1;
			}

			try {
				$submissionTable->save($row);
			} catch (\Exception $e) {
				$app->enqueueMessage($e->getMessage(), 'error');
				return false;
			}
		}

		// Remove submissions of previous pages for a multi-page form:
		if(!empty((int)$prev_submission_id)) {
			$query->clear()
				->delete($db->qn('#__formea_submissions'))
				->where($db->qn('id') . ' = :Id')
				->bind(':Id', $prev_submission_id, ParameterType::INTEGER);
			$db->setQuery($query);

			try {
				$db->execute();
			} catch (\RuntimeException $e) {
				$app->enqueueMessage($e->getMessage(), 'error');
			}

			$query->clear()
				->delete($db->qn('#__formea_submission_data'))
				->where($db->qn('submission_id') . ' = :submissionId')
				->bind(':submissionId', $prev_submission_id, ParameterType::INTEGER);
			$db->setQuery($query);

			try {
				$db->execute();
			} catch (\RuntimeException $e) {
				$app->enqueueMessage($e->getMessage(), 'error');
			}
		}

		// There are cases when user_email is not saved in the main submission table.
		// ToDo: If an event is will added after $sendMail in FormeaGeneralHelper::checkSubmission(), then in this event:
		/*
		if($last_page || (int)$form_type !== 1) {
			$query->clear();
			$query->select($db->qn('user_email'))
				->from($db->qn('#__formea_submissions'))
				->where($db->qn('id') . ' = :Id')
				->bind(':Id', $data->submission_id, ParameterType::INTEGER);
			try {
				$submit_user_email = $db->setQuery($query)->loadResult();
			} catch (ExecutionFailureException $e) {
				$submit_user_email = null;
			}

			if(empty($submit_user_email)) {
				$updSubmission = new \stdClass();
				$updSubmission->id = (int)$data->submission_id;
				$updSubmission->user_email = $user->email;
				$db->updateObject('#__formea_submissions', $updSubmission, 'id');
			}
		} */

		return true;
	}

	/**
	 * Get last submission.
	 *
	 * @param integer $form_id
	 *
	 * @return int|null
	 */
	private function getLastSubmission($form_id=0)
	{
		if(empty($form_id)) {
			return null;
		}

		$app = $this->getApplication();
		$user = $app->getIdentity();

		$submissionData = $this->getApplication()->getSession()->get('formeacustom.form.submission.data');
		$submissionData = !empty($submissionData) ? json_decode($submissionData, true) : [];

		if($user->guest) {
			if(!empty($submissionData['email'])) {
				$user->email = $submissionData['email'];
			}
		}

		// In #__formea_submissions the field "user_email" is not recorded immediately,
		// but after some time, necessary for sending the email.
		// If the user is a guest, this can create a request race problem.
		// If several guests simultaneously submit one form.
		// ToDo: We need post-submission event from the developer. => Rewrite submit and post-submit update.
		$db = $this->getDatabase();
		$query = $db->getQuery(true);
		$query->select($db->qn('id'))
			->from($db->qn('#__formea_submissions'))
			->where($db->qn('form_id') . ' = :formId')
			->where($db->qn('user_id') . ' = :userId')
			//->where($db->qn('user_email') . ' = :userEmail')
			->where($db->qn('state') . ' = ' . $db->q(1))
			->order($db->qn('id') . ' DESC')
			->bind(':formId', $form_id, ParameterType::INTEGER)
			->bind(':userId', $user->id, ParameterType::INTEGER)
			//->bind(':userEmail', $user->email, ParameterType::STRING)
		;

		$query->where("COALESCE(`user_email`, '".$user->email."') = " . $db->q($user->email));
		
		try {
			$submission_id = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			$submission_id = null;
		}

		return (int)$submission_id;
	}
}
