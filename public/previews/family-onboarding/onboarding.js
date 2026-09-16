(() => {
    'use strict';

    const form = document.querySelector('#onboarding-form');
    const steps = [...document.querySelectorAll('.wizard-step')];
    const back = document.querySelector('#back-button');
    // LoLo's existing care scheduling uses America/New_York (config/app.php).
    const careTimezone = 'America/New_York';
    const dayFormatter = new Intl.DateTimeFormat('en-CA', { timeZone: careTimezone, year: 'numeric', month: '2-digit', day: '2-digit' });
    const timeFormatter = new Intl.DateTimeFormat('en-GB', { timeZone: careTimezone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    const visitPeriods = {
        morning: { label: 'Morning', start: '09:00', hours: '9 am – 12 pm' },
        noon: { label: 'Noon', start: '12:00', hours: '12 pm – 2 pm' },
        afternoon: { label: 'Afternoon', start: '14:00', hours: '2 pm – 5 pm' },
    };
    const visitTimeInputs = [...form.querySelectorAll('[name="visit_time"]')];
    let currentStep = 0;
    const value = (name) => String(new FormData(form).get(name) || '').trim();

    function dateInCareTimezone(date) {
        const parts = Object.fromEntries(dayFormatter.formatToParts(date).map(part => [part.type, part.value]));
        return `${parts.year}-${parts.month}-${parts.day}`;
    }

    // Resolve a wall-clock time in the care location, including daylight-saving
    // changes. Nonexistent times (the spring clock jump) return null.
    function visitInstant(date, time) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) return null;
        const wallClock = Date.parse(`${date}T${time}:00Z`);
        for (const offsetHours of [4, 5]) {
            const instant = new Date(wallClock + offsetHours * 60 * 60 * 1000);
            if (!Number.isNaN(instant.getTime()) && dateInCareTimezone(instant) === date && timeFormatter.format(instant) === time) return instant;
        }
        return null;
    }

    function refreshDateLimits() {
        const earliest = new Date(Date.now() + 24 * 60 * 60 * 1000);
        const earliestMinute = new Date(Math.ceil(earliest.getTime() / 60000) * 60000);
        const dateField = form.elements.visit_date;
        dateField.min = dateInCareTimezone(earliestMinute);
    }

    function error(name, message) {
        const target = document.getElementById(`${name}-error`);
        if (target) { target.textContent = message; target.hidden = false; }
        form.querySelectorAll(`[name="${name}"]`).forEach(input => {
            input.setAttribute('aria-invalid', 'true');
            if (target) input.setAttribute('aria-describedby', target.id);
        });
    }

    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(element => { element.hidden = true; element.textContent = ''; });
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
    }

    function clearFieldError(name) {
        const target = document.getElementById(`${name}-error`);
        if (target) { target.hidden = true; target.textContent = ''; }
        form.querySelectorAll(`[name="${name}"]`).forEach(input => input.removeAttribute('aria-invalid'));
    }

    function validate(step, focus = true) {
        let valid = true;
        const requireField = (name, message) => { if (!value(name)) { error(name, message); valid = false; } };
        if (step === 0) {
            requireField('care_for', 'Choose who will be receiving care.');
            if (value('care_for') === 'family') {
                requireField('recipient_name', 'Please enter their name.');
                requireField('relationship', 'Please select their relationship to you.');
            }
        }
        if (step === 1) {
            requireField('address_line1', 'Please enter the street address.');
            requireField('city', 'Please enter the city.');
            requireField('state', 'Please enter the state.');
            requireField('zip', 'Please enter the ZIP code.');
            if (value('zip') && !/^\d{5}(?:-\d{4})?$/.test(value('zip'))) { error('zip', 'Enter a 5-digit ZIP code, or ZIP+4.'); valid = false; }
        }
        if (step === 3) {
            requireField('welcome_visit', 'Choose whether you would like a welcome visit.');
            if (value('welcome_visit') === 'yes') {
                refreshDateLimits();
                requireField('visit_date', 'Choose a preferred date.');
                requireField('visit_time', 'Choose Morning, Noon, or Afternoon.');
                if (value('visit_date') && value('visit_time')) {
                    const period = visitPeriods[value('visit_time')];
                    const date = period ? visitInstant(value('visit_date'), period.start) : null;
                    if (!date || date.getTime() < Date.now() + 24 * 60 * 60 * 1000) {
                        error('visit_datetime', 'Please choose a range that starts at least 24 hours from now (Eastern Time).');
                        form.elements.visit_date.setAttribute('aria-invalid', 'true');
                        visitTimeInputs.forEach(input => {
                            input.setAttribute('aria-invalid', 'true');
                            input.setAttribute('aria-describedby', 'schedule-hint visit_datetime-error');
                        });
                        valid = false;
                    }
                }
            }
        }
        if (!valid && focus) steps[step].querySelector('[aria-invalid="true"]')?.focus();
        return valid;
    }

    function showStep(index, focus = true) {
        currentStep = index;
        clearErrors();
        steps.forEach((section, position) => { section.hidden = position !== index; });
        document.querySelector('#step-counter').textContent = `STEP 0${index + 1} OF 05`;
        document.querySelector('#progress-fill').style.width = `${(index + 1) * 20}%`;
        document.querySelector('.progress-track').setAttribute('aria-valuenow', String(index + 1));
        document.querySelector('#continue-label').textContent = index === 4 ? 'Create my first request' : 'Continue';
        back.hidden = index === 0;
        if (index === 2) personalizeNotes();
        if (index === 3) refreshDateLimits();
        if (index === 4) updateSummary();
        if (focus) {
            steps[index].querySelector('h1').focus({ preventScroll: true });
            window.scrollTo({ top: 0, behavior: 'instant' });
        }
    }

    function personalizeNotes() {
        const self = value('care_for') === 'me';
        document.querySelector('#notes-heading').textContent = self ? 'Want to tell caregivers a little about yourself?' : 'Want to tell caregivers a little about this person?';
        document.querySelector('#care-notes-hint').textContent = self ? 'A little about your personality, daily routine, or the support you need.' : 'A little about their personality, daily routine, or the support they need.';
        document.querySelector('#care-preferences-hint').textContent = self ? 'Familiar comforts, preferences, or things that help you feel at ease.' : 'Familiar comforts, preferences, or things that help them feel at ease.';
        form.elements.care_notes.placeholder = self ? 'e.g. I love my garden and a good conversation. I need a little help getting around.' : 'e.g. Mom loves her garden and a good conversation. She needs a little help getting around.';
    }

    function updateSummary() {
        const summary = document.querySelector('#visit-summary');
        const period = visitPeriods[value('visit_time')];
        const date = period ? visitInstant(value('visit_date'), period.start) : null;
        summary.hidden = value('welcome_visit') !== 'yes' || !date;
        if (!summary.hidden) {
            document.querySelector('#visit-summary-date').textContent = `${new Intl.DateTimeFormat('en-US', { timeZone: careTimezone, weekday: 'short', month: 'short', day: 'numeric' }).format(date)} · ${period.label} (${period.hours}, ET) · 1 hour · Free`;
        }
    }

    // Clear errors as a field is edited, so blurring it to click Continue never
    // collapses an error message and moves the button out from under the pointer.
    form.addEventListener('input', event => {
        clearFieldError(event.target.name);
        if (['visit_date', 'visit_time'].includes(event.target.name)) {
            clearFieldError('visit_datetime');
            form.elements.visit_date.removeAttribute('aria-invalid');
            visitTimeInputs.forEach(input => input.removeAttribute('aria-invalid'));
        }
    });

    form.addEventListener('change', event => {
        if (event.target.name === 'care_for') {
            clearErrors();
            const isFamily = value('care_for') === 'family';
            document.querySelector('#family-fields').hidden = !isFamily;
            ['recipient_name', 'relationship'].forEach(name => { form.elements[name].disabled = !isFamily; form.elements[name].required = isFamily; });
        }
        if (event.target.name === 'welcome_visit') {
            clearErrors();
            const wantsVisit = value('welcome_visit') === 'yes';
            document.querySelector('#visit-scheduling').hidden = !wantsVisit;
            form.elements.visit_date.disabled = !wantsVisit;
            document.querySelector('#visit-time').disabled = !wantsVisit;
            refreshDateLimits();
        }
        if (event.target.name === 'visit_date') refreshDateLimits();
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        clearErrors();
        if (!validate(currentStep)) return;
        if (currentStep < 4) { showStep(currentStep + 1); return; }
        // Recheck the time at handoff in case this page has been left open.
        for (let index = 0; index < 4; index++) {
            if (!validate(index, false)) { showStep(index); validate(index); return; }
        }
        // Standalone design preview: keep input in memory only. The application
        // integration will save the profile and request the welcome visit here.
        window.location.assign('/family/requests/create');
    });

    back.addEventListener('click', () => showStep(Math.max(0, currentStep - 1)));
    document.querySelector('#edit-visit').addEventListener('click', () => showStep(3));
    showStep(0, false);
})();
