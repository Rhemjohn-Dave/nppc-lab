import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type Props = {
    summary: {
        draft_submitted: number;
        in_analysis: number;
        awaiting_signature: number;
        ready_for_pickup: number;
        total: number;
    };
    chartData: Array<{ status: string; count: number }>;
    recentOrders: Array<{
        id: number;
        reference_no: string;
        customer_name: string;
        status: string;
        status_label: string;
        total_cost: string | number;
        created_at: string | null;
    }>;
    roles: string[];
};

export default function Dashboard({
    summary,
    chartData,
    recentOrders,
    roles,
}: Props) {
    const cards = [
        ['Awaiting receive', summary.draft_submitted],
        ['In analysis', summary.in_analysis],
        ['Awaiting signature', summary.awaiting_signature],
        ['Ready for pickup', summary.ready_for_pickup],
    ] as const;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Laboratory dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Roles: {roles.join(', ') || 'staff'} · {summary.total}{' '}
                        total job orders
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {label}
                            </p>
                            <p className="mt-2 font-heading text-3xl font-semibold text-[#1A3694]">
                                {value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-4 font-medium">Orders by status</h2>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="status" hide />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="#1A3694" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <h2 className="mb-4 font-medium">Recent job orders</h2>
                        <div className="space-y-2">
                            {recentOrders.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {order.reference_no}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {order.customer_name} ·{' '}
                                            {order.status_label}
                                        </p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {order.created_at}
                                    </p>
                                </div>
                            ))}
                            {recentOrders.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No job orders yet. Start at{' '}
                                    <Link
                                        href="/intake"
                                        className="text-[#1A3694] underline"
                                    >
                                        /intake
                                    </Link>
                                    .
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
