{{--
    Shared print letterhead, matching the client-approved final layout
    (docs/print _layouts/*.docx): a large company logo banner (left) next
    to the document title/page/reference-numbers (right, plain label+
    underline fields, no table borders) — followed by a separately bordered
    "to" box (Deliver To / Supplier / Customer) with simple label+underline
    rows, no internal grid.

    Params:
    - docTitle (string, required): e.g. "DELIVERY RECEIPT"
    - docNoLabel (string, required): e.g. "D.R No."
    - docNo (string, required)
    - docNoLabel2 (string, optional): a second reference number, e.g. "S.O No."
    - docNo2 (string, optional)
    - docDate (string, required): already formatted, e.g. "09/08/2026"
    - toHeader (string, required): e.g. "Deliver to", "Supplier", "Customer"
    - toRows (array, required): ordered [label => value] pairs, e.g.
      ['Name' => $customer->customer_name, 'Address' => $customer->address]
--}}
<div class="print-letterhead row g-0">
    <div class="col-7">
        <img src="{{ asset(config('company.logo')) }}" alt="{{ config('company.name') }}" class="print-logo-full mb-2">
        <div class="print-company-detail fw-bold">{{ config('company.address') }}</div>
        <div class="print-company-detail fw-bold">{{ config('company.proprietor') }} -Proprietor</div>
        <div class="print-company-detail">VAT Reg Tin: {{ config('company.tin') }}</div>
        <div class="print-company-detail">Email: {{ config('company.email') }}</div>
    </div>
    <div class="col-5 text-end">
        <div class="print-doc-title">{{ $docTitle }}</div>
        <div class="print-doc-page">Page {{ $page ?? '___' }} of {{ $pageTotal ?? '___' }}</div>
        <div class="print-doc-no-row">
            <span class="print-doc-no-label">{{ $docNoLabel }}:</span>
            <span class="print-doc-no-value">{{ $docNo }}</span>
        </div>
        @isset($docNoLabel2)
            <div class="print-doc-no-row">
                <span class="print-doc-no-label">{{ $docNoLabel2 }}:</span>
                <span class="print-doc-no-value">{{ $docNo2 ?? '' }}</span>
            </div>
        @endisset
        <div class="print-doc-no-row">
            <span class="print-doc-no-label">Date:</span>
            <span class="print-doc-no-value">{{ $docDate }}</span>
        </div>
    </div>
</div>

<div class="print-to-box mb-2">
    <div class="print-to-header">{{ $toHeader }}</div>
    @foreach ($toRows as $label => $value)
        <div class="print-to-row">
            <span class="print-to-label">{{ $label }}:</span>
            <span class="print-to-value" style="white-space: pre-line;">{{ $value }}</span>
        </div>
    @endforeach
</div>
