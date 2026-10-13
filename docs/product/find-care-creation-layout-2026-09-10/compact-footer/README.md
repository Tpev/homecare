# Compact publishing footer

Follow-up to the September 10 single-column creation layout.

The large Review and publish card repeated the form. It has been replaced with a simple footer containing the privacy option, an estimate including the processing fee, an expandable Cost breakdown, and Publish/Cancel actions. The repeated person, help, address, care-type summary, and readiness checklist are removed. All original input fields remain. The first planned recurring visit now appears in Schedule. Validation errors remain visible beside fields and in the footer after an invalid submission.

The collapsed footer measures 213px on desktop and 319–338px on mobile, depending on care type and viewport. One-time and recurring draft checks passed at 1440px, 390px, and 320px, including privacy toggling, expanded fee details, and no horizontal overflow. Browser checks did not publish any requests.

Frontend build passed. Eleven focused regression tests passed with 103 assertions, covering fee totals, validation, privacy, one-time/recurring publishing, same-day dates, and invitation routing. Test edits were limited to the affected cost/footer and privacy-copy cases.

- [Browser results](validation.json)
- [Desktop footer](screenshots/one_time-1440-footer.png)
- [Mobile footer](screenshots/recurring-320-footer.png)
- [Expanded mobile cost details](screenshots/recurring-320-expanded.png)

Earlier screenshots are preserved in the parent folder.
