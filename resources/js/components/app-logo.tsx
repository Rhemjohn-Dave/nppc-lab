import NppcLogo from '@/components/nppc-logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white ring-1 ring-slate-200/80">
                <NppcLogo className="size-full p-0.5" />
            </div>
            <div className="ml-1.5 grid min-w-0 flex-1 text-left group-data-[collapsible=icon]:hidden">
                <div className="flex items-center gap-1.5">
                    <span className="truncate text-sm leading-tight font-bold tracking-tight text-slate-900">
                        NPPC Lab
                    </span>
                    <span className="shrink-0 rounded bg-nppc-100 px-1.5 py-0.5 text-[10px] font-semibold text-nppc-900">
                        LIMS
                    </span>
                </div>
                <span className="truncate text-[10px] font-medium tracking-wider text-slate-400 uppercase">
                    ISO/IEC 17025 Accredited
                </span>
            </div>
        </>
    );
}
