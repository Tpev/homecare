document.addEventListener('alpine:init', () => {
    window.Alpine.data('customerPaymentLink', (url) => ({
        copied: false,
        showQr: false,
        qrError: '',
        async copy() {
            try {
                await navigator.clipboard.writeText(url);
                this.copied = true;
                window.setTimeout(() => { this.copied = false; }, 2500);
            } catch {
                this.$refs.linkInput.focus();
                this.$refs.linkInput.select();
                this.qrError = 'Select and copy the link above.';
            }
        },
        async toggleQr() {
            this.showQr = !this.showQr;
            if (!this.showQr) return;
            this.qrError = '';
            await this.$nextTick();
            try {
                const { default: QRCode } = await import('qrcode');
                await QRCode.toCanvas(this.$refs.qrCanvas, url, {
                    width: 260,
                    margin: 4,
                    errorCorrectionLevel: 'M',
                    color: { dark: '#173f35', light: '#ffffff' },
                });
            } catch {
                this.qrError = 'The QR code could not be displayed. You can still copy the link.';
            }
        },
    }));
});
