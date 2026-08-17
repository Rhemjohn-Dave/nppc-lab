import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock3, Printer } from 'lucide-react';
import NppcLogo from '@/components/nppc-logo';
import { Button } from '@/components/ui/button';

type Props = {
    jobOrder: {
        id: number;
        reference_no: string;
        customer_name: string;
    };
};

export default function IntakeSuccess({ jobOrder }: Props) {
    return (
        <div className="nppc-atmosphere flex min-h-screen items-center justify-center px-6 py-12 text-slate-900">
            <Head title="Request submitted" />
            <div className="intake-enter w-full max-w-lg rounded-3xl bg-white/92 p-8 text-center shadow-sm ring-1 ring-[#1A3694]/12 backdrop-blur">
                <NppcLogo className="mx-auto h-16 w-auto" />
                <CheckCircle2 className="mx-auto mt-5 size-14 text-[#1A3694]" />
                <h1 className="mt-4 font-heading text-3xl font-semibold text-[#1A3694]">
                    Request received
                </h1>
                <p className="mt-3 text-slate-600">
                    Thank you, {jobOrder.customer_name}. Please keep this
                    reference number and proceed to Receiving.
                </p>
                <div className="mt-6 rounded-2xl bg-[#e8eef8] px-4 py-5">
                    <p className="text-xs font-semibold tracking-[0.18em] text-[#365BB0] uppercase">
                        Reference number
                    </p>
                    <p className="mt-2 font-heading text-4xl font-semibold tracking-wide text-[#1A3694]">
                        {jobOrder.reference_no}
                    </p>
                </div>

                <div className="mt-6 grid gap-3 text-left text-sm text-slate-600">
                    <div className="flex gap-3 rounded-xl border border-slate-200 p-3">
                        <Printer className="mt-0.5 size-4 shrink-0 text-[#1A3694]" />
                        <p>
                            Receiving will review pricing and print your Request
                            for Analysis form.
                        </p>
                    </div>
                    <div className="flex gap-3 rounded-xl border border-slate-200 p-3">
                        <Clock3 className="mt-0.5 size-4 shrink-0 text-[#1A3694]" />
                        <p>
                            Typical turnaround is 5–7 working days. We’ll email
                            you when results are ready for pickup.
                        </p>
                    </div>
                </div>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Button
                        asChild
                        className="h-12 bg-[#1A3694] hover:bg-[#365BB0]"
                    >
                        <Link href="/intake">Done</Link>
                    </Button>
                    <Button asChild variant="outline" className="h-12">
                        <Link href="/intake/create">Submit another</Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}

IntakeSuccess.layout = null;
