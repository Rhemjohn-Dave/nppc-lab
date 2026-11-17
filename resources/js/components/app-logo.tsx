import NppcLogo from '@/components/nppc-logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white ring-1 ring-slate-200/80 group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:ring-slate-200">
                <NppcLogo className="size-[122%] max-w-none group-data-[collapsible=icon]:size-[128%]" />
            </div>
            <div className="ml-2 grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-heading text-sm font-bold tracking-tight text-nppc-900">
                    NPPC Lab
                </span>
                <span className="truncate text-[10px] font-semibold tracking-[0.16em] text-nppc-700 uppercase">
                    LIMS
                </span>
            </div>
        </>
    );
}
