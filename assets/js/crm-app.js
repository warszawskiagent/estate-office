(function () {
    const root = document.querySelector('.estate-office-crm-app');
    if (!root) {
        return;
    }

    try {
        const settings = JSON.parse(root.dataset.settings || '{}');
        console.debug('Estate Office CRM settings', settings);
    } catch (error) {
        console.error('Estate Office CRM: cannot parse settings payload', error);
    }
})();
