const PLAIN_DECIMAL = /^[+-]?(?:\d+\.?\d*|\.\d+)$/;

const SUPERSCRIPT_DIGITS: Record<string, string> = {
    '0': '⁰',
    '1': '¹',
    '2': '²',
    '3': '³',
    '4': '⁴',
    '5': '⁵',
    '6': '⁶',
    '7': '⁷',
    '8': '⁸',
    '9': '⁹',
    '-': '⁻',
    '+': '⁺',
};

const SMALL_MAGNITUDE = 0.001;
const LARGE_MAGNITUDE = 10_000;

function toSuperscript(exponent: number): string {
    return String(exponent)
        .split('')
        .map((ch) => SUPERSCRIPT_DIGITS[ch] ?? ch)
        .join('');
}

function formatCoefficient(value: number): string {
    // Enough precision for lab decimals without trailing float noise.
    const raw = value.toPrecision(6);
    const asNumber = Number(raw);
    if (!Number.isFinite(asNumber)) {
        return raw;
    }
    // Drop trailing zeros from decimal form (e.g. 2.30000 → 2.3).
    return String(asNumber);
}

/**
 * Convert awkward plain decimals to Unicode scientific notation for storage/display.
 * Leaves ND, inequalities, existing sci/e notation, and ordinary decimals unchanged.
 *
 * Examples: 0.00023 → 2.3×10⁻⁴ ; 10000 → 1×10⁴ ; 42 → 42
 */
export function formatScientificResult(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed || !PLAIN_DECIMAL.test(trimmed)) {
        return raw;
    }

    const n = Number(trimmed);
    if (!Number.isFinite(n) || n === 0) {
        return raw;
    }

    const abs = Math.abs(n);
    if (abs >= SMALL_MAGNITUDE && abs < LARGE_MAGNITUDE) {
        return raw;
    }

    const exponent = Math.floor(Math.log10(abs));
    const coefficient = n / 10 ** exponent;
    const coeffText = formatCoefficient(coefficient);

    return `${coeffText}×10${toSuperscript(exponent)}`;
}
