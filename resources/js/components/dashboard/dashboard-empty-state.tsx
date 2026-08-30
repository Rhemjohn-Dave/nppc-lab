type Props = {
    title?: string;
    body: string;
};

export default function DashboardEmptyState({
    title = 'Nothing here',
    body,
}: Props) {
    return (
        <div className="rounded-xl border bg-white px-4 py-10 text-center">
            <p className="font-medium text-[#1A3694]">{title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{body}</p>
        </div>
    );
}
