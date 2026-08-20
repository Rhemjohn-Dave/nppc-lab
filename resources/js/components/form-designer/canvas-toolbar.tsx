import { ChevronLeft, ChevronRight, Magnet, Maximize2, Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    page: number;
    pageCount: number;
    zoom: number;
    pageWidthMm?: number | null;
    pageHeightMm?: number | null;
    snapEnabled?: boolean;
    onSnapEnabledChange?: (enabled: boolean) => void;
    onPageChange: (page: number) => void;
    onZoomChange: (zoom: number) => void;
    onFitWidth: () => void;
    onFitPage: () => void;
};

export default function CanvasToolbar({
    page,
    pageCount,
    zoom,
    pageWidthMm,
    pageHeightMm,
    snapEnabled = true,
    onSnapEnabledChange,
    onPageChange,
    onZoomChange,
    onFitWidth,
    onFitPage,
}: Props) {
    const zoomPercent = Math.round(zoom * 100);

    return (
        <div className="relative z-10 flex shrink-0 flex-wrap items-center justify-center gap-2 border-t bg-white/95 px-3 py-2 shadow-[0_-2px_8px_rgba(0,0,0,0.04)] backdrop-blur-sm">
            <div className="flex items-center gap-1 rounded-lg border bg-white px-1 py-0.5">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={zoom <= 0.4}
                    onClick={() => onZoomChange(Math.max(0.4, Number((zoom - 0.1).toFixed(2))))}
                >
                    <Minus className="size-3.5" />
                </Button>
                <span className="min-w-[3rem] text-center text-xs font-medium tabular-nums">
                    {zoomPercent}%
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={zoom >= 2.5}
                    onClick={() => onZoomChange(Math.min(2.5, Number((zoom + 0.1).toFixed(2))))}
                >
                    <Plus className="size-3.5" />
                </Button>
            </div>

            <Button variant="outline" size="sm" className="h-8 text-xs" onClick={onFitWidth}>
                Fit width
            </Button>
            <Button variant="outline" size="sm" className="h-8 text-xs" onClick={onFitPage}>
                <Maximize2 className="size-3.5" />
                Fit page
            </Button>

            {onSnapEnabledChange ? (
                <Button
                    variant={snapEnabled ? 'default' : 'outline'}
                    size="sm"
                    className={`h-8 text-xs ${snapEnabled ? 'bg-[#1A3694] hover:bg-[#1A3694]/90' : ''}`}
                    title="Snap to other boxes and page edges. Hold Alt to disable while dragging."
                    onClick={() => onSnapEnabledChange(!snapEnabled)}
                >
                    <Magnet className="size-3.5" />
                    Snap
                </Button>
            ) : null}

            <span className="hidden text-xs text-muted-foreground lg:inline">
                Arrows nudge 0.5 mm · Shift+Arrows 5 mm
            </span>

            <div className="flex items-center gap-1 rounded-lg border bg-white px-1 py-0.5">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={page <= 1}
                    onClick={() => onPageChange(page - 1)}
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <span className="min-w-[5rem] text-center text-xs tabular-nums">
                    Page {page} / {pageCount}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={page >= pageCount}
                    onClick={() => onPageChange(page + 1)}
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>

            {pageWidthMm && pageHeightMm ? (
                <span className="hidden text-xs text-muted-foreground sm:inline">
                    {Number(pageWidthMm).toFixed(1)} × {Number(pageHeightMm).toFixed(1)} mm
                </span>
            ) : null}
        </div>
    );
}
