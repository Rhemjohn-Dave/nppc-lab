import NppcLogo from '@/components/nppc-logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-white ring-1 ring-[#1A3694]/15">
                <NppcLogo className="size-7" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold text-[#1A3694]">
                    NPPC Lab
                </span>
            </div>
        </>
    );
}
