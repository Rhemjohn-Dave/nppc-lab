<x-mail::message>
# Results ready for pickup

Hello {{ $jobOrder->customer_name }},

Your laboratory results for request **{{ $jobOrder->reference_no }}** are ready for pickup at the NPPC Analytical & Diagnostic Laboratory.

**Please bring:**
- A valid ID
- This request reference number: **{{ $jobOrder->reference_no }}**

**Laboratory**  
NPPC Analytical & Diagnostic Laboratory, Inc.  
Block 2, Lot 29, Sta. Clara Subdivision, Circumferential Road  
Brgy. Banago, Bacolod City 6100 Philippines  

Tel: 034-4332131 / 034-4352613  
Email: nppclab@gmail.com  

Typical laboratory hours follow NPPC-ADL reception schedule. If you have questions about pickup, please call the numbers above.

Thanks,<br>
NPPC Analytical & Diagnostic Laboratory
</x-mail::message>
