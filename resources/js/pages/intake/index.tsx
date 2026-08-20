import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, RotateCcw, UserPlus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import NppcLogo from '@/components/nppc-logo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function IntakeIndex() {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [mode, setMode] = useState<'choose' | 'returning'>(() =>
        errors?.query ? 'returning' : 'choose',
    );

    return (
        <div className="relative min-h-screen overflow-hidden bg-slate-50 text-slate-900">
            <Head title="Customer Intake" />

            <div className="grid min-h-screen lg:grid-cols-2">
                <aside className="relative hidden min-h-[42vh] overflow-hidden lg:block">
                    <img
                        src="/nppc.jpg"
                        alt="NPPC laboratory analysts at work"
                        className="absolute inset-0 size-full object-cover object-[center_30%]"
                    />
                    <div className="absolute inset-0 bg-[linear-gradient(to_top,rgba(10,28,84,0.55)_0%,rgba(10,28,84,0.12)_42%,rgba(10,28,84,0.08)_100%)]" />
                    <div className="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-slate-50 to-transparent" />

                    <div className="absolute inset-x-0 bottom-0 p-8 text-white">
                        <p className="font-heading text-3xl font-semibold tracking-tight">
                            Advancing science,
                            <span className="mt-1 block font-normal text-white/85">
                                supporting industries.
                            </span>
                        </p>
                        <p className="mt-4 max-w-md text-sm leading-relaxed text-white/75">
                            Trusted laboratory testing for agriculture,
                            aquaculture, and industrial partners across Negros.
                        </p>
                    </div>
                </aside>

                <main className="relative flex flex-col justify-between px-6 py-10 sm:px-10 sm:py-14 lg:px-14">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 lg:hidden"
                    >
                        <img
                            src="/nppc.jpg"
                            alt=""
                            className="absolute inset-0 size-full object-cover opacity-25"
                        />
                        <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(247,249,253,0.92)_0%,rgba(247,249,253,0.97)_55%,#f7f9fd_100%)]" />
                    </div>

                    <div className="intake-enter relative">
                        <div className="flex items-center gap-4">
                            <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-md ring-4 ring-[#1A3694]/10 sm:size-20">
                                <NppcLogo className="h-[90%] w-[90%]" />
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-[0.22em] text-[#365BB0] uppercase">
                                    Analytical & Diagnostic Laboratory
                                </p>
                                <h1 className="font-heading text-4xl font-semibold tracking-tight text-[#1A3694] sm:text-5xl">
                                    NPPC
                                </h1>
                            </div>
                        </div>

                        <p className="mt-6 max-w-lg text-lg leading-relaxed text-slate-600">
                            Welcome. Submit your Request for Analysis here, then
                            proceed to Receiving with your samples.
                        </p>
                    </div>

                    <div className="intake-enter-delay relative mt-10">
                        {mode === 'choose' ? (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Link
                                    href="/intake/create"
                                    className="group rounded-2xl border border-[#1A3694]/10 bg-white p-6 shadow-[0_12px_40px_rgba(26,54,148,0.08)] transition duration-300 hover:-translate-y-1 hover:border-[#1A3694]/25 hover:shadow-[0_18px_48px_rgba(26,54,148,0.14)]"
                                >
                                    <div className="flex size-11 items-center justify-center rounded-full bg-[#1A3694] text-white transition duration-300 group-hover:scale-105">
                                        <UserPlus className="size-5" />
                                    </div>
                                    <h2 className="mt-5 font-heading text-2xl font-semibold text-[#1A3694]">
                                        New customer
                                    </h2>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                                        First visit? Start a guided request with
                                        your details and tests.
                                    </p>
                                    <p className="mt-2 text-xs text-slate-500">
                                        Best for first-time clients. Usually
                                        takes about 3-5 minutes.
                                    </p>
                                    <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#1A3694]">
                                        Start request
                                        <ArrowRight className="size-4 transition duration-300 group-hover:translate-x-1" />
                                    </span>
                                </Link>

                                <button
                                    type="button"
                                    onClick={() => setMode('returning')}
                                    className="group rounded-2xl border border-[#1A3694]/10 bg-white p-6 text-left shadow-[0_12px_40px_rgba(26,54,148,0.08)] transition duration-300 hover:-translate-y-1 hover:border-[#1A3694]/25 hover:shadow-[0_18px_48px_rgba(26,54,148,0.14)]"
                                >
                                    <div className="flex size-11 items-center justify-center rounded-full bg-[#365BB0] text-white transition duration-300 group-hover:scale-105">
                                        <RotateCcw className="size-5" />
                                    </div>
                                    <h2 className="mt-5 font-heading text-2xl font-semibold text-[#1A3694]">
                                        Returning customer
                                    </h2>
                                    <p className="mt-2 text-sm leading-relaxed text-slate-600">
                                        Look up your email, contact number, or
                                        name from a past visit.
                                    </p>
                                    <p className="mt-2 text-xs text-slate-500">
                                        Use this if you have submitted before and
                                        want to reuse saved customer details.
                                    </p>
                                    <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#1A3694]">
                                        Find my details
                                        <ArrowRight className="size-4 transition duration-300 group-hover:translate-x-1" />
                                    </span>
                                </button>
                            </div>
                        ) : (
                            <div className="max-w-md rounded-2xl border border-[#1A3694]/10 bg-white p-6 shadow-[0_12px_40px_rgba(26,54,148,0.08)] sm:p-8">
                                <button
                                    type="button"
                                    onClick={() => setMode('choose')}
                                    className="text-sm font-medium text-[#365BB0] hover:text-[#1A3694]"
                                >
                                    ← Back to options
                                </button>
                                <h2 className="mt-4 font-heading text-3xl font-semibold text-[#1A3694]">
                                    Returning customer
                                </h2>
                                <p className="mt-2 text-slate-600">
                                    Enter any detail from a previous visit.
                                </p>
                                <Form
                                    action="/intake/lookup"
                                    method="post"
                                    className="mt-6 space-y-4"
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
                                                    className="h-12 text-base"
                                                    placeholder="e.g. 0917… or name@email.com"
                                                />
                                                <InputError
                                                    message={errors?.query}
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="h-12 w-full bg-[#1A3694] text-base hover:bg-[#365BB0]"
                                            >
                                                Continue with my details
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}
                    </div>

                    <footer className="intake-enter-late relative mt-12 border-t border-[#1A3694]/10 pt-6 text-sm text-slate-500">
                        <p className="font-medium text-[#365BB0]">
                            After submitting, please proceed to Receiving with
                            your samples.
                        </p>
                        <p className="mt-2">
                            Tel 034-4332131 · 034-4352613 · nppclab@gmail.com
                        </p>
                        <p className="mt-1 text-slate-400">
                            Typical turnaround: 5–7 working days
                        </p>
                    </footer>
                </main>
            </div>
        </div>
    );
}

IntakeIndex.layout = null;
