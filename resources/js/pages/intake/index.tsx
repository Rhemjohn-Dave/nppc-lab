import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    FileCheck2,
    FlaskConical,
    Lock,
    Package,
    RotateCcw,
    ShieldCheck,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import IntakePortalChrome from '@/components/intake/intake-portal-chrome';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { limsPageBackground } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type LandingPackage = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    default_price: string | number;
    form_code: string | null;
    category: string | null;
    category_label: string | null;
    tests: Array<{ id: number; name: string }>;
};

type Props = {
    packages: LandingPackage[];
};

const PROCEDURE = [
    {
        step: '01',
        title: 'Online Pre-Registration',
        body: 'Complete this portal to receive a system-allocated job order reference and sample labels before you arrive.',
        meta: 'Input time: ~3–5 mins',
        accent: 'Instant JO reference',
    },
    {
        step: '02',
        title: 'Counter Receiving & Custody',
        body: 'Bring labeled samples to Receiving for container inspection, temperature check, and Chain of Custody logging.',
        meta: 'Inspection: ~2 mins',
        accent: 'Temp verification',
    },
    {
        step: '03',
        title: 'Digital COA Certificate Release',
        body: 'After analysis and Head release, encrypted PDF Certificates of Analysis are delivered using your registered contacts.',
        meta: 'Delivery: Email / pickup',
        accent: 'ISO 17025 ready',
    },
] as const;

