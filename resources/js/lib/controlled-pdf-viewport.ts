/**
 * Shared viewport helpers for Form Designer and PDF preview.
 * Coordinate authority is FPDI millimetres from the server (canonical PDF).
 * pdf.js is only used to rasterize the background; never re-derive page mm from it alone.
 */

/** CSS px per PDF point at 96dpi (1pt = 1/72in). */
export const PDF_CSS_SCALE = 96 / 72;

/** Convert PDF user-space points (72dpi) to millimetres. */
export function mmFromPdfPoints(points: number): number {
    return Number(((points * 25.4) / 72).toFixed(2));
}

/** Page size in CSS pixels for a given mm size and zoom (relative to PDF_CSS_SCALE). */
export function pagePxSize(
    widthMm: number,
    heightMm: number,
    zoom = 1,
): { widthPx: number; heightPx: number } {
    const scale = PDF_CSS_SCALE * zoom;
    const widthPx = (widthMm / 25.4) * 72 * scale;
    const heightPx = (heightMm / 25.4) * 72 * scale;

    return { widthPx, heightPx };
}

/**
 * Zoom factor so the page (at PDF_CSS_SCALE, zoom=1) fits the available width.
 * Returns a value suitable for multiplying PDF_CSS_SCALE (designer `zoom` state).
 */
export function fitScaleToWidth(
    availableWidthPx: number,
    pageWidthPxAtBaseScale: number,
    min = 0.3,
    max = 2.5,
): number {
    if (pageWidthPxAtBaseScale <= 0 || availableWidthPx <= 0) {
        return 1;
    }

    const fit = availableWidthPx / pageWidthPxAtBaseScale;

    return Number(Math.min(Math.max(fit, min), max).toFixed(2));
}

/**
 * Absolute pdf.js viewport scale so rendered width matches FPDI mm × CSS scale × zoom.
 * Use when pdf.js MediaBox differs from FPDI template size — stretch raster to FPDI basis.
 */
export function pdfjsViewportScaleForFpdiMm(
    widthMm: number,
    unscaledViewportWidthPts: number,
    zoom = 1,
): number {
    if (unscaledViewportWidthPts <= 0 || widthMm <= 0) {
        return PDF_CSS_SCALE * zoom;
    }

    const targetWidthPx = (widthMm / 25.4) * 72 * PDF_CSS_SCALE * zoom;

    return targetWidthPx / unscaledViewportWidthPts;
}
