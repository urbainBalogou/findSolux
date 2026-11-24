document.querySelectorAll('[data-duration-form]').forEach((durationForm) => {
    const durationType = durationForm.querySelector('[data-duration-type]');
    const durationValue = durationForm.querySelector('[data-duration-value]');
    const durationUnit = durationForm.querySelector('[data-duration-unit]');

    const synchronizeDurationFields = () => {
        const isUnlimited = durationType.value === 'unlimited';

        durationValue.disabled = isUnlimited;
        durationUnit.disabled = isUnlimited;
        durationValue.required = !isUnlimited;
        durationUnit.required = !isUnlimited;
    };

    durationType.addEventListener('change', synchronizeDurationFields);
    synchronizeDurationFields();
});
