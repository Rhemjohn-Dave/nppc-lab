import { useMemo } from 'react';

export const RULER_SIZE = 22;

type Tick = { mm: number; px: number; major: boolean; medium: boolean };

function buildTicks(lengthMm: number, pxPerMm: number): Tick[] {
    const ticks: Tick[] = [];
    const step = pxPerMm >= 4 ? 1 : pxPerMm >= 2 ? 5 : 10;
    const max = Math.ceil(lengthMm);

    for (let mm = 0; mm <= max; mm += step) {
        ticks.push({
            mm,
            px: mm * pxPerMm,
            major: mm % 10 === 0,
            medium: mm % 5 === 0,
        });
    }

    return ticks;
}

type HorizontalProps = {
    widthPx: number;
    lengthMm: number;
    pxPerMm: number;
    cursorMm: number | null;
};

export function HorizontalRuler({ widthPx, lengthMm, pxPerMm, cursorMm }: HorizontalProps) {
    const ticks = useMemo(() => buildTicks(lengthMm, pxPerMm), [lengthMm, pxPerMm]);
    const cursorPx = cursorMm === null ? null : cursorMm * pxPerMm;

    return (
        <div
            className="relative shrink-0 overflow-hidden border-b border-zinc-300 bg-zinc-100"
            style={{ width: widthPx, height: RULER_SIZE }}
        >
            <svg width={widthPx} height={RULER_SIZE} className="block">
                {ticks.map((tick) => {
                    const h = tick.major ? 12 : tick.medium ? 8 : 5;

                    return (
                        <g key={`h-${tick.mm}`}>
                            <line
                                x1={tick.px}
                                y1={RULER_SIZE - h}
                                x2={tick.px}
                                y2={RULER_SIZE}
                                stroke="#71717a"
                                strokeWidth={tick.major ? 1 : 0.75}
                            />
                            {tick.major && tick.mm > 0 ? (
                                <text
                                    x={tick.px + 3}
                                    y={10}
                                    fill="#52525b"
                                    fontSize={9}
                                    fontFamily="ui-sans-serif, system-ui, sans-serif"
                                >
                                    {tick.mm}
                                </text>
                            ) : null}
                        </g>
                    );
                })}
                {cursorPx !== null ? (
                    <line
                        x1={cursorPx}
                        y1={0}
                        x2={cursorPx}
                        y2={RULER_SIZE}
                        stroke="#1A3694"
                        strokeWidth={1}
                    />
                ) : null}
            </svg>
        </div>
    );
}

type VerticalProps = {
    heightPx: number;
    lengthMm: number;
    pxPerMm: number;
    cursorMm: number | null;
};

export function VerticalRuler({ heightPx, lengthMm, pxPerMm, cursorMm }: VerticalProps) {
    const ticks = useMemo(() => buildTicks(lengthMm, pxPerMm), [lengthMm, pxPerMm]);
    const cursorPx = cursorMm === null ? null : cursorMm * pxPerMm;

    return (
        <div
            className="relative shrink-0 overflow-hidden border-r border-zinc-300 bg-zinc-100"
            style={{ width: RULER_SIZE, height: heightPx }}
        >
            <svg width={RULER_SIZE} height={heightPx} className="block">
                {ticks.map((tick) => {
                    const w = tick.major ? 12 : tick.medium ? 8 : 5;

                    return (
                        <g key={`v-${tick.mm}`}>
                            <line
                                x1={RULER_SIZE - w}
                                y1={tick.px}
                                x2={RULER_SIZE}
                                y2={tick.px}
                                stroke="#71717a"
                                strokeWidth={tick.major ? 1 : 0.75}
                            />
                            {tick.major && tick.mm > 0 ? (
                                <text
                                    x={10}
                                    y={tick.px - 3}
                                    fill="#52525b"
                                    fontSize={9}
                                    fontFamily="ui-sans-serif, system-ui, sans-serif"
                                    transform={`rotate(-90 10 ${tick.px - 3})`}
                                >
                                    {tick.mm}
                                </text>
                            ) : null}
                        </g>
                    );
                })}
                {cursorPx !== null ? (
                    <line
                        x1={0}
                        y1={cursorPx}
                        x2={RULER_SIZE}
                        y2={cursorPx}
                        stroke="#1A3694"
                        strokeWidth={1}
                    />
                ) : null}
            </svg>
        </div>
    );
}

export function RulerCorner() {
    return (
        <div
            className="flex shrink-0 items-end justify-end border-b border-r border-zinc-300 bg-zinc-100 pr-0.5 pb-0.5 text-[8px] font-medium text-zinc-500"
            style={{ width: RULER_SIZE, height: RULER_SIZE }}
        >
            mm
        </div>
    );
}