export default function IntakeIndex({ packages }: Props) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [mode, setMode] = useState<'choose' | 'returning'>(() =>
        errors?.query ? 'returning' : 'choose',
    );
    const [filter, setFilter] = useState<string>('all');

    const categoryFilters = useMemo(() => {
        const labels = new Map<string, string>();
        packages.forEach((pkg) => {
            if (pkg.category && pkg.category_label) {
                labels.set(pkg.category, pkg.category_label);
            }
        });
        return Array.from(labels.entries()).map(([slug, label]) => ({
            slug,
            label,
        }));
    }, [packages]);

    const filteredPackages = useMemo(() => {
        if (filter === 'all') {
            return packages;
        }
        return packages.filter((pkg) => pkg.category === filter);
    }, [filter, packages]);

    return (
        <div
            className={cn(
                'relative min-h-screen text-slate-900',
                limsPageBackground,
            )}
        >
            <Head title="Customer Intake Portal" />
            <IntakePortalChrome />

            {/* Hero: brand + intake actions */}
            <section className="mx-auto grid w-full max-w-[1680px] gap-0 px-4 py-6 sm:px-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-6 lg:px-6 lg:py-8 xl:px-8">
                <aside className="relative hidden min-h-[28rem] overflow-hidden rounded-xl lg:block">
                    <img
                        src="/nppc.jpg"
                        alt="NPPC laboratory analysts at work"
                        className="absolute inset-0 size-full object-cover object-[center_30%]"
                    />
                    <div className="absolute inset-0 bg-[linear-gradient(160deg,rgba(10,28,84,0.88)_0%,rgba(10,28,84,0.55)_55%,rgba(10,28,84,0.75)_100%)]" />
                    <div className="relative flex h-full flex-col justify-between p-8 text-white">
                        <div>
                            <p className="text-[10px] font-semibold tracking-[0.2em] text-white/70 uppercase">
                                NPPC Diagnostics & Testing
                            </p>
                            <span className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-400/40 bg-emerald-500/15 px-2.5 py-1 text-[10px] font-semibold text-emerald-100">
                                <span className="size-1.5 rounded-full bg-emerald-400" />
                                Receiving counter open
                            </span>
                            <h1 className="mt-6 font-heading text-3xl font-semibold tracking-tight xl:text-4xl">
                            Advancing science,
                            <span className="mt-1 block font-normal text-white/85">
                                supporting industries.
                            </span>
                            </h1>
                        <p className="mt-4 max-w-md text-sm leading-relaxed text-white/75">
                            Trusted laboratory testing for agriculture,
                                aquaculture, and industrial partners across
                                Negros.
                            </p>
                        </div>
                        <div className="space-y-1 text-xs text-white/70">
                            <p>Tel 034-4332131 · 034-4352613</p>
                            <p>nppclab@gmail.com</p>
                            <p className="pt-2 text-[10px] tracking-wide text-white/50 uppercase">
                                Methods: SMEWW · AOAC · USEPA
                            </p>
                        </div>
                    </div>
                </aside>

                <div className="flex flex-col justify-center rounded-xl border border-slate-200/80 bg-white p-5 shadow-xs sm:p-7">
                    <p className="text-[10px] font-semibold tracking-[0.18em] text-nppc-700 uppercase">
                        NPPC Intake Portal
                    </p>
                    <h2 className="mt-1 font-heading text-2xl font-semibold text-nppc-900 sm:text-3xl">
                        Start your Request for Analysis
                    </h2>
                    <p className="mt-2 max-w-xl text-sm text-slate-600">
                        Submit online, then bring your samples to Receiving.
                        Typical turnaround is 5–7 working days.
                    </p>

                    {mode === 'choose' ? (
                        <div className="mt-6 grid gap-4 sm:grid-cols-2">
                            <div className="flex flex-col rounded-xl border border-nppc-200 bg-gradient-to-b from-nppc-50/80 to-white p-5">
                                <span className="inline-flex w-fit items-center rounded-md bg-nppc-900 px-2 py-0.5 text-[10px] font-semibold text-white">
                                    Guided · ~3–5 mins
                                </span>
                                <div className="mt-4 flex size-10 items-center justify-center rounded-full bg-nppc-900 text-white">
                                    <UserPlus className="size-5" />
                            </div>
                                <h3 className="mt-3 font-heading text-lg font-semibold text-nppc-900">
                                    New customer
                                </h3>
                                <p className="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">
                                    First visit? Start a guided request with
                                    your details, samples, and tests.
                                </p>
                                <ul className="mt-3 space-y-1 text-[11px] text-slate-500">
                                    <li className="flex items-center gap-1.5">
                                        <Check className="size-3 text-nppc-700" />
                                        Auto job order reference
                                    </li>
                                    <li className="flex items-center gap-1.5">
                                        <Check className="size-3 text-nppc-700" />
                                        Packages & individual tests
                                    </li>
                                </ul>
                                <Button
                                    asChild
                                    className="mt-5 h-10 gap-2 bg-nppc-900 hover:bg-nppc-700"
                                >
                                    <Link href="/intake/create">
                                        Start Intake Request
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                        </div>

                            <div className="flex flex-col rounded-xl border border-slate-200 bg-white p-5">
                                <span className="inline-flex w-fit items-center rounded-md border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-800">
                                    1-Click Recall
                                </span>
                                <div className="mt-4 flex size-10 items-center justify-center rounded-full bg-nppc-700 text-white">
                                    <RotateCcw className="size-5" />
                    </div>
                                <h3 className="mt-3 font-heading text-lg font-semibold text-nppc-900">
                                    Returning customer
                                </h3>
                                <p className="mt-1.5 text-sm leading-relaxed text-slate-600">
                                    Look up email, contact, or name from a past
                                    visit to reuse customer details.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-auto h-10 gap-2 border-nppc-900/25 text-nppc-900 hover:bg-nppc-50"
                                    onClick={() => setMode('returning')}
                                >
                                        Find my details
                                    <ArrowRight className="size-4" />
                                </Button>
                            </div>
                            </div>
                        ) : (
                        <div className="mt-6 max-w-md rounded-xl border border-slate-200 bg-slate-50/50 p-5">
                                <button
                                    type="button"
                                    onClick={() => setMode('choose')}
                                    className="text-sm font-medium text-nppc-700 hover:text-nppc-900"
                                >
                                    ← Back to options
                                </button>
                            <h3 className="mt-3 font-heading text-xl font-semibold text-nppc-900">
                                    Returning customer
                            </h3>
                            <p className="mt-1 text-sm text-slate-600">
                                    Enter any detail from a previous visit.
                                </p>
                                <Form
                                    action="/intake/lookup"
                                    method="post"
                                className="mt-4 space-y-4"
                                >
                                    {({ processing }) => (
                                        <>
                                            <div className="space-y-2">
                                                <Label htmlFor="query">
                                                    Email, contact, or name
                                                </Label>
                                                <Input
                                                    id="query"
                                                    name="query"
                                                    required
                                                    autoFocus
                                                className="h-11 text-base"
                                                    placeholder="e.g. 0917… or name@email.com"
                                                />
                                                <InputError
                                                    message={errors?.query}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            className="h-11 w-full gap-2 bg-nppc-900 text-base hover:bg-nppc-700"
                                            >
                                                Continue with my details
                                            <ArrowRight className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}
                    </div>
            </section>

            {/* Drop-off procedure */}
            <section
                id="drop-off"
                className="border-y border-slate-200/80 bg-white"
            >
                <div className="mx-auto w-full max-w-[1680px] px-4 py-10 sm:px-5 lg:px-6 xl:px-8">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-[10px] font-semibold tracking-[0.18em] text-nppc-700 uppercase">
                                How it works
                            </p>
                            <h2 className="mt-1 font-heading text-xl font-semibold text-nppc-900 sm:text-2xl">
                                Physical Drop-off & Sample Intake Procedure
                            </h2>
                        </div>
                        <span className="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold text-sky-800">
                            Cold chain ready · follow preservation guidance
                        </span>
                    </div>

                    <div className="mt-6 grid gap-4 md:grid-cols-3">
                        {PROCEDURE.map((phase) => (
                            <article
                                key={phase.step}
                                className="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-xs"
                            >
                                <span className="pointer-events-none absolute top-3 right-4 font-heading text-4xl font-bold text-slate-100">
                                    {phase.step}
                                </span>
                                <div className="relative flex size-9 items-center justify-center rounded-lg bg-nppc-50 text-nppc-900">
                                    {phase.step === '01' ? (
                                        <Package className="size-4" />
                                    ) : phase.step === '02' ? (
                                        <FlaskConical className="size-4" />
                                    ) : (
                                        <FileCheck2 className="size-4" />
                                    )}
                                </div>
                                <h3 className="relative mt-4 font-heading text-base font-semibold text-nppc-900">
                                    {phase.title}
                                </h3>
                                <p className="relative mt-2 text-sm leading-relaxed text-slate-600">
                                    {phase.body}
                                </p>
                                <div className="relative mt-4 flex flex-wrap gap-x-3 gap-y-1 border-t border-slate-100 pt-3 text-[11px]">
                                    <span className="text-slate-500">
                                        {phase.meta}
                                    </span>
                                    <span className="font-medium text-emerald-700">
                                        {phase.accent}
                                    </span>
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            {/* Package catalog — browse only */}
            <section className="mx-auto w-full max-w-[1680px] px-4 py-10 sm:px-5 lg:px-6 xl:px-8">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-[10px] font-semibold tracking-[0.18em] text-nppc-700 uppercase">
                            Service catalog
                        </p>
                        <h2 className="mt-1 font-heading text-xl font-semibold text-nppc-900 sm:text-2xl">
                            Standard Analytical Suites
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            Browse packages below. Selecting starts a new
                            intake — you choose tests again in the wizard.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => setFilter('all')}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                filter === 'all'
                                    ? 'bg-nppc-900 text-white'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:border-nppc-300',
                            )}
                        >
                            All Packages
                        </button>
                        {categoryFilters.map((cat) => (
                            <button
                                key={cat.slug}
                                type="button"
                                onClick={() => setFilter(cat.slug)}
                                className={cn(
                                    'rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                    filter === cat.slug
                                        ? 'bg-nppc-900 text-white'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:border-nppc-300',
                                )}
                            >
                                {cat.label}
                            </button>
                        ))}
                    </div>
                </div>

                {filteredPackages.length === 0 ? (
                    <p className="mt-8 text-sm text-slate-500">
                        No packages available in this category yet. Start a
                        request to browse individual tests.
                    </p>
                ) : (
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {filteredPackages.map((pkg) => {
                            const preview = pkg.tests.slice(0, 4);
                            return (
                                <article
                                    key={pkg.id}
                                    className="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-xs"
                                >
                                    <div className="flex flex-wrap gap-1.5">
                                        {pkg.form_code || pkg.code ? (
                                            <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-medium text-slate-600">
                                                {pkg.form_code || pkg.code}
                                            </span>
                                        ) : null}
                                        {pkg.category_label ? (
                                            <span className="rounded bg-nppc-50 px-1.5 py-0.5 text-[10px] font-medium text-nppc-800">
                                                {pkg.category_label}
                                            </span>
                                        ) : null}
                                    </div>
                                    <h3 className="mt-3 font-heading text-base font-semibold text-nppc-900">
                                        {pkg.name}
                                    </h3>
                                    {pkg.description ? (
                                        <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-600">
                                            {pkg.description}
                                        </p>
                                    ) : null}
                                    <ul className="mt-3 flex-1 space-y-1">
                                        {preview.map((test) => (
                                            <li
                                                key={test.id}
                                                className="flex items-start gap-1.5 text-xs text-slate-700"
                                            >
                                                <Check className="mt-0.5 size-3 shrink-0 text-nppc-700" />
                                                <span className="min-w-0">
                                                    {test.name}
                                                </span>
                                            </li>
                                        ))}
                                        {pkg.tests.length > 4 ? (
                                            <li className="pl-4 text-[11px] text-slate-500">
                                                +{pkg.tests.length - 4} more
                                            </li>
                                        ) : null}
                                    </ul>
                                    <div className="mt-4 flex items-end justify-between gap-2 border-t border-slate-100 pt-3">
                                        <div>
                                            <p className="text-[9px] font-semibold tracking-wider text-slate-500 uppercase">
                                                Fee per sample
                                            </p>
                                            <p className="font-heading text-lg font-extrabold tabular-nums text-nppc-900">
                                                ₱
                                                {Number(
                                                    pkg.default_price,
                                                ).toFixed(2)}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-8 border-nppc-900/30 bg-nppc-50/50 text-nppc-900 hover:bg-nppc-50"
                                        >
                                            <Link href="/intake/create">
                                                Select
                                            </Link>
                                        </Button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-nppc-200 bg-nppc-50/60 px-4 py-3">
                    <p className="text-sm text-nppc-900">
                        Need a custom or bulk quotation? Start intake and note
                        requirements under Other tests, or call Receiving.
                    </p>
                    <Button
                        asChild
                        className="h-8 gap-1.5 bg-nppc-900 hover:bg-nppc-700"
                    >
                        <Link href="/intake/create">
                            Start request
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </section>

            {/* Accreditations */}
            <section className="border-t border-slate-200/80 bg-white">
                <div className="mx-auto grid w-full max-w-[1680px] gap-4 px-4 py-8 sm:grid-cols-3 sm:px-5 lg:px-6 xl:px-8">
                    {[
                        {
                            icon: ShieldCheck,
                            title: 'ISO/IEC 17025:2017',
                            body: 'Accredited laboratory quality management for competent testing.',
                        },
                        {
                            icon: FileCheck2,
                            title: 'DENR & DOH Recognized',
                            body: 'Methods aligned with applicable Philippine regulatory frameworks.',
                        },
                        {
                            icon: Lock,
                            title: 'RA 10173 Data Privacy',
                            body: 'Customer and sample data handled under Philippine privacy law.',
                        },
                    ].map((item) => (
                        <div
                            key={item.title}
                            className="rounded-xl border border-slate-200 bg-slate-50/50 p-4"
                        >
                            <item.icon className="size-5 text-nppc-900" />
                            <h3 className="mt-2 font-heading text-sm font-semibold text-nppc-900">
                                {item.title}
                            </h3>
                            <p className="mt-1 text-xs leading-relaxed text-slate-600">
                                {item.body}
                            </p>
                        </div>
                    ))}
                </div>
                <div className="border-t border-slate-100">
                    <div className="mx-auto flex w-full max-w-[1680px] flex-wrap items-center justify-between gap-2 px-4 py-3 text-[11px] text-slate-500 sm:px-5 lg:px-6 xl:px-8">
                        <p>
                            ISO/IEC 17025 Accredited Laboratory · NPPC
                            Analytical & Diagnostic Testing
                        </p>
                        <p className="inline-flex items-center gap-1.5">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            Secure SSL Encrypted
                        </p>
                    </div>
            </div>
            </section>
        </div>
    );
}

IntakeIndex.layout = null;
