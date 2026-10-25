import type { ReactNode } from 'react';

export default function RecordHeader({
    kicker,
    id,
    title,
    description,
    meta = [],
    actions,
}: {
    kicker: string;
    id: string;
    title: ReactNode;
    description?: ReactNode;
    meta?: Array<{ label: string; value: ReactNode }>;
    actions?: ReactNode;
}) {
    return (
        <div className="mt-8 border-t-2 border-ink pt-3 sm:mt-10">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-[10px] tracking-[0.18em] uppercase">
                <span className="text-muted">{kicker}</span>
                <span className="font-mono text-[10px] tracking-tight text-muted normal-case">
                    {id}
                </span>
            </div>
            <div className="mt-3 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                <h1 className="max-w-[42ch] text-[clamp(1.9rem,4.2vw,3.1rem)] leading-[1.01] font-semibold">
                    {title}
                </h1>
                {actions && (
                    <div className="flex flex-wrap gap-3">{actions}</div>
                )}
            </div>
            {description && (
                <div className="mt-3 max-w-[70ch] text-[14px] leading-relaxed text-ink-soft">
                    {description}
                </div>
            )}
            {meta.length > 0 && (
                <dl className="mt-5 flex flex-wrap gap-x-10 gap-y-3 border-t border-rule pt-3">
                    {meta.map((item) => (
                        <div key={item.label}>
                            <dt className="text-[9px] tracking-[0.16em] text-muted uppercase">
                                {item.label}
                            </dt>
                            <dd className="mt-0.5 text-[12px] text-ink">
                                {item.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </div>
    );
}
