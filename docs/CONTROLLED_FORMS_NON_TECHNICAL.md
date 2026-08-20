# Controlled Forms Guide for Non-Technical Staff

## What This Feature Is For
Controlled Forms lets the laboratory keep an approved printable form in the system and place live data on top of it.

Examples:
- Request for Analysis forms
- controlled report layouts
- other official laboratory documents that must follow a fixed format

Instead of typing everything manually into a blank page, the system uses an uploaded document as the base form, then fills the needed data into the correct places.

## Simple Idea
Think of it like this:

1. An admin uploads the official form.
2. The admin opens the designer and marks where each value should appear.
3. The admin saves and activates that version.
4. The system then uses that approved version when generating documents.

The uploaded PDF is the actual form. The designer only tells the system where to place dynamic information.

## Who Uses It
### Admin
The admin is responsible for:
- creating the controlled form record
- uploading the official document
- placing fields in the designer
- reviewing the preview
- activating the correct revision

### Other Staff
Other staff usually do not redesign the form. They use the active revision indirectly when the system generates documents from encoded job order and analysis data.

## Normal Workflow
### Step 1. Create the Controlled Form
The admin creates the form entry and fills in details such as:
- form code
- form name
- revision number
- description or notes if needed

### Step 2. Upload the Source Document
The admin uploads the official file.

Supported uploads may include:
- PDF
- Word document

The system keeps the original file and prepares a PDF version that will be used in the designer.

### Step 3. Open the Form Designer
The designer shows:
- a field library on the left
- the PDF in the middle
- field properties on the right

The admin places fields onto the PDF where the live values should appear.

Examples of values that may be placed:
- customer name
- address
- sample details
- analysis names
- selected parameters
- signatures
- checkboxes

### Step 4. Save the Layout
After fields are placed correctly, the admin saves the revision.

### Step 5. Preview
The admin previews the form using sample or real data to make sure:
- text appears in the right location
- checkboxes work as expected
- table areas look correct
- the overall layout matches the intended printed form

### Step 6. Activate the Revision
Once verified, the admin activates the revision so it becomes the approved version used by the system.

## What a Revision Means
A revision is a specific version of a controlled form.

This is important because forms may change over time:
- new revision number
- updated wording
- updated signatures
- updated layout

Older revisions should remain as records, while the active revision is the one currently used for generated documents.

## Why PDF Size Matters
The system reads the size of the uploaded PDF and uses that size when placing data.

Common page sizes include:
- `A4`
- `Short bond / Letter / 8.5 × 11`
- `Long / Folio / 8.5 × 13`

This matters because field placement depends on the real page size of the uploaded file.

If the system thinks the page is a different size than the actual document, the preview can become misaligned.

## If the Uploaded Form Is 8.5 × 11
That is fine.

If the uploaded PDF is short bond or Letter size (`8.5 × 11`), the system should use that page size when designing and generating the form.

The same is true for other page sizes, as long as the uploaded source file is the one used in the designer.

## Why the Admin Must Upload First
The correct process is:

1. upload the real document first
2. design on top of that exact document
3. save and preview
4. activate when ready

This avoids guessing the page size or using the wrong background layout.

## What the Designer Does Not Do
The designer does not create the official form from nothing.

It does not replace the need for the approved document layout.

It only:
- displays the uploaded form
- allows the admin to place data areas
- saves where those values should appear

## Common Problems Staff May Notice
### 1. Data looks shifted or misaligned
Possible reason:
- the uploaded document size and the stored design do not match

What to do:
- check whether the correct file was uploaded
- check the preview
- ask the admin or developer to confirm the stored page size

### 2. A field is missing in the output
Possible reason:
- the field was not added in the designer
- the field was not mapped to the correct data source

What to do:
- ask the admin to open the designer and verify the field

### 3. Wrong revision is being used
Possible reason:
- the needed revision is not the active one

What to do:
- verify which revision is active

### 4. The file opens but the layout is wrong
Possible reason:
- the source document was updated, but the revision was not redesigned or rechecked

What to do:
- create or update the revision
- preview before activating

## Good Practice for Admin Staff
- Always upload the final approved document before designing.
- Always preview before activating a revision.
- If the source document changes, create or update the revision instead of assuming old field positions will still match.
- Keep revision notes clear so staff know what changed.
- Treat the active revision as the official printable version.

## Short Example
Example flow for a Request for Analysis form:

