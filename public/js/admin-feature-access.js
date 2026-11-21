// "Create feature access" form: when the organizer picks a player, reload the
// form for that player so the Feature list only offers features they lack.
// Without JavaScript the form still works; duplicate grants are rejected on save.
//
// EasyAdmin renders the player field with Tom Select, which neither dispatches
// a native "change" event nor keeps the same instance once opened, so the
// selected value is watched instead of relying on widget events.
const initFeatureAccessForm = () => {
    if (!document.body.classList.contains('ea-new')) {
        return;
    }

    const select = document.querySelector('select[name="PlayerFeatureAccess[player]"]');
    if (!select) {
        return;
    }

    const reloadForPlayer = () => {
        const url = new URL(window.location.href);
        if (select.value) {
            url.searchParams.set('player', select.value);
        } else {
            url.searchParams.delete('player');
        }

        if (url.href !== window.location.href) {
            window.location.assign(url.href);
        }
    };

    let current = select.value;
    window.setInterval(() => {
        if (select.value !== current) {
            current = select.value;
            reloadForPlayer();
        }
    }, 250);
};

if (document.readyState === 'complete') {
    initFeatureAccessForm();
} else {
    window.addEventListener('load', initFeatureAccessForm);
}
