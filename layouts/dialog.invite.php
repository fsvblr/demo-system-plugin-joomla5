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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');

extract($displayData);

$countChecked = 0;
$allChecked = false;
if(!empty($users)) {
    foreach($users as $user) {
        if(!(int)$user->invitation_sent) {
            $countChecked++;
        }
    }
}
if($countChecked && $countChecked == count($users)) {
    $allChecked = true;
}

?>
<div class="dialog-invite__content p-md-4" data-noitems="<?php echo !empty($users) ? false : true; ?>">
	<form action="<?php echo Route::_('index.php?task=plg_system_formeacustom.invite'); ?>" method="post" name="adminForm" id="adminForm">
		<div class="row">
			<div class="col-md-12">
				<div id="j-main-container" class="j-main-container">
					<?php if (empty($users)) : ?>
						<div class="alert alert-info mt-3">
							<span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
							<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
						</div>
					<?php else : ?>
						<table class="table" id="usersList">
							<thead>
							<tr>
								<td class="w-1 text-center">
                                    <input class="form-check-input" autocomplete="off" type="checkbox"
                                           name="checkall-toggle" value=""
                                           title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                                           <?php echo $allChecked ? ' checked ' : ''; ?>
                                           onclick="Joomla.checkAll(this);">
								</td>
                                <th class="d-md-table-cell">
									<?php echo Text::_('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_USER_NAME'); ?>
								</th>
								<th class="w-40 d-md-table-cell">
									<?php echo Text::_('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_USER_EMAIL'); ?>
								</th>
								<th class="w-10 text-center d-md-table-cell">
									<?php echo Text::_('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_INVITATION_SENT'); ?>
								</th>
								<th class="w-10 text-center d-md-table-cell">
									<?php echo Text::_('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_USER_ID'); ?>
								</th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($users as $i => $item) : ?>
								<tr class="row<?php echo $i % 2; ?>">
									<td class="text-center d-md-table-cell">
                                        <label for="cb<?php echo $item->id; ?>" class="label-hidden">
                                            <span class="visually-hidden">
                                                <?php $hiddenName = !empty($item->user_name) ? $item->user_name : $item->user_email; ?>
                                                <?php echo Text::_('JSELECT'). ' ' . htmlspecialchars($hiddenName, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </label>
                                        <input class="form-check-input" autocomplete="off" type="checkbox"
                                               id="cb<?php echo $item->id; ?>" name="cid[]" value="<?php echo $item->id; ?>"
                                               <?php echo (int)$item->invitation_sent ? '' : ' checked '; ?>
                                               onclick="Joomla.isChecked(this.checked);">
									</td>
									<td class="d-md-table-cell">
										<?php echo !empty($item->user_name) ? htmlspecialchars($item->user_name, ENT_QUOTES, 'UTF-8') : '-'; ?>
									</td>
									<td class="d-md-table-cell">
										<?php echo htmlspecialchars($item->user_email, ENT_QUOTES, 'UTF-8'); ?>
									</td>
									<td class="text-center d-md-table-cell">
										<?php echo (int)$item->invitation_sent ? date("Y-m-d", strtotime($item->invitation_date)) : '-'; ?>
									</td>
									<td class="text-center d-md-table-cell">
										<?php echo $item->user_id ? (int)$item->user_id : '-'; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<input type="hidden" name="form_id" value="<?php echo $form_id; ?>">
					<input type="hidden" name="task" value="plg_system_formeacustom.invite">
					<input type="hidden" name="boxchecked" value="<?php echo $countChecked; ?>">
					<?php echo HTMLHelper::_('form.token'); ?>
				</div>
			</div>
		</div>
	</form>
</div>
