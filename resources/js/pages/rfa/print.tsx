import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import RequestForAnalysisForm from '@/components/request-for-analysis-form';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    jobOrder: RequestForAnalysisData;
    copies?: number;
    showResults?: boolean;
};

export default function RfaPrint({
    jobOrder,
    copies = 1,
    showResults = false,
}: Props) {
    const [copyCount, setCopyCount] = useState(copies);

    return (
        <>
            <Head title={`Print ${jobOrder.reference_no}`} />
            <div className="min-h-screen space-y-8 bg-slate-100 p-4 print:bg-white print:p-0">
                <div className="mx-auto flex max-w-[8.5in] flex-wrap items-center justify-between gap-2 print:hidden">
                    <div>
                        <p className="text-sm font-medium text-[#1A3694]">
                            Print preview — {jobOrder.reference_no}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {showResults
                                ? 'Official form with analyst results'
                                : 'Official Request for Analysis form'}
                            {copies > 1 ? ` · ${copies} copies` : ''}
                            {' · Long bond 8.5×13 · 1″ margins'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="flex items-center gap-2 text-sm">
                            Copies
                            <Input
                                type="number"
                                min={1}
                                max={20}
                                className="w-20"
                                value={copyCount}
                                onChange={(event) =>
                                    setCopyCount(
                                        Math.min(
                                            20,
                                            Math.max(
                                                1,
                                                Number(event.target.value) || 1,
                                            ),
                                        ),
                                    )
                                }
                            />
                        </label>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    window.location.pathname,
                                    { copies: copyCount },
                                    { preserveState: false },
                                )
                            }
                        >
                            Apply copies
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            Back
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={() => window.print()}
                        >
                            Print
                        </Button>
                    </div>
                </div>

                {Array.from({ length: copies }).map((_, index) => (
                    <div
                        key={index}
                        className="mx-auto max-w-[8.5in] rounded-xl bg-white p-6 shadow print:max-w-none print:break-after-page print:rounded-none print:p-0 print:shadow-none"
                    >
                        {copies > 1 && (
                            <p className="mb-2 text-xs text-slate-500 print:hidden">
                                Copy {index + 1} of {copies}
                            </p>
                        )}
                        <RequestForAnalysisForm
                            jobOrder={jobOrder}
                            showResults={showResults}
                            showPrintButton={false}
                        />
                    </div>
                ))}
            </div>
        </>
    );
}

RfaPrint.layout = null;
