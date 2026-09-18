# Client questions: multi-customer samples on one Job Order

Use this when clarifying how receiving, result printing, and reporting should work when **one Job Order (JO)** has **multiple samples** that belong to **different customers** (different names / addresses).

**Current system today:** one customer per Job Order; many samples under that customer.  
**Desired:** one JO number with samples that can each have a different customer, each producing a result.

---

## 1. Who is the “customer” in this process?

1. **Who pays / who is billed?**  
   - The JO submitter (primary customer)?  
   - Each sample’s customer?  
   - Or both (billing vs. result recipient)?

2. **What happens if the customer changes after samples are received?**  
   - Can we update customer name/address after receipt?  
   - Or is that only allowed at intake?

3. **Is there a “primary customer” on the JO that is not a sample’s customer?**  
   - Example: a laboratory client enters one JO, then samples for several end customers?  
   - Or every sample is a full customer record?

---

## 2. How should one JO with multiple samples look?

4. **How many samples can one JO have?**  
   - Unlimited?  
   - Fixed maximum (e.g. 5)?

5. **Can different samples on the same JO share the same customer?**  
   - Yes (two samples under one customer name)  
   - No (every sample is always a different customer)  
   - Or both?

6. **If two samples have the same name and address, can they still be treated as one customer result?**  
   - Or always separate?

7. **What is printed on the RFA (Request for Analysis)?**  
   - One RFA with many samples and many customers?  
   - Or one RFA per sample (same JO number)?

8. **What is printed on the result form?**  
   - One result form for the whole JO?  
   - Or one result form **per sample** (or per customer)?

---

## 3. Receiving and analysis workflow

9. **When samples are received, are they all received at once?**  
   - Yes → whole JO marked received  
   - Or partial receive (some samples received, others later)?

10. **Can analysts work on one sample while another sample is still pending?**  
    - Yes?  
    - Or must all samples be ready before analysis starts?

11. **When is analysis complete?**  
    - All samples?  
    - Each sample independently?

12. **Who is the analyst assigned to?**  
    - One JO-level analyst?  
    - Per-sample analyst?  
    - Or same assigned person for every sample?

13. **How are unpaid / unpriced packages applied when there are multiple customers?**  
    - One total price for the JO?  
    - Or price per sample / customer?

14. **If a sample is waived or cancelled, what happens to the result form?**  
    - Still print for that sample?  
    - Or drop that sample from the result?

---

## 4. Result printing and PDF

15. **What is the print unit for results?**  
    - One PDF for the entire JO (all samples)?  
    - One PDF per sample?  
    - One PDF for each distinct customer (merging samples under that customer)?

16. **How should the customer / address line print?**  
    - From the JO?  
    - From the sample?  
    - From both (JO as billing, sample as result)?

17. **What about sample code, control number, and sample description when there are multiple samples?**  
    - One control number per JO?  
    - Or control numbers per sample?

18. **If a sample has a different customer, does it still use the same control number as the JO?**  
    - Yes?  
    - Or each sample gets a new control number?

19. **If a sample is unselected (package partial), is the result still printed?**  
    - Yes (with `-` or blank)?  
    - Or omit that sample from the result?

20. **Can two customers share one result form template?**  
    - Or is there a separate form for each customer type?

---

## 5. History, search, and handover

21. **How should the queue look?**  
    - One JO card with multiple samples listed under it?  
    - Or multiple cards for one JO (one per sample)?

22. **How should History work?**  
    - One history entry per JO?  
    - One history entry per sample?  
    - Or history of each sample’s result?

23. **Who can see which result?**  
    - JO-level role?  
    - Per sample?  
    - Or still by role only (admin/receiving/analyst/head)?

24. **What happens if two samples have different customers and both are signed?**  
    - Can both be signed?  
    - Or only one?

---

## 6. Pricing and packaging

25. **Are packages and tests selected once for the whole JO?**  
    - Or can each sample have a different package / test set?

26. **If Sample A uses package A and Sample B uses package B, how is that priced?**  
    - One total price?  
    - Separate totals?  
    - Or the customer pays only for the sample’s package?

27. **If a customer selects no tests for one sample, is the result still printed?**  
    - Yes?  
    - Or no PDF?

---

## 7. Intake and kiosk

28. **Is the customer entered once at intake, or can they enter a new customer for each sample?**  
    - One-time customer only?  
    - Per sample?

29. **Does the kiosk need to support “add sample with different customer”?**  
    - Or is that staff-only?

30. **If a sample is removed after intake, can the customer on the remaining samples stay?**  
    - Yes?  
    - Or always re-enter?

---

## 8. Edge cases (please answer if relevant)

31. **Sample A customer is Acme, Sample B customer is Beta, and Sample C is Acme again.**  
    - Are A and C one result, or separate?

32. **Can the same customer have multiple samples on different JOs and still be linked?**  
    - Or only within one JO?

33. **What if customer name changes after receiving?**  
    - Allowed?  
    - Or frozen after receive?

34. **What if sample A is received and sample B is not?**  
    - Result for A only?  
    - Or block result until all samples are done?

35. **What if sample A fails and sample B passes?**  
    - Separate PDFs?  
    - Or one combined PDF?

---

## 9. Preferred answers (fill while talking with the client)

Use this table when you have answers:

| # | Question | Client answer | Notes |
|---|---|---|---|
| 1 | Who is billed? | | |
| 2 | Print unit | | |
| 3 | Receive all at once or partial? | | |
| 4 | Analysis complete per sample or whole JO? | | |
| 5 | Can samples share a customer? | | |
| 6 | Result PDF: one per sample or one per customer? | | |
| 7 | Control number: one for JO or per sample? | | |
| 8 | Package selection: one for JO or per sample? | | |
| 9 | Who owns which sample in the queue? | | |

---

## Suggested process once answers are clear

1. **Intake:** capture customer + samples (and optional per-sample customer).  
2. **Receiving:** mark the JO received (or sample-by-sample if partial).  
3. **Analysis:** assign and complete work (per sample or whole JO).  
4. **Result:** print the correct PDF for each customer/sample.  
5. **History:** show the correct result for each sample.

Once those answers are filled, we can turn them into a concrete schema and UI design.
