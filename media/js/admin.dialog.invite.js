import JoomlaDialog from 'joomla.dialog';
window.addEventListener('load', function() {
    let inviteBtn = document.getElementById('inviteBtn');

    if(inviteBtn) {
        inviteBtn.addEventListener('click', function() {
            const dialogTmpl = '<div class="joomla-dialog-container">' +
                '<header class="joomla-dialog-header"></header>' +
                '<section class="joomla-dialog-body">' +
                Joomla.getOptions('plg_system_formeacustom.dialog.invite.popupContent').value +
                '</section>' +
                '<footer class="joomla-dialog-footer"></footer>' +
                '</div>';

            const dialog = new JoomlaDialog({
                popupType: 'inline',
                popupTemplate: dialogTmpl,
                width: '70%',
                height: '80%',
                className: 'dialog-invite',
                textHeader: Joomla.Text._('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_HEADER_TEXT', ''),
                iconHeader: 'fas fa-envelope',
                popupButtons: [
                    {
                        label: Joomla.Text._('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_BTN_INVITE', 'Invite'),
                        onClick: () => sendInvite(),
                        className: 'btn btn-outline-success btn-invite'
                    },
                    {
                        label: Joomla.Text._('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_BTN_CLOSE', 'Close'),
                        onClick: () => dialog.destroy(),
                        className: 'btn btn-outline-info ms-4'
                    },
                ],
            });

            dialog.addEventListener('joomla-dialog:load', () => {
                setTimeout(function() {
                    let popupButtonInvite = dialog.querySelector('.btn-invite'),
                        noItems = dialog.querySelector('.dialog-invite__content').getAttribute('data-noitems');
                    if(popupButtonInvite && noItems) {
                        popupButtonInvite.setAttribute('disabled', 'disabled');
                    }

                    dialog.querySelector('#adminForm').addEventListener('submit', () => {
                        setTimeout(function() {
                            dialog.close();
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }, 1500);
                    });
                }, 500);
            });

            dialog.show();

            dialog.addEventListener('click', (event) => {
                let rect = event.target.getBoundingClientRect();
                if (rect.left > event.clientX ||
                    rect.right < event.clientX ||
                    rect.top > event.clientY ||
                    rect.bottom < event.clientY
                ) {
                    dialog.close();
                }
            });

            function sendInvite() {
                let form = dialog.querySelector('#adminForm'),
                    popupButtonInvite = dialog.querySelector('.btn-invite');
                if (form.boxchecked.value === 0) {
                    JoomlaDialog.alert(
                        Joomla.Text._('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_WARNING_SELECT_USER', ''),
                        Joomla.Text._('PLG_SYSTEM_FORMEACUSTOM_ADMIN_DIALOG_TYPE_MESSAGE_WARNING', 'Warning')
                    );
                    return false;
                }
                popupButtonInvite.setAttribute('disabled', 'disabled');
                form.submit();
            }
        });
    }
});
