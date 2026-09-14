{{--
    Shared "money document" footer for Sales Order / Sales Quote: a Note
    box (left) + VAT/withholding breakdown labels (right) + boxed Prepared
    By / Acknowledge By signatures. Matches the client-approved final
    layout for both documents.

    SalesOrderItem/SalesQuoteItem don't record a per-line tax
    classification (they're keyed by generic_name_id — the specific
    taxed product/brand isn't chosen until Delivery Receipt time), so the
    VAT breakdown itself can't be computed correctly from current data.
    The labels are still printed (matching the approved layout) with
    blank lines for manual completion; only the grand total — which is
    reliably computable — is auto-filled.

    Params:
    - totalAmountDue (string, required): already formatted, e.g. "1,881.00"
    - preparedByLabel / preparedByValue (optional, default "Prepared By" / '')
    - acknowledgeByLabel (optional, default "Acknowledge By")
    - notes (string, optional): printed inside the Note box; left blank
      (for manual completion) when the document has no notes
--}}
{{-- A genuine <table>/<td> row — not Bootstrap's flex .row/.col-*, and not
     CSS Grid either (both were tried first; neither reliably resolved a
     nested element's height: 100% against a "stretched" flex/grid item in
     every browser's print engine). A native table cell's height is the one
     case percentage heights on its children are unambiguously, reliably
     specified to resolve against — so .print-vat-wrapper's height: 100%
     below finally has solid ground to stand on. --}}
<table class="print-money-footer-table mb-0">
    <tr>
        <td class="print-note-box">
            <div class="print-note-label">Note:</div>
            @if(!empty($notes))
                <div class="print-note-content" style="white-space: pre-line;">{{ $notes }}</div>
            @endif
        </td>
        <td class="print-vat-cell">
            <div class="print-vat-wrapper">
                <table class="table table-bordered table-sm print-vat-table mb-0">
                    <tbody>
                        <tr><td>VATable Sales</td><td></td></tr>
                        <tr><td>VAT-Exempt Sales</td><td></td></tr>
                        <tr><td>VAT Zero Rated Sales</td><td></td></tr>
                        <tr><td>Add: VAT</td><td></td></tr>
                        <tr><td>Less Withholding Tax</td><td></td></tr>
                        <tr class="print-vat-total-row">
                            <td class="fw-bold">Total Amount Due</td>
                            <td class="text-end fw-bold">₱{{ $totalAmountDue }}</td>
                        </tr>
                    </tbody>
                </table>
                {{-- Fills any leftover height when the Note box (sibling
                     cell) is taller, so the two boxes' bottom borders line
                     up, without stretching the VAT table's own rows to
                     match (which left ugly gaps inside each row when the
                     Note box grew tall). --}}
                <div class="print-vat-filler"></div>
            </div>
        </td>
    </tr>
</table>

<div class="row g-0 print-signature-boxes">
    <div class="col-6">
        <div class="print-sig-box">
            <div class="print-sig-label">{{ $preparedByLabel ?? 'Prepared By' }}:</div>
            <div class="print-sig-value">{{ $preparedByValue ?? '' }}</div>
        </div>
    </div>
    <div class="col-6">
        <div class="print-sig-box">
            <div class="print-sig-label">{{ $acknowledgeByLabel ?? 'Acknowledge By' }}:</div>
        </div>
    </div>
</div>
