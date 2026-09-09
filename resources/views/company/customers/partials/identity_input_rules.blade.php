@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rules = {
            mobile_no: { type: 'digits', max: 10 },
            phone_no: { type: 'digits', max: 15 },
            pincode: { type: 'digits', max: 6 },
            contact_person1_phone: { type: 'digits', max: 15 },
            contact_person2_phone: { type: 'digits', max: 15 },
            aadhaar_no: { type: 'digits', max: 12 },
            pan_no: {
                type: 'alnum-upper',
                max: 10,
                regex: /^[A-Z]{5}[0-9]{4}[A-Z]$/,
                hint: 'PAN format: ABCDE1234F'
            },
            gst_no: {
                type: 'alnum-upper',
                max: 15,
                regex: /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/,
                hint: 'GST format: 24ABCDE1234F1Z5'
            },
        };

        function cleanValue(value, rule) {
            const raw = String(value || '');
            if (rule.type === 'digits') {
                return raw.replace(/\D/g, '').slice(0, rule.max);
            }

            return raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, rule.max);
        }

        function ensureFeedback(input, name) {
            let feedback = input.parentElement.querySelector(`[data-identity-feedback="${name}"]`);
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'text-danger small mt-1';
                feedback.dataset.identityFeedback = name;
                input.insertAdjacentElement('afterend', feedback);
            }

            return feedback;
        }

        function updateFeedback(input, name, rule) {
            if (!rule.regex) {
                return;
            }

            const feedback = ensureFeedback(input, name);
            if (!input.value || rule.regex.test(input.value)) {
                feedback.textContent = '';
                input.classList.remove('is-invalid');
                return;
            }

            feedback.textContent = rule.hint;
            input.classList.add('is-invalid');
        }

        Object.entries(rules).forEach(([name, rule]) => {
            document.querySelectorAll(`[name="${name}"]`).forEach((input) => {
                input.setAttribute('maxlength', String(rule.max));
                input.setAttribute('autocomplete', 'off');
                if (rule.type === 'digits') {
                    input.setAttribute('inputmode', 'numeric');
                    input.setAttribute('pattern', `[0-9]{0,${rule.max}}`);
                } else {
                    input.setAttribute('inputmode', 'text');
                }

                input.value = cleanValue(input.value, rule);
                updateFeedback(input, name, rule);
                input.addEventListener('input', function () {
                    const cleaned = cleanValue(this.value, rule);
                    if (this.value !== cleaned) {
                        this.value = cleaned;
                    }
                    updateFeedback(this, name, rule);
                });
                input.addEventListener('blur', function () {
                    updateFeedback(this, name, rule);
                });
            });
        });
    });
</script>
@endpush
