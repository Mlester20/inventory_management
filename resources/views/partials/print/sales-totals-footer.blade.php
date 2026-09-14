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
{{-- A genuine <table>/<td> row — Bootstrap's flex .row/.col-*, then CSS
     Grid, were each tried first; neither reliably resolved a nested
     element's height: 100% against a "stretched" flex/grid item across
     real browser print engines. This version drops percentage heights
     entirely: the VAT table itself gets an explicit height, and its own
     last row is an empty spacer with height: 100% — the one classic,
     print-safe technique for "this row absorbs whatever's left over"
     that's been reliable in HTML tables since long before flexbox/grid
     existed, because it never asks a child to resolve a percentage
     height against anything — it only relies on how browsers have always
     distributed a table's own extra height across its own rows. --}}
<table class="print-money-footer-table mb-0">
    <tr>
        <td class="print-note-box">
            <div class="print-note-label">Note:</div>
            @if(!empty($notes))
                <div class="print-note-content" style="white-space: pre-line;">{{ $notes }}</div>
            @endif
        </td>
        <td class="print-vat-cell">
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
                    <tr class="print-vat-spacer-row"><td colspan="2"></td></tr>
                </tbody>
            </table>
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
