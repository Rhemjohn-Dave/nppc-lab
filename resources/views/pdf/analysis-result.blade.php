<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Analysis Result {{ $jobOrder->reference_no }}</title>
    <style>
        @page { size: A4; margin: 18mm 16mm 18mm 16mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            margin: 0;
            line-height: 1.4;
        }
        p { margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo-cell { width: 64px; padding-right: 10px; }
        .logo-cell img { width: 58px; height: auto; }
        .lab-name {
            font-size: 13px;
            font-weight: bold;
            color: #1A3694;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .lab-meta { font-size: 8px; color: #444; line-height: 1.35; }
        .rule {
            border: none;
            border-top: 2.5px solid #1A3694;
            margin: 8px 0 6px;
        }
        .rule-thin {
            border: none;
            border-top: 1px solid #c5d4f0;
            margin: 0 0 10px;
        }
        .doc-title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1A3694;
            letter-spacing: 0.8px;
            margin: 0 0 12px;
        }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td {
            vertical-align: top;
            padding: 3px 0;
            font-size: 9.5px;
        }
        .meta .label {
            width: 118px;
            color: #365BB0;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.4px;
        }
        .section-title {
            background: #1A3694;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 5px 8px;
            margin: 0;
        }
        .result-box {
            border: 1px solid #1A3694;
            border-top: none;
            padding: 10px 10px 12px;
            margin-bottom: 14px;
        }
        .result-grid td {
            vertical-align: top;
            padding: 4px 8px 4px 0;
        }
        .kicker {
            font-size: 8px;
            font-weight: bold;
            color: #365BB0;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .value { font-size: 10.5px; }
        .result-value {
            font-size: 18px;
            font-weight: bold;
            color: #1A3694;
            letter-spacing: 0.2px;
        }
        .samples { width: 100%; border: 1px solid #c5d4f0; margin-top: 4px; }
        .samples th {
            background: #eef3fb;
            text-align: left;
            font-size: 8px;
            color: #1A3694;
            padding: 4px 6px;
            border-bottom: 1px solid #c5d4f0;
        }
        .samples td {
            padding: 4px 6px;
            font-size: 9px;
            border-bottom: 1px solid #e8eef8;
        }
        .sig { width: 100%; margin-top: 28px; }
        .sig td { width: 48%; vertical-align: top; }
        .sig-gap { width: 4%; }
        .sig-line {
            border-bottom: 1px solid #222;
            height: 28px;
        }
        .sig-caption {
            font-size: 8px;
            text-align: center;
            padding-top: 4px;
            color: #333;
        }
        .footer {
            margin-top: 22px;
            border-top: 1px solid #1A3694;
            padding-top: 6px;
            font-size: 7.5px;
            color: #444;
        }
        .footer td { vertical-align: top; }
        .muted { color: #666; }
        .empty { color: #999; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('nppc-logo.jpg');
    $code = $analysis->analysisType?->code;
    $samples = $jobOrder->samples ?? collect();
    $resultDisplay = filled($analysis->result_value)
        ? trim($analysis->result_value.($analysis->result_unit ? ' '.$analysis->result_unit : ''))
        : null;
    $fieldData = is_string($jobOrder->field_data) ? $jobOrder->field_data : null;
@endphp

<table class="header">
    <tr>
        @if(file_exists($logoPath))
            <td class="logo-cell">
                <img src="{{ $logoPath }}" alt="NPPC">
            </td>
        @endif
        <td>
            <p class="lab-name">NPPC Analytical &amp; Diagnostic Laboratory, Inc.</p>
            <p class="lab-meta">
                Block 2, Lot 29, Sta. Clara Subdivision, Circumferential Road, Brgy. Banago, Bacolod City 6100 Philippines<br>
                Tel Nos. 034-4332131, 034-4352613 | Email: nppclab@gmail.com
            </p>
        </td>
    </tr>
</table>
<hr class="rule">
<hr class="rule-thin">

<p class="doc-title">Analysis Result Sheet</p>

<table class="meta">
    <tr>
        <td class="label">Reference No.</td>
        <td>{{ $jobOrder->reference_no }}</td>
        <td class="label">Date issued</td>
        <td>{{ now()->format('m/d/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Customer</td>
        <td>{{ $jobOrder->customer_name }}</td>
        <td class="label">Company</td>
        <td>{{ $jobOrder->company_name ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Classification</td>
        <td>{{ $jobOrder->classification ?: '—' }}</td>
        <td class="label">Storage temp.</td>
        <td>{{ $jobOrder->sample_storage_temp ?: '—' }}</td>
    </tr>
    @if($fieldData)
        <tr>
            <td class="label">Field data</td>
            <td colspan="3">{{ $fieldData }}</td>
        </tr>
    @endif
</table>

<p class="section-title">Test</p>
<div class="result-box">
    <table class="result-grid">
        <tr>
            <td style="width: 58%;">
                <p class="kicker">Parameter</p>
                <p class="value"><strong>{{ $analysis->name }}</strong></p>
            </td>
            <td>
                <p class="kicker">Category</p>
                <p class="value">{{ $analysis->resolvedCategoryLabel() }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="kicker">Method / code</p>
                <p class="value">{{ $code ?: '—' }}</p>
            </td>
            <td>
                <p class="kicker">Status</p>
                <p class="value">{{ $analysis->status->label() }}</p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 10px;">
                <p class="kicker">Result</p>
                <p class="result-value">
                    @if($resultDisplay)
                        {{ $resultDisplay }}
                    @else
                        <span class="empty">Pending</span>
                    @endif
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <p class="kicker">Remarks</p>
                <p class="value">{{ $analysis->result_remarks ?: '—' }}</p>
            </td>
        </tr>
    </table>
</div>

<p class="section-title">Sample identification</p>
<div class="result-box">
    @if($samples->isEmpty())
        <p class="muted">No samples recorded on this job order.</p>
    @else
        <table class="samples">
            <tr>
                <th>Sample code</th>
                <th>Description</th>
                <th>Matrix</th>
            </tr>
            @foreach($samples as $sample)
                <tr>
                    <td>{{ $sample->sample_code ?: '—' }}</td>
                    <td>{{ $sample->description ?: '—' }}</td>
                    <td>{{ $sample->matrix ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<table class="sig">
    <tr>
        <td>
            <div class="sig-line"></div>
            <p class="sig-caption">
                {{ $analysis->assignee?->name ?: 'Analyst' }}<br>
                Analyst
                @if($analysis->completed_at)
                    · {{ $analysis->completed_at->format('m/d/Y') }}
                @endif
            </p>
        </td>
        <td class="sig-gap"></td>
        <td>
            <div class="sig-line"></div>
            <p class="sig-caption">
                Reviewed by<br>
                Head of Analysis
            </p>
        </td>
    </tr>
</table>

<table class="footer">
    <tr>
        <td>{{ $documentControl['lab'] }} · {{ $documentControl['form'] }}</td>
        <td style="text-align: center;">{{ $documentControl['revision'] }}</td>
        <td style="text-align: right;">{{ $documentControl['effective'] }} · Page 1/1</td>
    </tr>
</table>
</body>
</html>
