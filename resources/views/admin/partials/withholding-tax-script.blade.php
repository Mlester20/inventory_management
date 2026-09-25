{{--
    Withholding Tax suggestion — Sir's formula, shared by the Sales Invoice form and
    the Delivery Receipt -> Invoice form so the two can never drift apart.

    Per line, using the item's Product Type (Goods 1% / Services 2%, fixed):
        VATable line       amount / (1 + VAT rate) x rate
        VAT-Exempt line    amount x rate
        Zero-Rated line    nothing (Sir: it is not the same as VAT-Exempt, so it defaults to zero)
    plus, only for a customer that has a Withholding VAT % (Government):
        VATable line       amount / (1 + VAT rate) x Withholding VAT %

    The result is only ever a pre-filled, still-editable value.
--}}
<script>
    window.WithholdingTax = {
        RATES: @json(\App\Models\GenericName::WITHHOLDING_RATES),

        money(n) {
            return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * @param lines    [{amount, classification: 'vatable'|'vatex'|'zero', type: 'goods'|'services'}]
         * @param customer {withholding_vat: number|null} or null when no customer is chosen
         * @param vatRate  the active VAT rate in percent (e.g. 12)
         * @return {total, text} or null when there is nothing to suggest
         */
        compute(lines, customer, vatRate) {
            if (!customer || lines.length === 0) {
                return null;
            }

            const divisor = 1 + (vatRate / 100);
            const wvat = customer.withholding_vat;
            const labels = { goods: 'Goods', services: 'Services' };
            let total = 0;
            const parts = [];

            ['goods', 'services'].forEach(type => {
                const rate = this.RATES[type];
                let vatable = 0, exempt = 0;
                lines.filter(l => l.type === type).forEach(l => {
                    if (l.classification === 'vatable') {
                        vatable += l.amount;
                    } else if (l.classification !== 'zero') {
                        exempt += l.amount;
                    }
                });
                if (vatable <= 0 && exempt <= 0) {
                    return;
                }

                const net = vatable / divisor;
                total += net * (rate / 100) + exempt * (rate / 100);
                const bits = [];
                if (vatable > 0) {
                    bits.push(`${this.money(net)} x ${rate}%`);
                }
                if (exempt > 0) {
                    bits.push(`VAT-exempt ${this.money(exempt)} x ${rate}%`);
                }
                if (wvat !== null && wvat !== undefined && vatable > 0) {
                    total += net * (wvat / 100);
                    bits.push(`${this.money(net)} x ${wvat}% Withholding VAT`);
                }
                parts.push(`${labels[type]}: ${bits.join(' + ')}`);
            });

            const rounded = Math.round((total + Number.EPSILON) * 100) / 100;
            return { total: rounded, text: `Suggested ${this.money(rounded)} (${parts.join('; ')}).` };
        },
    };
</script>
