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

use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;
use Joomla\CMS\Uri\Uri;

/**
 * Checking user access to the form.
 *
 * @since   4.0.0
 */
trait CheckFormAccess
{
    /**
     * Checking user access to the form.
     *
     * Access to the form is granted to:
     * 1. LMS user. Even if it is not matched to the form.
     * 2. Authorized user. If it is matched to the form. Token may or may not be present.
     *    If there is no token, check that the form is matched by userID.
     * 3. Guest. If it is matched to the form by email. Token is required.
     *
     * @param integer $form_id
     *
     * @return boolean
     */
    public function CheckFormAccess($form_id=0)
    {
        $app = $this->getApplication();
        $user = $app->getIdentity();
        $token = $app->getInput()->get('token');

        $userId = $user->id;
        $userEmail = $user->email;

        if(empty($form_id)) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select($db->qn('fc.submission_deadline'))
            ->select($db->qn('f.form_type'))
            ->from($db->qn('#__formeacustom_forms', 'fc'))
            ->join('INNER', $db->qn('#__formea_forms', 'f'), $db->qn('f.id') . ' = ' . $db->qn('fc.form_id'))
            ->where($db->qn('fc.form_id') . ' = :formId')
            ->bind(':formId', $form_id, ParameterType::INTEGER)
            ->order($db->qn('fc.id') . ' DESC');
        try {
            $formData = $db->setQuery($query)->loadObject();
        } catch (ExecutionFailureException $e) {
            return false;
        }

        $lmsUser = $this->isLmsUser($form_id);

        if(!$lmsUser) {
            if (empty($token) && $user->guest) {
                return false;
            }

            if (((int) $user->id > 0 && $token) || $user->guest) {
                if (!$this->checkToken($form_id, $token)) {
                    return false;
                }
            }

            if ($token && $user->guest) {
                $authString = @base64_decode($token);
                $parts      = explode(':', $authString, 4);
                list($algo, $userId, $userEmail, $tokenHMAC) = $parts;
            }

            $query->clear();
            $query->select('fc.*')
                ->from($db->qn('#__formeacustom_forms', 'fc'))
                ->where($db->qn('fc.form_id') . ' = :formId')
                ->where($db->qn('fc.user_id') . ' = :userId')
                ->where($db->qn('fc.user_email') . ' = :userEmail')
                ->bind(':formId', $form_id, ParameterType::INTEGER)
                ->bind(':userId', $userId, ParameterType::INTEGER)
                ->bind(':userEmail', $userEmail, ParameterType::STRING);
            try {
                $data = $db->setQuery($query)->loadObject();
            } catch (ExecutionFailureException $e) {
                return false;
            }

            if (empty($data->id)) {
                return false;
            }
        }

        // If the "submission_deadline" field is not filled in, then there is no deadline.
        if(!empty($formData->submission_deadline) && strtotime($formData->submission_deadline) < time()) {
            return false;
        }

        $cookie = json_encode(['uid' => $userId, 'email' => $userEmail, 'form_id' => $form_id, 'form_type' => $formData->form_type]);
        $cookie_options = ['path'=>'/'];
        if(Uri::getInstance()->getScheme() === 'https') {
            $cookie_options = array_merge($cookie_options, ['secure'=>true, 'httponly'=>true]);
        }
        $app->getInput()->cookie->set('formeacustom_form_'.$form_id, $cookie, $cookie_options);

        return true;
    }

    /**
     * Whether the current user is an LMS user
     * and has been assigned the current course.
     *
     * @param integer $form_id
     *
     * @return boolean
     */
    private function isLmsUser($form_id=0)
    {
        if(empty($form_id)) {
            return false;
        }

        $app = $this->getApplication();
        $user = $app->getIdentity();

        if($user->guest) {
            return false;
        }

        $lmsUser = false;

        // ToDo...

        return $lmsUser;
    }
}
