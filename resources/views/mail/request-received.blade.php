<x-mail::message>
# Request received

Hello {{ $jobOrder->customer_name }},

We have received your request for analysis at the NPPC Analytical & Diagnostic Laboratory.

**Reference number:** {{ $jobOrder->reference_no }}  
**Samples:** {{ $sampleCount }}

@if (count($tests) > 0)
**Requested tests:**
@foreach ($tests as $test)
- {{ $test }}
@endforeach
@endif

Our Receiving team will review your request and contact you about pricing and sample submission. Please keep this reference number for all follow-ups.

**Laboratory**  
NPPC Analytical & Diagnostic Laboratory, Inc.  
Block 2, Lot 29, Sta. Clara Subdivision, Circumferential Road  
Brgy. Banago, Bacolod City 6100 Philippines  

Tel: 0948 104 8084
/ laboratory@nppc.com.ph  
Email: laboratory@nppc.com.ph  

Thanks,<br>
NPPC Analytical & Diagnostic Laboratory
</x-mail::message>
