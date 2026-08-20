<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request for Analysis {{ $jobOrder->reference_no }}</title>
    <style>
        @page { size: 8.5in 13in; margin: 1in; }
        body {
            font-family: DejaVu Serif, "Times New Roman", Times, serif;
            font-size: 8px;
            color: #000;
            margin: 0;
            line-height: 1.15;
        }
        p { margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        .line td { padding: 0 2px; vertical-align: bottom; line-height: 1.1; }
        .uline { border-bottom: 1px solid #000; height: 10px; line-height: 10px; font-size: 8px; }
        .box { border: 1px solid #000; }
        .box th, .box td { border: 1px solid #000; padding: 0 2px; vertical-align: bottom; line-height: 1.05; }
        .box th { font-size: 7.5px; padding: 1px 2px; }
        .box td { height: 10px; font-size: 7.5px; }
        .header-title { font-size: 10px; font-weight: bold; text-transform: uppercase; margin: 0; line-height: 1.15; }
        .form-title { font-size: 9px; font-weight: bold; text-transform: uppercase; margin: 2px 0 3px; text-align: center; line-height: 1.15; }
        .section { font-weight: bold; margin: 2px 0 1px; line-height: 1.15; font-size: 8px; }
        .small { font-size: 7px; line-height: 1.15; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mt { margin-top: 2px; }
        .mt-sm { margin-top: 1px; }
        .w50 { width: 50%; }
        .check { white-space: nowrap; line-height: 1.1; }
        .item { line-height: 1.08; margin: 0; padding: 0; font-size: 7.5px; }
        .own-blank { display: inline-block; min-width: 16px; border-bottom: 1px solid #000; text-align: center; font-size: 7px; margin-right: 2px; line-height: 1; }
        .samples { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .samples th { font-weight: bold; text-align: left; padding: 0 0 1px; font-size: 7.5px; line-height: 1.1; border: none; }
        .samples td { padding: 0; vertical-align: bottom; border: none; }
        .samples .seg { border-bottom: 1px solid #000; height: 10px; font-size: 7.5px; line-height: 10px; padding: 0 1px; }
        .samples-control { width: 72px; }
        .bill { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .bill th { font-weight: bold; text-align: left; padding: 0 0 1px; font-size: 7.5px; line-height: 1.1; border: none; }
        .bill td { padding: 0; vertical-align: bottom; border: none; }
        .bill .seg { border-bottom: 1px solid #000; height: 10px; font-size: 7.5px; line-height: 10px; padding: 0 1px; }
        .bill .seg.right { text-align: right; }
        .bill-price { width: 52px; }
        .bill-total { width: 52px; }
        .sig-block { margin-top: 10px; width: 100%; }
        .sig-block td { vertical-align: top; }
        .sig-label { width: 88px; white-space: nowrap; font-weight: bold; padding: 0 6px 0 0; vertical-align: top; line-height: 1.1; font-size: 8px; }
        .sig-line-wrap { width: 210px; }
        .sig-write { border-bottom: 1px solid #000; height: 11px; width: 210px; }
        .sig-caption { text-align: center; font-size: 7px; padding-top: 1px; line-height: 1.05; width: 210px; }
        .sig-caption-bold { font-weight: bold; }
        .sig-date-label { width: 32px; white-space: nowrap; font-weight: bold; padding: 0 4px 0 12px; vertical-align: top; line-height: 1.1; font-size: 8px; }
        .sig-date-write { width: 72px; border-bottom: 1px solid #000; height: 11px; text-align: center; vertical-align: bottom; font-size: 7.5px; line-height: 11px; }
        .sig-row-gap td { padding-top: 6px; }
        .doc-footer { margin-top: 14px; }
        .doc-footer td { font-size: 7px; font-weight: bold; line-height: 1.15; vertical-align: bottom; }
        .total-row { margin-top: 3px; width: 48%; }
        .total-row table { width: 100%; }
        .total-label { width: 88px; font-weight: bold; white-space: nowrap; line-height: 1.1; font-size: 8px; }
        .total-line { border-bottom: 1px solid #000; height: 11px; text-align: right; font-size: 7.5px; padding-right: 2px; line-height: 11px; }
        .terms { font-size: 7px; line-height: 1.15; margin-top: 2px; }
        .header-block td { padding: 0; }
        .logo-cell { width: 58px; vertical-align: middle; padding-right: 4px; }
        .logo-cell img { width: 52px; height: auto; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('nppc-logo.jpg');
    $selectedIds = $jobOrder->analyses->pluck('analysis_type_id')->filter()->all();
    $selectedNames = $jobOrder->analyses->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
    $isChecked = function (array $item) use ($selectedIds, $selectedNames) {
        return in_array($item['id'], $selectedIds, true)
            || in_array(mb_strtolower($item['name']), $selectedNames, true);
    };
    $mark = fn (bool $checked) => $checked ? '(X)' : '( )';
    $ownMark = fn (bool $checked) => $checked ? 'X' : '&nbsp;';
    $catalog = collect($catalog);
    $micro = $catalog->where('category', 'microbiological')->values();
    $physico = $catalog->where('category', 'physico_chemical')->values();
    $metals = $catalog->where('category', 'trace_heavy_metals')->values();
    $lime = $catalog->where('category', 'lime')->values();
    $classification = mb_strtolower((string) $jobOrder->classification);
    $classes = ['Aqua', 'Potability', 'Wastewater', 'Agriculture', 'Academic/Research', 'Others'];
    $samples = $jobOrder->samples->values();
    $sampleRows = [];
    for ($i = 0; $i < 9; $i++) {
        $sample = $samples->get($i);
        $sampleRows[] = [
            'description' => $sample
                ? trim(collect([$sample->sample_code, $sample->description])->filter()->implode(' — '))
                : '',
            'control' => $sample ? $jobOrder->reference_no : '',
        ];
    }
    $leftSamples = array_slice($sampleRows, 0, 5);
    $rightSamples = array_pad(array_slice($sampleRows, 5, 4), 5, ['description' => '', 'control' => '']);
    $billing = $jobOrder->analyses->values()->all();
    while (count($billing) < 14) {
        $billing[] = null;
    }
    $billingLeft = array_slice($billing, 0, 7);
    $billingRight = array_slice($billing, 7, 7);
@endphp

<table class="header-block">
    <tr>
        <td class="logo-cell">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="NPPC">
            @endif
        </td>
        <td class="center" style="vertical-align: middle;">
            <p class="header-title">NPPC Analytical &amp; Diagnostic Laboratory, Inc.</p>
            <div class="small">
                Block 2, Lot 29, Sta. Clara Subdivision, Circumferential Road, Brgy. Banago, Bacolod City 6100 Philippines<br>
                Tel Nos. 034-4332131, 034-4352613 | Email: nppclab@gmail.com
            </div>
        </td>
    </tr>
</table>
<div class="form-title">
    Request for Analysis Form / Job Order
    @if(!empty($showResults)) — with Results @endif
</div>

<table class="line">
    <tr>
        <td style="width:12%;"><strong>Customer:</strong></td>
        <td class="uline" style="width:38%;">{{ $jobOrder->customer_name }}</td>
        <td style="width:16%;"><strong>Reference No.:</strong></td>
        <td class="uline" style="width:34%;">{{ $jobOrder->reference_no }}</td>
    </tr>
    <tr>
        <td><strong>Address:</strong></td>
        <td class="uline">{{ $jobOrder->customer_address }}</td>
        <td><strong>Contact No.:</strong></td>
        <td class="uline">{{ $jobOrder->customer_contact }}</td>
    </tr>
</table>

<table class="line mt">
    <tr>
        <td style="white-space:nowrap; width:1%;"><strong>Type of Ownership:</strong></td>
        <td>
            @foreach(['Private','Commercial','Public'] as $ownership)
                <span class="own-blank">{!! $ownMark(strcasecmp((string) $jobOrder->ownership_type, $ownership) === 0) !!}</span>{{ $ownership }}&nbsp;&nbsp;
            @endforeach
        </td>
        <td style="width:22%; white-space:nowrap;"><strong>Time and Date Submitted:</strong></td>
        <td class="uline">{{ optional($jobOrder->created_at)->format('m/d/Y h:i A') }}</td>
    </tr>
</table>

<table class="line mt">
    <tr>
        <td style="width:14%;"><strong>Sampling Date:</strong></td>
        <td class="uline" style="width:19%;">{{ optional($jobOrder->sampling_date)->format('m/d/Y') }}</td>
        <td style="width:14%;"><strong>Sampling Time:</strong></td>
        <td class="uline" style="width:19%;">{{ $jobOrder->sampling_time }}</td>
        <td style="width:16%;"><strong>Sample Collected by:</strong></td>
        <td class="uline">{{ $jobOrder->sample_collected_by }}</td>
    </tr>
</table>

<div class="mt">
    <strong>Sample Classification:</strong>
    @foreach($classes as $class)
        @php
            $checked = str_contains($classification, mb_strtolower($class))
                || ($class === 'Others' && filled($jobOrder->classification) && ! collect(['aqua','potability','wastewater','agriculture','academic/research'])->contains(fn ($c) => str_contains($classification, $c)));
        @endphp
        <span class="check">{{ $mark($checked) }} {{ $class }}</span>
        @if($class === 'Others' && $checked)
            <span class="uline" style="display:inline-block; min-width:80px;">{{ preg_replace('/^others\s*:\s*/i', '', (string) $jobOrder->classification) }}</span>
        @endif
    @endforeach
</div>

<table class="mt">
    <tr>
        @foreach([$leftSamples, $rightSamples] as $group)
            <td class="w50" style="padding-right:6px; vertical-align:top;">
                <table class="samples">
                    <tr>
                        <th>Sample Code/Description</th>
                        <th class="samples-control">Control Number</th>
                    </tr>
                    @foreach($group as $row)
                        <tr>
                            <td><div class="seg">@if($row['description'] !== ''){{ $row['description'] }}@else&nbsp;@endif</div></td>
                            <td class="samples-control"><div class="seg">@if($row['control'] !== ''){{ $row['control'] }}@else&nbsp;@endif</div></td>
                        </tr>
                    @endforeach
                </table>
            </td>
        @endforeach
    </tr>
</table>

@php
    $fieldDataText = trim((string) $jobOrder->field_data);
    $sterileBottle = str_contains(mb_strtolower($fieldDataText), 'sterile bottle');
    $sterileExtra = trim(preg_replace('/^water in sterile bottle\.?\s*/i', '', $fieldDataText) ?? '');
@endphp
<table class="line mt">
    <tr>
        <td style="width:20%;"><strong>Field Data (Potability):</strong></td>
        <td class="uline">
            <span class="check">{{ $mark($sterileBottle) }} Water in sterile bottle</span>
            @if($sterileExtra !== '')
                — {{ $sterileExtra }}
            @endif
        </td>
        <td style="width:28%;"><strong>Sample Storage Temp. (AS RECEIVED):</strong></td>
        <td class="uline">{{ $jobOrder->sample_storage_temp }}</td>
    </tr>
</table>
<div class="mt">
    <strong>Field Data for Waste Water — Sample Source:</strong>
    @foreach(['Local water district','Tank','Faucet','Deepwell','Others'] as $source)
        @php
            $storedSource = (string) $jobOrder->wastewater_source;
            $sourceChecked = $source === 'Others'
                ? str_starts_with(mb_strtolower($storedSource), 'others')
                : strcasecmp($storedSource, $source) === 0;
        @endphp
        <span class="check">{{ $mark($sourceChecked) }} {{ $source }}</span>
        @if($source === 'Others' && $sourceChecked)
            <span class="uline" style="display:inline-block; min-width:80px;">{{ preg_replace('/^others\s*:\s*/i', '', $storedSource) }}</span>
        @endif
    @endforeach
</div>

<table class="mt">
    <tr>
        <td class="w50" style="padding-right:4px; vertical-align:top;">
            <div class="section">Microbiological Analysis</div>
            @foreach($micro as $item)
                <div class="item"><span class="check">{{ $mark($isChecked($item)) }}</span> {{ $item['name'] }}</div>
            @endforeach
        </td>
        <td class="w50" style="vertical-align:top;">
            <div class="section">Physico-Chemical Analysis</div>
            <table>
                <tr>
                    <td class="w50" style="vertical-align:top;">
                        @foreach($physico->take(15) as $item)
                            <div class="item"><span class="check">{{ $mark($isChecked($item)) }}</span> {{ $item['name'] }}</div>
                        @endforeach
                    </td>
                    <td class="w50" style="vertical-align:top;">
                        @foreach($physico->slice(15) as $item)
                            <div class="item"><span class="check">{{ $mark($isChecked($item)) }}</span> {{ $item['name'] }}</div>
                        @endforeach
                    </td>
                </tr>
            </table>
            <div class="section">Trace/Heavy Metals (Water/Food)</div>
            @foreach($metals as $item)
                <span class="check item">{{ $mark($isChecked($item)) }} {{ preg_replace('/.*\\((.+)\\)/', '$1', $item['name']) }}</span>
            @endforeach
            <div class="mt-sm">
                @foreach($lime as $item)
                    <span class="check item">{{ $mark($isChecked($item)) }} {{ $item['name'] }}</span>
                @endforeach
            </div>
        </td>
    </tr>
</table>

<table class="line mt">
    <tr>
        <td style="width:12%;"><strong>Other Tests:</strong></td>
        <td class="uline">{{ $jobOrder->other_tests }}</td>
    </tr>
</table>

<div class="terms">
    <strong>Sample Retention:</strong> Samples are discarded after analysis, except physico-chemical samples which are retained until results are released.<br>
    <strong>Turn Around Time:</strong> 5–7 working days.<br>
    <strong>Terms and Condition:</strong> NPPC-ADL has discussed policies, pricing, and methods with the customer. Acceptance of this form constitutes a binding agreement.
</div>

<div class="mt">
<table>
    <tr>
        @foreach([$billingLeft, $billingRight] as $group)
            <td class="w50" style="padding-right:6px; vertical-align:top;">
                <table class="bill">
                    <tr>
                        <th>Parameters</th>
                        <th class="bill-price right">Price/Test</th>
                        <th class="bill-total right">Total Cost</th>
                    </tr>
                    @foreach($group as $line)
                        <tr>
                            <td>
                                <div class="seg">
                                    @if($line)
                                        {{ $line->name }}
                                        @if(!empty($showResults) && $line->result_value)
                                            → {{ $line->result_value }} {{ $line->result_unit }}
                                        @endif
                                    @else
                                        &nbsp;
                                    @endif
                                </div>
                            </td>
                            <td class="bill-price">
                                <div class="seg right">@if($line){{ number_format((float) $line->unit_price, 2) }}@else&nbsp;@endif</div>
                            </td>
                            <td class="bill-total">
                                <div class="seg right">@if($line){{ number_format((float) $line->total_cost, 2) }}@else&nbsp;@endif</div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        @endforeach
    </tr>
</table>
<div class="total-row">
    <table>
        <tr>
            <td class="total-label">Total</td>
            <td class="total-line">@if((float) $jobOrder->total_cost > 0)PHP {{ number_format((float) $jobOrder->total_cost, 2) }}@else&nbsp;@endif</td>
        </tr>
    </table>
</div>
</div>

<table class="sig-block">
    <tr>
        <td class="sig-label">Conforme:</td>
        <td class="sig-line-wrap">
            <div class="sig-write">&nbsp;</div>
            <div class="sig-caption">Printed Name and Signature of Customer</div>
        </td>
        <td class="sig-date-label">Date:</td>
        <td class="sig-date-write">&nbsp;</td>
    </tr>
    <tr class="sig-row-gap">
        <td class="sig-label">Received by:</td>
        <td class="sig-line-wrap">
            <div class="sig-write">&nbsp;</div>
            <div class="sig-caption sig-caption-bold">MIKE RYSTAR M. DELA CRUZ</div>
        </td>
        <td class="sig-date-label">Date:</td>
        <td class="sig-date-write">{{ optional($jobOrder->received_at)->format('m/d/Y') }}</td>
    </tr>
    <tr class="sig-row-gap">
        <td class="sig-label">Reviewed by:</td>
        <td class="sig-line-wrap">
            <div class="sig-write">&nbsp;</div>
            <div class="sig-caption sig-caption-bold">ROSELYN C. USERO</div>
        </td>
        <td class="sig-date-label">Date:</td>
        <td class="sig-date-write">{{ optional($jobOrder->reviewed_at)->format('m/d/Y') }}</td>
    </tr>
</table>

<table class="doc-footer">
    <tr>
        <td>
            {{ $documentControl['lab'] ?? 'NPPC-ADL' }}<br>
            {{ $documentControl['form'] ?? 'LSP 7.1 F01' }}
        </td>
        <td class="right">
            Revision: {{ $documentControl['revision'] }}<br>
            Effectivity Date: {{ $documentControl['effective'] }}<br>
            Page 1/1
        </td>
    </tr>
</table>
</body>
</html>
