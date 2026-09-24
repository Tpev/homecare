let schedulingScript;

const loadSchedulingButton = () => {
    if (!document.querySelector('[data-google-scheduling-styles]')) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = 'https://calendar.google.com/calendar/scheduling-button-script.css';
        stylesheet.dataset.googleSchedulingStyles = '';
        document.head.appendChild(stylesheet);
    }

    if (window.calendar?.schedulingButton) {
        return Promise.resolve();
    }

    if (!schedulingScript) {
        schedulingScript = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://calendar.google.com/calendar/scheduling-button-script.js';
            script.async = true;
            script.onload = () => {
                if (window.calendar?.schedulingButton) {
                    resolve();
                } else {
                    script.remove();
                    reject(new Error('Google appointment scheduling is unavailable.'));
                }
            };
            script.onerror = () => {
                script.remove();
                reject(new Error('Google appointment scheduling could not load.'));
            };
            document.head.appendChild(script);
        }).catch((error) => {
            schedulingScript = null;
            throw error;
        });
    }

    return schedulingScript;
};

// Alpine initializes this on first render, Livewire navigation, and lead claims.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('googleAppointmentBooking', () => ({
        async init() {
            try {
                await loadSchedulingButton();
                if (!this.$el.isConnected) return;

                window.calendar.schedulingButton.load({
                    url: this.$el.dataset.bookingUrl,
                    color: '#4285F4',
                    label: 'Book an appointment',
                    target: this.$refs.target,
                });
            } catch {
                // The direct booking link remains available if Google cannot load.
            }
        },
    }));
});
