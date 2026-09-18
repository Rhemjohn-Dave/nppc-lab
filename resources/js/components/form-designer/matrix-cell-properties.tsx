import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DesignerField } from '@/lib/controlled-forms';
import {
    findColumn,
    matrixConfig,
    patchCellText,
    patchSelectedColumn,
    readCellText,
    selectionLabel,
    columnWidthPctSum,
    type MatrixCellSelection,
    type MatrixTableConfig,
} from '@/lib/dynamic-test-matrix';

type Props = {
    field: DesignerField;
    selection: MatrixCellSelection;
    canEdit: boolean;
    onUpdate: (patch: Partial<DesignerField>, recordHistory?: boolean) => void;
    onClearSelection: () => void;
    packagePreviewRows?: Array<Record<string, string>>;
};

export default function MatrixCellProperties({
    field,
    selection,
    canEdit,
    onUpdate,
    onClearSelection,
    packagePreviewRows,
}: Props) {
    const config = matrixConfig(field);
    const column = findColumn(config, selection.columnKey);
    const text = readCellText(config, config.columns ?? [], selection, packagePreviewRows);
    const isHeader = selection.area === 'header';
    const isPreviewBody = selection.area === 'body';
    const widthPctSum = columnWidthPctSum(config.columns ?? []);
    const widthPctNeedsNormalize = widthPctSum > 0 && Math.abs(widthPctSum - 100) >= 0.05;

    function updateConfig(next: MatrixTableConfig, recordHistory = true) {
        onUpdate({ table_config: next }, recordHistory);
    }

    function updateText(value: string) {
        updateConfig(patchCellText(config, selection, value, packagePreviewRows));
    }

    function updateColumn(patch: Parameters<typeof patchSelectedColumn>[2]) {
        updateConfig(patchSelectedColumn(config, selection, patch));
    }

    const fontSize = isHeader ? column?.header_font_size : column?.font_size;
    const isHeaderSublabel = isHeader && selection.part === 'sublabel';
    const align = isHeaderSublabel
        ? (column?.sublabel_align ?? column?.header_align ?? 'C')
        : isHeader
          ? (column?.header_align ?? 'C')
          : (column?.align ?? (selection.columnKey === 'test' ? 'L' : 'C'));

    return (
        <section className="space-y-3 rounded-md border border-[#1A3694]/25 bg-[#1A3694]/5 p-3">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-[10px] font-semibold tracking-wider text-[#1A3694] uppercase">
                        Selected cell
                    </p>
                    <p className="text-xs font-medium text-slate-800">{selectionLabel(selection)}</p>
                </div>
                <button
                    type="button"
                    className="text-[10px] text-[#1A3694] underline"
                    onClick={onClearSelection}
                >
                    Clear
                </button>
            </div>

            {isPreviewBody && (
                <p className="text-[10px] leading-snug text-amber-900">
                    Body preview text is for layout only. Printed results come from the job
                    order at runtime.
                </p>
            )}

            {isHeader && (
                <p className="text-[10px] leading-snug text-muted-foreground">
                    {isHeaderSublabel
                        ? 'Sublabel alignment is separate from the column title (e.g. Sample Description / SS).'
                        : 'Header text is printed on every report using this form.'}
                </p>
            )}

            <div className="space-y-2">
                <Label htmlFor="matrix-cell-text">Text</Label>
                <Input
                    id="matrix-cell-text"
                    disabled={!canEdit}
                    value={text}
                    onChange={(event) => updateText(event.target.value)}
                    onBlur={() => onUpdate({}, true)}
                />
            </div>

            <div className="grid grid-cols-2 gap-2">
                <div>
                    <Label htmlFor="matrix-cell-font">Font size (this column)</Label>
                    <Input
                        id="matrix-cell-font"
                        type="number"
                        step="0.5"
                        min={6}
                        max={14}
                        disabled={!canEdit}
                        value={fontSize ?? ''}
                        placeholder="Default"
                        onChange={(event) =>
                            updateColumn(
                                isHeader
                                    ? {
                                          header_font_size: event.target.value
                                              ? Number(event.target.value)
                                              : undefined,
                                      }
                                    : {
                                          font_size: event.target.value
                                              ? Number(event.target.value)
                                              : undefined,
                                      },
                            )
                        }
                        onBlur={() => onUpdate({}, true)}
                    />
                </div>
                <div>
                    <Label htmlFor="matrix-cell-align">Alignment</Label>
                    <select
                        id="matrix-cell-align"
                        className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                        disabled={!canEdit}
                        value={align}
                        onChange={(event) => {
                            const next = event.target.value as 'L' | 'C' | 'R';
                            if (isHeaderSublabel) {
                                updateColumn({ sublabel_align: next });
                                return;
                            }
                            if (isHeader) {
                                updateColumn({ header_align: next });
                                return;
                            }
                            updateColumn({ align: next });
                        }}
                    >
                        <option value="L">Left</option>
                        <option value="C">Center</option>
                        <option value="R">Right</option>
                    </select>
                </div>
            </div>

            {!isHeader && selection.columnKey === 'test' && selection.part === 'test' && (
                <label className="flex items-center gap-2 text-xs">
                    <Checkbox
                        checked={config.test_name_bold !== false}
                        disabled={!canEdit}
                        onCheckedChange={(checked) =>
                            updateConfig({ ...config, test_name_bold: checked === true })
                        }
                    />
                    Bold test names (whole TEST column)
                </label>
            )}

            {isHeader && selection.part === 'label' && (
                <label className="flex items-center gap-2 text-xs">
                    <Checkbox
                        checked={config.header_bold !== false}
                        disabled={!canEdit}
                        onCheckedChange={(checked) =>
                            updateConfig({ ...config, header_bold: checked === true })
                        }
                    />
                    Bold header labels (whole header row)
                </label>
            )}

            {selection.area === 'header' && (
                <div>
                    <Label htmlFor="matrix-cell-width">Column width %</Label>
                    <Input
                        id="matrix-cell-width"
                        type="number"
                        min={5}
                        max={90}
                        disabled={!canEdit}
                        value={column?.width_pct ?? ''}
                        placeholder="Auto"
                        onChange={(event) =>
                            updateColumn({
                                width_pct: event.target.value ? Number(event.target.value) : undefined,
                            })
                        }
                        onBlur={() => onUpdate({}, true)}
                    />
                    {widthPctNeedsNormalize && (
                        <p className="mt-1 text-[11px] text-amber-700">
                            Column widths sum to {Math.round(widthPctSum)}%. Preview and PDF normalize
                            to 100% so the table still fills the field box.
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}
