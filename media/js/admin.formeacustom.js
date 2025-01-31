(function() {

    const currentUrl = new URL(window.location.href);
    let option = currentUrl.searchParams.get('option'),
        view = currentUrl.searchParams.get('view');

    if(option === 'com_formeacustom' && view === 'submissions') {
        document.addEventListener('DOMContentLoaded', function() {
            let submissionLink = document.getElementById('sidebarmenu')
                .querySelector("a[href='index.php?option=com_formea&view=submissions']"),
                componentTitle = submissionLink.closest('li.parent');

            submissionLink.classList.add('mm-active');
            submissionLink.closest('ul').classList.add('mm-show');
            componentTitle.classList.add('mm-active');
            componentTitle.closest('ul').classList.add('mm-show');
        });
    }

})();
