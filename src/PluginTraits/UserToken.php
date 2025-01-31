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

use Joomla\CMS\Crypt\Crypt;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;
use Joomla\CMS\Filter\InputFilter;

/**
 * Methods of working with user token.
 *
 * @since   4.0.0
 */
trait UserToken
{
	/**
	 * Generating a new user token.
	 *
	 * @return string
	 */
	private function generateToken()
	{
		return base64_encode(Crypt::genRandomBytes($this->tokenLength));
	}

	private function checkToken($formId=0, $tokenString='')
	{
		if(empty($formId)) {
			return false;
		}

		$filter = InputFilter::getInstance();
		$tokenString = $filter->clean($tokenString, 'BASE64');

		if(empty($tokenString)) {
			return false;
		}

		// The token is a base64 encoded string. Make sure we can decode it.
		$authString = @base64_decode($tokenString);

		if (empty($authString) || (strpos($authString, ':') === false)) {
			return false;
		}

		// Deconstruct the decoded token string to its four discrete parts:
		// algorithm, user ID, user Email and HMAC of the token string saved in the database.
		$parts = explode(':', $authString, 4);

		if (\count($parts) != 4) {
			return false;
		}

		list($algo, $userId, $userEmail, $tokenHMAC) = $parts;

		// Verify the HMAC algorithm requested in the token string is allowed
		$allowedAlgo = $algo == $this->tokenalgorithm;

		// Make sure the user ID is an integer
		$userId = (int) $userId;

		// Check email
		$allowedEmail = filter_var($userEmail, FILTER_VALIDATE_EMAIL);

		// Calculate the reference token data HMAC
		try {
			$siteSecret = $this->getApplication()->get('secret');
		} catch (\Exception $e) {
			return false;
		}

		if (empty($siteSecret)) {
			return false;
		}

		$referenceTokenData = $this->getTokenFromDatabase($formId, $userId, $userEmail);
		$referenceTokenData = empty($referenceTokenData) ? '' : $referenceTokenData;
		$referenceTokenData = base64_decode($referenceTokenData);
		$referenceHMAC      = hash_hmac($algo, $referenceTokenData, $siteSecret);

		// Do the tokens match? Use a timing safe string comparison to prevent timing attacks.
		$hashesMatch = Crypt::timingSafeCompare($referenceHMAC, $tokenHMAC);

		/**
		 * Allow?
		 *
		 * DO NOT concatenate in a single line. Due to boolean short-circuit evaluation it might
		 * make timing attacks possible. Using separate lines of code with the previously calculated
		 * boolean value to the right hand side forces PHP to evaluate the conditions in
		 * approximately constant time.
		 */

		// We need non-empty reference token data
	    $allow = !empty($referenceTokenData);
		// The token hash must be calculated with an allowed algorithm
		$allow = $allowedAlgo && $allow;
		// There must be a valid email
		$allow = $allowedEmail && $allow;
		// The token HMAC hash coming into the request and our reference must match.
		$allow = $hashesMatch && $allow;

		/**
		 * DO NOT try to be smart and do an early return when either of the individual conditions
		 * are not met. There's a reason we only return after checking all three conditions: it
		 * prevents timing attacks.
		 */
		if (!$allow) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the token formatted suitably for the user to copy.
	 *
	 * @param integer $form_id The form id for token
	 * @param integer $user_id The user id for token
	 * @param string $user_email The user email for token
	 *
	 * @return string
	 */
	private function getTokenForDisplay($form_id=0, $user_id=0, $user_email='')
	{
		if(empty($form_id) || (empty($user_id) && empty($user_email))) {
			return '';
		}

		try {
			$siteSecret = $this->getApplication()->get('secret');
		} catch (\Exception $e) {
			$siteSecret = '';
		}

		if (empty($siteSecret)) {
			return '';
		}

		$tokenSeed = $this->getTokenFromDatabase($form_id, $user_id, $user_email);
		if(empty($tokenSeed)) {
			return '';
		}

		$rawToken  = base64_decode($tokenSeed);
		$tokenHash = hash_hmac($this->tokenalgorithm, $rawToken, $siteSecret);
		$message   = base64_encode("$this->tokenalgorithm:$user_id:$user_email:$tokenHash");

		return $message;
	}

	/**
	 * Returns the token from database.
	 *
	 * @param integer $form_id The form id for token
	 * @param integer $user_id The user id for token
	 * @param string $user_email The user email for token
	 *
	 * @return string
	 */
	private function getTokenFromDatabase($form_id=0, $user_id=0, $user_email='')
	{
		if(empty($form_id) || (empty($user_id) && empty($user_email))) {
			return '';
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		$query->select($db->qn('token'))
			->from($db->qn('#__formeacustom_forms'))
			->where($db->qn('form_id') . ' = :formId')
			->where($db->qn('user_id') . ' = :userId')
			->where($db->qn('user_email') . ' = :userEmail')
			->bind(':formId', $form_id, ParameterType::INTEGER)
			->bind(':userId', $user_id, ParameterType::INTEGER)
			->bind(':userEmail', $user_email, ParameterType::STRING)
		;
		try {
			$tokenSeed = $db->setQuery($query)->loadResult();
		} catch (ExecutionFailureException $e) {
			$tokenSeed = '';
		}

		return $tokenSeed ?: '';
	}
}
