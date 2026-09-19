type Props = {
    formCode: string;
    revision: string | null;
    effectiveDate: string | null;
};

export default function DocumentControlCallout({
    formCode,
    revision,
    effectiveDate,
}: Props) {
    return (
        <aside className="rounded-lg border border-[#1A3694]/25 bg-[#eef3fb]/80 px-3 py-2.5 text-xs">
            <p className="font-semibold text-[#1A3694]">
                ISO/IEC 17025:2017 document rule
            </p>
            <p className="mt-1 leading-relaxed text-slate-700">
                Official {formCode}
                {revision ? ` · revision ${revision}` : ''}
                {effectiveDate ? `. Effective ${effectiveDate}.` : '.'} Only the
                active revision may be used for analytical testing and reporting.
                Superseded versions remain archived for regulatory audits.
            </p>
        </aside>
    );
}
