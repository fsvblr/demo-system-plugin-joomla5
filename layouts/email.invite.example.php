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

extract($displayData);

?>
<div>
    <table style="width:90%; max-width:500pt; margin:0; padding:0">
        <tr><td>
            <span style="display: inline-block;">Hello <?php echo $user_name ?: ''; ?>,<br /></span>
        </td></tr>
        <tr><td>
            <span style="display: inline-block;">We invite you to fill out the form <?php echo $formTitle; ?>.</span>
            <span style="display: inline-block;">Link to the form - <?php echo $formLink; ?><br /></span>
        </td></tr>
        <?php if(!empty($submission_deadline)): ?>
        <tr><td>
            <span style="display: inline-block;">Deadline for filling out the form: <?php echo date('F j, Y', strtotime($submission_deadline)) ?><br/></span>
        </td></tr>
	    <?php endif; ?>
        <tr><td>
            <span style="display: inline-block;">Company name<br /></span>
        </td></tr>
        <tr><td>
            <span style="display: inline-block;">I: <a href="https://site.com">site.com</a></span>
            <span style="display: inline-block;">E: <a href="mailto:service@site.com">service@site.com</a></span>
        </td></tr>
    </table>
</div>

