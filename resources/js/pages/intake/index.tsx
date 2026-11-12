import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Award,
    Clock,
    FilePlus2,
    FlaskConical,
    History,
    LoaderCircle,
} from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import IntakePortalChrome from '@/components/intake/intake-portal-chrome';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CustomerSuggestion = {
    id: number;
    customer_name: string;
    customer_email: string | null;
    customer_contact: string | null;
    customer_address: string | null;
    company_name: string | null;
    ownership_type: string | null;
};

export default function IntakeIndex() {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [mode, setMode] = useState<'choose' | 'returning'>(() =>
        errors?.query ? 'returning' : 'choose',
    );
    const [query, setQuery] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [suggestions, setSuggestions] = useState<CustomerSuggestion[]>([]);
    const [suggestOpen, setSuggestOpen] = useState(false);
    const [suggestLoading, setSuggestLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const suggestListId = useId();
    const suggestAbort = useRef<AbortController | null>(null);

    useEffect(() => {
        if (mode !== 'returning') {
            return;
        }

        const trimmed = query.trim();
        if (trimmed.length < 2) {
            suggestAbort.current?.abort();
            setSuggestions([]);
            setSuggestLoading(false);
            setSuggestOpen(false);
            setActiveIndex(-1);

            return;
        }

        const timer = window.setTimeout(async () => {
            suggestAbort.current?.abort();
            const controller = new AbortController();
            suggestAbort.current = controller;
            setSuggestLoading(true);

            try {
                const response = await fetch(
                    `/intake/customers/suggest?q=${encodeURIComponent(trimmed)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Suggest failed');
                }

                const payload = (await response.json()) as {
                    customers?: CustomerSuggestion[];
                };
                const rows = payload.customers ?? [];
                setSuggestions(rows);
                setSuggestOpen(true);
                setActiveIndex(rows.length > 0 ? 0 : -1);
            } catch (error) {
                if ((error as Error).name === 'AbortError') {
                    return;
                }
                setSuggestions([]);
                setSuggestOpen(true);
                setActiveIndex(-1);
            } finally {
                if (!controller.signal.aborted) {
                    setSuggestLoading(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timer);
        };
    }, [query, mode]);

    function prefillFromCustomer(customer: CustomerSuggestion) {
        setSelectedId(customer.id);
        setSuggestOpen(false);
        // Only the customer id goes in the URL; the wizard loads details server-side.
        router.get('/intake/create', {
            customer_id: customer.id,
        });
    }

    function onQueryKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (!suggestOpen || suggestions.length === 0) {
            if (event.key === 'Escape') {
                setSuggestOpen(false);
            }

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) =>
                index < suggestions.length - 1 ? index + 1 : 0,
            );

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) =>
                index > 0 ? index - 1 : suggestions.length - 1,
            );

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            prefillFromCustomer(suggestions[activeIndex]);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setSuggestOpen(false);
        }
    }

    return (
        <div className="relative flex h-dvh max-h-dvh flex-col overflow-hidden bg-[#f7f9fc] text-slate-900">
            <Head title="Customer Intake Portal" />
            <IntakePortalChrome />

            <main className="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(226,232,240,0.55),transparent_55%),linear-gradient(180deg,#ffffff_0%,#f7f9fc_100%)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-y-0 right-0 hidden w-[46%] lg:block"
                >
                    <img
                        src="/intake-hero.jpg"
                        alt=""
                        className="absolute inset-0 size-full object-cover object-[72%_center]"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-[#f7f9fc] via-[#f7f9fc]/80 to-transparent" />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#f7f9fc] via-transparent to-[#f7f9fc]/35" />
                </div>
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 opacity-[0.28]"
                    style={{
                        backgroundImage:
                            "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg stroke='%23cbd5e1' stroke-width='0.6'%3E%3Cpath d='M0 30c10-8 20-8 30 0s20 8 30 0'/%3E%3Cpath d='M0 45c10-8 20-8 30 0s20 8 30 0'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")",
                    }}
                />

                <div className="relative z-10 mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col justify-center px-5 py-4 sm:px-8 sm:py-6 lg:px-10">
                    <div className="max-w-2xl">
                        <div className="flex items-center gap-3">
                            <span className="h-px w-8 bg-nppc-900/70" />
                            <p className="text-[11px] font-semibold tracking-[0.22em] text-slate-500 uppercase">
                                Customer Intake
                            </p>
                        </div>
                        <h1 className="mt-3 font-heading text-3xl font-bold tracking-tight text-nppc-900 sm:text-4xl lg:text-[2.75rem] lg:leading-[1.12]">
                            Start Your Request for Analysis
                        </h1>
                        <p className="mt-3 max-w-xl text-sm leading-relaxed text-slate-600 sm:text-base">
                            Submit your request online, then bring labeled
                            samples to Receiving. Typical turnaround is 5–7
                            working days.
                        </p>
                    </div>

                    {mode === 'choose' ? (
                        <div className="mt-6 grid gap-4 sm:mt-7 sm:grid-cols-2 sm:gap-5">
                            <article className="flex flex-col rounded-2xl border border-slate-200/90 bg-white/95 p-5 shadow-sm backdrop-blur-sm sm:p-6">
                                <div className="flex size-11 items-center justify-center rounded-xl bg-nppc-50 text-nppc-900">
                                    <FilePlus2 className="size-5" />
                                </div>
                                <h2 className="mt-4 font-heading text-lg font-bold text-nppc-900 sm:text-xl">
                                    New Customer
                                </h2>
                                <p className="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">
                                    First visit? Enter your details, samples,
                                    and tests.
                                </p>
                                <Button
                                    asChild
                                    className="mt-5 h-11 gap-2 bg-nppc-900 px-5 text-sm hover:bg-nppc-700 sm:text-base"
                                >
                                    <Link href="/intake/create">
                                        Start Request
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </article>

                            <article className="flex flex-col rounded-2xl border border-slate-200/90 bg-white/95 p-5 shadow-sm backdrop-blur-sm sm:p-6">
                                <div className="flex size-11 items-center justify-center rounded-xl bg-slate-50 text-nppc-900">
                                    <History className="size-5" />
                                </div>
                                <h2 className="mt-4 font-heading text-lg font-bold text-nppc-900 sm:text-xl">
                                    Returning Customer
                                </h2>
                                <p className="mt-1.5 flex-1 text-sm leading-relaxed text-slate-600">
                                    Look up a past visit to reuse your customer
                                    details.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-5 h-11 gap-2 border-nppc-900/30 bg-white px-5 text-sm text-nppc-900 hover:bg-slate-50 sm:text-base"
                                    onClick={() => setMode('returning')}
                                >
                                    Find My Details
                                    <ArrowRight className="size-4" />
                                </Button>
                            </article>
                        </div>
                    ) : (
                        <div className="mt-6 max-w-xl rounded-2xl border border-slate-200/90 bg-white/95 p-5 shadow-sm backdrop-blur-sm sm:mt-7 sm:p-6">
                            <button
                                type="button"
                                onClick={() => {
                                    setMode('choose');
                                    setQuery('');
                                    setSelectedId(null);
                                    setSuggestions([]);
                                    setSuggestOpen(false);
                                }}
                                className="text-sm font-medium text-nppc-700 hover:text-nppc-900"
                            >
                                ← Back
                            </button>
                            <h2 className="mt-3 font-heading text-xl font-bold text-nppc-900">
                                Returning Customer
                            </h2>
                            <p className="mt-1.5 text-sm text-slate-600">
                                Enter any detail from a previous visit. Pick a
                                suggestion when several people match.
                            </p>
                            <Form
                                action="/intake/lookup"
                                method="post"
                                className="mt-5 space-y-3"
                            >
                                {({ processing }) => (
                                    <>
                                        {selectedId ? (
                                            <input
                                                type="hidden"
                                                name="customer_id"
                                                value={selectedId}
                                            />
                                        ) : null}
                                        <div className="relative space-y-2">
                                            <Label htmlFor="query">
                                                Email, contact, or name
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="query"
                                                    name="query"
                                                    required
                                                    autoFocus
                                                    autoComplete="off"
                                                    role="combobox"
                                                    aria-expanded={suggestOpen}
                                                    aria-controls={
                                                        suggestListId
                                                    }
                                                    aria-autocomplete="list"
                                                    value={query}
                                                    onChange={(event) => {
                                                        setQuery(
                                                            event.target.value,
                                                        );
                                                        setSelectedId(null);
                                                    }}
                                                    onFocus={() => {
                                                        if (
                                                            suggestions.length >
                                                                0 ||
                                                            query.trim()
                                                                .length >= 2
                                                        ) {
                                                            setSuggestOpen(
                                                                true,
                                                            );
                                                        }
                                                    }}
                                                    onKeyDown={onQueryKeyDown}
                                                    onBlur={() => {
                                                        window.setTimeout(
                                                            () =>
                                                                setSuggestOpen(
                                                                    false,
                                                                ),
                                                            150,
                                                        );
                                                    }}
                                                    className="h-11 pr-9 text-base"
                                                    placeholder="e.g. 0917… or name@email.com"
                                                />
                                                {suggestLoading ? (
                                                    <LoaderCircle className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-slate-400" />
                                                ) : null}
                                            </div>
                                            {suggestOpen ? (
                                                <div
                                                    id={suggestListId}
                                                    role="listbox"
                                                    className="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-md"
                                                >
                                                    {suggestions.length ===
                                                    0 ? (
                                                        <p className="px-3 py-2 text-xs text-slate-500">
                                                            {suggestLoading
                                                                ? 'Searching…'
                                                                : 'No matching customers yet.'}
                                                        </p>
                                                    ) : (
                                                        suggestions.map(
                                                            (
                                                                customer,
                                                                index,
                                                            ) => {
                                                                const meta = [
                                                                    customer.customer_contact,
                                                                    customer.customer_email,
                                                                    customer.company_name,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    );

                                                                return (
                                                                    <button
                                                                        key={
                                                                            customer.id
                                                                        }
                                                                        type="button"
                                                                        role="option"
                                                                        aria-selected={
                                                                            index ===
                                                                            activeIndex
                                                                        }
                                                                        className={cn(
                                                                            'flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition',
                                                                            index ===
                                                                                activeIndex
                                                                                ? 'bg-nppc-50'
                                                                                : 'hover:bg-slate-50',
                                                                        )}
                                                                        onMouseDown={(
                                                                            event,
                                                                        ) =>
                                                                            event.preventDefault()
                                                                        }
                                                                        onMouseEnter={() =>
                                                                            setActiveIndex(
                                                                                index,
                                                                            )
                                                                        }
                                                                        onClick={() =>
                                                                            prefillFromCustomer(
                                                                                customer,
                                                                            )
                                                                        }
                                                                    >
                                                                        <span className="text-sm font-semibold text-nppc-900">
                                                                            {
                                                                                customer.customer_name
                                                                            }
                                                                        </span>
                                                                        {meta ? (
                                                                            <span className="text-[11px] text-slate-500">
                                                                                {
                                                                                    meta
                                                                                }
                                                                            </span>
                                                                        ) : null}
                                                                    </button>
                                                                );
                                                            },
                                                        )
                                                    )}
                                                </div>
                                            ) : null}
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

                    <div className="mt-6 flex flex-col items-stretch gap-3 border-t border-slate-200/80 pt-5 sm:mt-7 sm:flex-row sm:items-center sm:justify-center sm:gap-0 sm:divide-x sm:divide-slate-200 sm:border-0 sm:pt-6">
                        {[
                            {
                                icon: Award,
                                label: 'ISO/IEC 17025 Accredited',
                            },
                            {
                                icon: Clock,
                                label: 'Turnaround Time 5–7 Business Days',
                            },
                            {
                                icon: FlaskConical,
                                label: 'Reliable & Defensible Analytical Results',
                            },
                        ].map((item) => (
                            <div
                                key={item.label}
                                className="flex items-center gap-2 px-0 text-xs text-slate-600 sm:px-5 sm:text-sm lg:px-7"
                            >
                                <item.icon
                                    className="text-nppc-800 size-4 shrink-0 sm:size-5"
                                    aria-hidden
                                />
                                <span className="font-medium">
                                    {item.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </main>

            <footer className="relative z-10 shrink-0 border-t border-slate-200 bg-white">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-1.5 px-5 py-2.5 text-[10px] text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:text-[11px] lg:px-10">
                    <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <span className="font-medium text-slate-700">
                            NPPC Laboratory
                        </span>
                        <span className="hidden text-slate-300 sm:inline">
                            |
                        </span>
                        <span>
                            ISO/IEC 17025 · DENR &amp; DOH Recognized · RA 10173
                            Data Privacy
                        </span>
                    </div>
                    <p className="sm:text-right">
                        Powered by FNDS · All rights reserved 2026
                    </p>
                </div>
            </footer>
        </div>
    );
}

IntakeIndex.layout = null;
