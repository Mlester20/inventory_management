{{-- Shared print mechanics + letterhead/table styling. Included inside a
     <style> tag by each document's show.blade.php. Renamed from Invoice's
     original .invoice-* prefix to a generic .print-* prefix so nothing
     Invoice-specific leaks into shared code — each document's own sheet
     class (e.g. .invoice-sheet, .dr-sheet) still sets font-size on its
     wrapper, everything else here is shared. --}}
.print-letterhead {
    margin-bottom: 0.75rem;
}

.print-logo-full {
    display: block;
    width: 100%;
    max-width: 300px;
    height: auto;
}

.print-company-detail {
    font-size: 0.75rem;
    line-height: 1.35;
    color: #222;
}

.print-doc-title {
    font-weight: 800;
    font-size: 1.7rem;
    letter-spacing: 0.5px;
    line-height: 1.1;
}

.print-doc-page {
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
    color: #333;
}

.print-doc-no-row {
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    gap: 0.4rem;
    font-size: 0.82rem;
    margin-bottom: 0.15rem;
    max-width: 100%;
}

.print-doc-no-label {
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}

.print-doc-no-value {
    flex: 1 1 auto;
    min-width: 0;
    border-bottom: 1px solid #333;
    text-align: left;
    padding: 0 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.print-to-box {
    border: 1.5px solid #333;
    padding: 0.5rem 0.9rem 0.6rem;
}

.print-to-header {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.35rem;
}

.print-to-row {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin-bottom: 0.3rem;
    font-size: 0.82rem;
}

.print-to-row:last-child {
    margin-bottom: 0;
}

.print-to-label {
    font-weight: 600;
    width: 75px;
    flex-shrink: 0;
}

.print-to-value {
    flex-grow: 1;
    border-bottom: 1px solid #333;
    min-height: 1.15em;
}

.print-note-box {
    border: 1.5px solid #333;
    min-height: 95px;
    padding: 0.4rem 0.6rem;
}

.print-note-label {
    font-weight: 700;
    font-size: 0.85rem;
}

.print-vat-table td {
    border-color: #333;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.3rem 0.5rem;
}

.print-vat-total-row td {
    background-color: #f5f5f5;
}

.print-sig-box {
    border: 1.5px solid #333;
    min-height: 75px;
    padding: 0.4rem 0.6rem;
    margin-top: -1.5px;
}

.print-sig-label {
    font-weight: 600;
    font-size: 0.85rem;
}

.print-sig-value {
    font-size: 0.8rem;
    margin-top: 0.2rem;
}

.print-items-table th,
.print-items-table td,
.print-totals td {
    border-color: #333;
    vertical-align: middle;
}

.print-items-table thead th {
    background-color: #eee;
    font-weight: 700;
    text-align: center;
    white-space: nowrap;
}

.print-totals {
    width: 170px;
    flex-shrink: 0;
    margin-left: -1px;
}

.print-totals td {
    font-size: 0.68rem;
    padding: 0.15rem 0.4rem;
    text-align: center;
}

.print-totals td.text-end {
    text-align: right;
}

.print-totals tr:nth-child(odd) td {
    background-color: #f5f5f5;
}

.print-totals .total-due-row td {
    font-size: 0.85rem;
    background-color: #eee;
}

.print-disclaimer {
    font-size: 0.7rem;
    color: #6c757d;
    text-align: justify;
}

@media print {
    @page {
        size: auto;
        margin: 10mm;
    }

    .no-print,
    #layout-menu,
    #layout-navbar,
    .content-footer {
        display: none !important;
    }

    .layout-page {
        margin-left: 0 !important;
    }

    body {
        font-size: 12px;
    }

    .print-sheet-card {
        box-shadow: none !important;
        border: none !important;
    }

    .print-sheet-card .card-body {
        padding: 0 !important;
    }

    .print-totals,
    .signature-block,
    tr {
        page-break-inside: avoid;
    }

    /* An items table long enough to span multiple printed pages must be
       allowed to actually break (table/.table-responsive were previously
       also in the avoid-break list above, which tells the browser to try
       to keep the whole table on one page — with enough rows it either
       gets clipped or pushed as a block instead of flowing cleanly). Row
       headers repeat on each new page via table-header-group so a
       continued page still reads correctly without the letterhead. */
    thead {
        display: table-header-group;
    }

    h4 {
        font-size: 1.1rem;
    }

    .table-sm td,
    .table-bordered td,
    .table-bordered th {
        padding: 0.25rem 0.4rem;
    }

    .print-items-table thead th,
    .print-totals .total-due-row td {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
