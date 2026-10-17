const PLAIN_DECIMAL = /^[+-]?(?:\d+\.?\d*|\.\d+)$/;
const E_NOTATION = /^[+-]?(?:\d+\.?\d*|\.\d+)[eE][+-]?\d+$/;
const UNICODE_SCIENTIFIC = /^([+-]?(?:\d+\.?\d*|\.\d+))\s?×\s?10([⁺⁻]?[⁰¹²³⁴⁵⁶⁷⁸⁹]+)$/u;

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

const FROM_SUPERSCRIPT: Record<string, string> = Object.fromEntries(
    Object.entries(SUPERSCRIPT_DIGITS).map(([plain, sup]) => [sup, plain]),
);

const SMALL_MAGNITUDE = 0.001;
const LARGE_MAGNITUDE = 10_000;
const SIGNIFICANT_FIGURES = 2;

function toSuperscript(exponent: number): string {
    return String(exponent)
        .split('')
        .map((ch) => SUPERSCRIPT_DIGITS[ch] ?? ch)
        .join('');
}

function parseResultNumber(text: string): number | null {
    if (PLAIN_DECIMAL.test(text) || E_NOTATION.test(text)) {
        return Number(text);
    }

    const match = text.match(UNICODE_SCIENTIFIC);
    if (!match) {
        return null;
    }

    const exponent = [...match[2]].map((ch) => FROM_SUPERSCRIPT[ch] ?? ch).join('');

    return Number(`${match[1]}e${exponent}`);
}

/**
 * Convert very large or very small numeric results to 2 significant figures in
 * engineering notation (power of ten is a multiple of 3, written as a superscript).
 * Accepts plain decimals, e-notation and existing "a×10ⁿ"; leaves ND, inequalities
 * and ordinary values unchanged.
 *
 * Examples: 23456 → 23×10³ ; 0.000234 → 230×10⁻⁶ ; 1234567 → 1.2×10⁶ ; 42 → 42
 */
export function formatScientificResult(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed) {
        return raw;
    }

    const n = parseResultNumber(trimmed);
    if (n === null || !Number.isFinite(n) || n === 0) {
        return raw;
    }

    const abs = Math.abs(n);
    if (abs >= SMALL_MAGNITUDE && abs < LARGE_MAGNITUDE) {
        return raw;
    }

    const rounded = Number(n.toPrecision(SIGNIFICANT_FIGURES));
    const exponent = Number(rounded.toExponential().split('e')[1]);
    const engExponent = Math.floor(exponent / 3) * 3;
    const integerDigits = exponent - engExponent + 1;
    const coefficient = (rounded / 10 ** engExponent).toFixed(Math.max(0, SIGNIFICANT_FIGURES - integerDigits));

    return `${coefficient}×10${toSuperscript(engExponent)}`;
}