1. Admin creates `RFA` controlled form.
2. Admin uploads the official RFA PDF.
3. Admin opens the designer.
4. Admin places fields like customer name, sample table, analysis selected, and signatures.
5. Admin saves the layout.
6. Admin checks preview with sample data.
7. Admin activates the revision.
8. Staff use the system normally, and generated RFA documents follow that approved layout.

## Microbiological Non-Drinking Water Result Sheet (LSP 7.8 FO4)

The official combined result form for wastewater / non-potable microbiological examination is versioned in the repository at:

`resources/forms/lsp-7.8-fo4-micro-non-drinking-water.pdf`

Do not rebuild this sheet in HTML. Bind it as a Controlled Form so Analyst can print the two coliform results on the official overlay.

What to do in Admin → Controlled Forms:

1. Create a form with category **Analysis Result**.
2. Upload that PDF as the source document (form code on the sheet is `LSP 7.8 FO4`).
3. On the analysis bindings, choose the package **Microbiological Examination — Non-Drinking Water**. Prefer package binding so the sheet still prints when the customer unchecks a member (unchecked slots show `-`).
4. Open the designer. With the package selected, the Field Library shows only the boxes this sheet needs. Under **Result sheet header** and **This package**, map:
   - Customer, Address, Date & Time Sample Received, Sample Description, Date & Time of Sampling, Date & Time of Analysis, Date of Release of Result, Sample Collected by
   - **Total Coliform (MPN/100ml) result** on the Total Coliform cell (prints Passed or Failed)
   - **Thermotolerant Coliform (MPN/100ml) result** on the Thermotolerant Coliform cell (prints Passed or Failed)
   - Reference Number → LSO No.
   - First sample code
   - Analyst name
5. Preview with sample data, then activate the revision. Use **Show all fields** only if you need extra mappings.

The kiosk offers the **package** card. Customers may uncheck individual package tests; the FO4 sheet still applies, and unchecked tests print as `-`.

## Microbiological Drinking Water Result Sheet (LSP 7.8 FO5)

The official combined result form for drinking-water / potability bacteriology is versioned at:

`resources/forms/lsp-7.8-fo5-micro-drinking-water.pdf`

Form code on the sheet: `LSP 7.8 FO5` (Water Bacteriology Analysis Test Report). Do not rebuild this sheet in HTML.

What to do in Admin → Controlled Forms:

1. Create a form with category **Analysis Result**.
2. Upload that PDF (or use **New PDF revision** if updating an existing form).
3. Bind the package **Microbiological Examination — Drinking Water** (members in sheet order: Total Coliform `MB-02A`, Thermotolerant Coliform `MB-02B`, HPC `MB-01`). Package binding is required so partial selections still use FO5.
4. Open the designer. Under **Result sheet header** and **This package**, map:
   - **Customer**, **Address**, **Ref. No.**, **Control No. (RFA)** (same number as the Request for Analysis control number), **Sample Collected by**
   - **Date/Time of Collection** — RFA sampling date and time
   - **Receipt** — when Receiving marks the samples as received
   - **Examination** — when the analyst encodes results
   - **Report** and **Release** — Head signature date after the designated analyst sends the job for review. Blank until signed.
   - **Water Supply** and **Sampling Point** — both use the job order **sample source** (Local water district, Tank, Faucet, Deepwell). If **Others** is selected, staff must specify the source; that specified text prints on the sheet.
   - **Sample Classification** — including **Others** with a required specify line.
   - **Sample Description** — **Water in sterile bottle** when that Field Data (Potability) option is selected on the RFA (not the RFA sample description text)
   - **Sample Code (RFA)** — the Sample Code from the Request for Analysis only (not code joined with description)
   - Each test **measured value** on the Results of Analysis cell
   - Each test **result** on the Interpretation Pass/Fail cell (prints Passed or Failed)
   - Analyst name
5. Preview, then activate. Package price on the kiosk is a placeholder (₱900) until updated in Admin → Packages.

The kiosk shows this package for **Potability**. HPC (`MB-01`) remains available as a standalone test; the two coliform members stay hidden except inside packages.

## Summary
Controlled Forms helps the laboratory keep document generation consistent, traceable, and revision-based.

In simple terms:
- upload the official form
- design where data should appear
- preview it
- activate the correct revision
- let the system use that approved version

If the uploaded form is `8.5 × 11`, `A4`, or another valid size, the important thing is that the design is done on top of that exact uploaded file.
