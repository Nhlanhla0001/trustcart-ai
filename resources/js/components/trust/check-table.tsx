import type { Check } from '@/types/trustcart';
import StatusStamp from './status-stamp';

export default function CheckTable({
    checks,
    compact = false,
}: {
    checks: Check[];
    compact?: boolean;
}) {
    if (checks.length === 0) {
        return (
            <p className="px-4 py-6 text-[13px] text-muted sm:px-6">
                No checks were recorded for this decision.
            </p>
        );
    }

    return (
        <>
            <ul className="sm:hidden">
                {checks.map((check) => (
                    <li
                        key={check.key}
                        className="border-b border-rule px-4 py-3 last:border-b-0"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <span className="text-[13px] font-medium">
                                {check.label}
                            </span>
                            <StatusStamp kind={check.result} />
                        </div>
                        {!compact && (
                            <p className="mt-1 text-[11px] leading-relaxed text-ink-soft">
                                {check.detail}
                            </p>
                        )}
                        <dl className="mt-2 grid grid-cols-2 gap-2 text-[10px]">
                            <div>
                                <dt className="tracking-[0.1em] text-muted uppercase">
                                    Need
                                </dt>
                                <dd className="mt-0.5">{check.requirement}</dd>
                            </div>
                            <div>
                                <dt className="tracking-[0.1em] text-muted uppercase">
                                    Evidence
                                </dt>
                                <dd className="mt-0.5">{check.evidence}</dd>
                            </div>
                        </dl>
                    </li>
                ))}
            </ul>

            <table className="table-record hidden sm:table">
                <thead>
                    <tr>
                        <th className="pl-4 sm:pl-6">Check</th>
                        <th>Requirement</th>
                        <th>Catalog evidence</th>
                        <th className="pr-4 text-right sm:pr-6">Result</th>
                    </tr>
                </thead>
                <tbody>
                    {checks.map((check) => (
                        <tr key={check.key}>
                            <td className="pl-4 sm:pl-6">
                                <span className="font-medium text-ink">
                                    {check.label}
                                </span>
                                {!compact && (
                                    <span className="mt-0.5 block max-w-[30rem] text-[11px] leading-relaxed text-muted">
                                        {check.detail}
                                    </span>
                                )}
                            </td>
                            <td className="text-ink-soft">
                                {check.requirement}
                            </td>
                            <td className="text-ink-soft">{check.evidence}</td>
                            <td className="pr-4 text-right sm:pr-6">
                                <StatusStamp kind={check.result} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}
