<?php

/**
 * @package     System.Plugin
 * @subpackage  System.formeacustom
 *
 * @copyright   (C) 2024 Belitsoft. <https://belitsoft.com>
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

extract($displayData);

echo Text::sprintf('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_BODY_INTRO', $user_name, $formTitle, $formLink);

if(!empty($submission_deadline)) {
	echo Text::sprintf('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_BODY_DEADLINE', date('F j, Y', strtotime($submission_deadline)));
}

echo Text::_('PLG_SYSTEM_FORMEACUSTOM_EMAIL_INVITE_BODY_SIGNATURE');

?>
