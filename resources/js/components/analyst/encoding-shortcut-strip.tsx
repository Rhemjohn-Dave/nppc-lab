export default function EncodingShortcutStrip() {
    const items = [
        { keys: 'Tab', label: 'Next' },
        { keys: 'P / F', label: 'Pass / Fail' },
        { keys: 'Ctrl+S', label: 'Draft' },
        { keys: 'Ctrl+Enter', label: 'Complete' },
        { keys: 'Alt+←/→', label: 'JO' },
        { keys: 'Alt+1..9', label: 'Test' },
        { keys: 'Alt+F', label: 'Focus' },
    ];

    return (
        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 border-t border-slate-100 bg-slate-50/80 px-3 py-1 text-[10px] text-slate-500 sm:px-4">
            <span className="font-semibold text-slate-600">Shortcuts</span>
            {items.map((item) => (
                <span key={item.keys} className="inline-flex items-center gap-1">
                    <kbd className="rounded border border-slate-300 bg-white px-1 py-px font-mono text-[9px] text-slate-700">
                        {item.keys}
                    </kbd>
                    <span>{item.label}</span>
                </span>
            ))}
        </div>
    );
}
