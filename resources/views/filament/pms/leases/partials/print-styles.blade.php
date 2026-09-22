<style>
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        background: #fff;
        color: #111;
    }

    .page {
        position: relative;
        width: 210mm;
        page-break-after: always;
        overflow: hidden;
    }

    .page:last-child { page-break-after: auto; }

    .page img.background {
        display: block;
        width: 100%;
        height: auto;
    }

    .field {
        position: absolute;
        white-space: pre-line;
        line-height: 1.2;
    }

    .field.boxed {
        display: flex;
        align-items: center;
        direction: ltr;
    }

    .field.boxed span { max-width: 100%; }

    /*
     * A placed `installments_table` field — the only field that renders as
     * an actual table rather than a text span. The builder's box width/
     * height controls the table's own size the same way it does a boxed
     * text field's; with no box set it just flows at its natural size from
     * the placed X/Y.
     */
    .field-table {
        position: absolute;
        border-collapse: collapse;
        overflow: auto;
    }

    .field-table th,
    .field-table td {
        border: 1px solid #999;
        padding: 4px 6px;
        text-align: left;
    }

    .field-table th {
        background: #f3f4f6;
    }

    .not-configured {
        padding: 60px 40px;
        font-size: 12pt;
        color: #555;
        max-width: 500px;
    }

    @media print {
        .no-print { display: none !important; }
    }

    .no-print { margin: 12px; }

    .no-print button {
        font: inherit;
        padding: 6px 14px;
        border: 1px solid #111;
        background: #111;
        color: #fff;
        cursor: pointer;
    }
</style>
