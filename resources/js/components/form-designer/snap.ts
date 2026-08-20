export type SnapBox = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type SnapGuides = {
    vertical: number[];
    horizontal: number[];
};

const emptyGuides = (): SnapGuides => ({ vertical: [], horizontal: [] });

function unique(values: number[]): number[] {
    return [...new Set(values.map((value) => Number(value.toFixed(3))))];
}

function nearest(
    value: number,
    targets: number[],
    threshold: number,
): { target: number; delta: number } | null {
    let best: { target: number; delta: number } | null = null;

    for (const target of targets) {
        const delta = target - value;
        const distance = Math.abs(delta);

        if (distance <= threshold && (best === null || distance < Math.abs(best.delta))) {
            best = { target, delta };
        }
    }

    return best;
}

function pickEdgeSnap(
    edges: Array<{ value: number; apply: (target: number) => number }>,
    targets: number[],
    threshold: number,
): { position: number; guide: number } | null {
    let best: { position: number; guide: number; distance: number } | null = null;

    for (const edge of edges) {
        const match = nearest(edge.value, targets, threshold);

        if (!match) {
            continue;
        }

        const distance = Math.abs(match.delta);

        if (best === null || distance < best.distance) {
            best = {
                position: edge.apply(match.target),
                guide: match.target,
                distance,
            };
        }
    }

    return best ? { position: best.position, guide: best.guide } : null;
}

export function collectSnapTargets(
    others: SnapBox[],
    pageWidthMm: number,
    pageHeightMm: number,
): { xs: number[]; ys: number[]; widths: number[]; heights: number[] } {
    const xs = [0, pageWidthMm / 2, pageWidthMm];
    const ys = [0, pageHeightMm / 2, pageHeightMm];
    const widths: number[] = [];
    const heights: number[] = [];

    for (const box of others) {
        xs.push(box.x, box.x + box.width / 2, box.x + box.width);
        ys.push(box.y, box.y + box.height / 2, box.y + box.height);
        widths.push(box.width);
        heights.push(box.height);
    }

    return { xs, ys, widths, heights };
}

export function snapThresholdMm(pxPerMm: number): number {
    return Math.max(0.75, 7 / Math.max(pxPerMm, 0.01));
}

export function snapMove(
    box: SnapBox,
    others: SnapBox[],
    pageWidthMm: number,
    pageHeightMm: number,
    threshold: number,
): { x: number; y: number; guides: SnapGuides } {
    const { xs, ys } = collectSnapTargets(others, pageWidthMm, pageHeightMm);
    const guides = emptyGuides();

    const xSnap = pickEdgeSnap(
        [
            { value: box.x, apply: (target) => target },
            { value: box.x + box.width / 2, apply: (target) => target - box.width / 2 },
            { value: box.x + box.width, apply: (target) => target - box.width },
        ],
        xs,
        threshold,
    );

    const ySnap = pickEdgeSnap(
        [
            { value: box.y, apply: (target) => target },
            { value: box.y + box.height / 2, apply: (target) => target - box.height / 2 },
            { value: box.y + box.height, apply: (target) => target - box.height },
        ],
        ys,
        threshold,
    );

    const x = Math.max(0, xSnap?.position ?? box.x);
    const y = Math.max(0, ySnap?.position ?? box.y);

    if (xSnap) {
        guides.vertical.push(xSnap.guide);
    }

    if (ySnap) {
        guides.horizontal.push(ySnap.guide);
    }

    return { x, y, guides: { vertical: unique(guides.vertical), horizontal: unique(guides.horizontal) } };
}

export function snapResize(
    box: SnapBox,
    others: SnapBox[],
    pageWidthMm: number,
    pageHeightMm: number,
    threshold: number,
): { width: number; height: number; guides: SnapGuides } {
    const { xs, ys, widths, heights } = collectSnapTargets(others, pageWidthMm, pageHeightMm);
    const guides = emptyGuides();

    const right = box.x + box.width;
    const bottom = box.y + box.height;

    const rightSnap = nearest(right, xs, threshold);
    const widthSnap = nearest(box.width, widths, threshold);
    let width = box.width;

    const rightDistance = rightSnap ? Math.abs(rightSnap.delta) : Number.POSITIVE_INFINITY;
    const widthDistance = widthSnap ? Math.abs(widthSnap.delta) : Number.POSITIVE_INFINITY;

    if (rightSnap && rightDistance <= widthDistance) {
        width = Math.max(1, rightSnap.target - box.x);
        guides.vertical.push(rightSnap.target);
    } else if (widthSnap) {
        width = Math.max(1, widthSnap.target);
        guides.vertical.push(box.x + width);
    }

    const bottomSnap = nearest(bottom, ys, threshold);
    const heightSnap = nearest(box.height, heights, threshold);
    let height = box.height;

    const bottomDistance = bottomSnap ? Math.abs(bottomSnap.delta) : Number.POSITIVE_INFINITY;
    const heightDistance = heightSnap ? Math.abs(heightSnap.delta) : Number.POSITIVE_INFINITY;

    if (bottomSnap && bottomDistance <= heightDistance) {
        height = Math.max(1, bottomSnap.target - box.y);
        guides.horizontal.push(bottomSnap.target);
    } else if (heightSnap) {
        height = Math.max(1, heightSnap.target);
        guides.horizontal.push(box.y + height);
    }

    return {
        width,
        height,
        guides: { vertical: unique(guides.vertical), horizontal: unique(guides.horizontal) },
    };
}

export function snapPoint(
    x: number,
    y: number,
    others: SnapBox[],
    pageWidthMm: number,
    pageHeightMm: number,
    threshold: number,
): { x: number; y: number } {
    const { xs, ys } = collectSnapTargets(others, pageWidthMm, pageHeightMm);
    const xMatch = nearest(x, xs, threshold);
    const yMatch = nearest(y, ys, threshold);

    return {
        x: Math.max(0, xMatch?.target ?? x),
        y: Math.max(0, yMatch?.target ?? y),
    };
}
