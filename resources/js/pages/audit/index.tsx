import { Head, Link } from '@inertiajs/react';
import { ArrowRight, FileText } from 'lucide-react';
import RecordHeader from '@/components/trust/record-header';
import StatusStamp from '@/components/trust/status-stamp';
import { formatDateTime, shortId } from '@/lib/format';

type SessionRow = {
    uuid: string;
    intent: string;
    created_at: string;
    verdict: string | null;
    product: string | null;
    score: number | null;
    approval_status: string | null;
    order_status: string | null;
};

export default function AuditIndex({ sessions }: { sessions: SessionRow[] }) {
    return (
        <>
            <Head title="Audit trail" />

            <RecordHeader
                kicker="Audit trail"
                id={`${sessions.length} records · this browser`}
                title="The record is the product."
                description="Each decision file keeps the request, the parsed constraints, every rule outcome, the model narrative, the approved amount and the PayPal status. Records are scoped to this browser session; no account is required for the demo."
                meta={[
                    { label: 'Storage', value: 'SQLite · server-side' },
                    { label: 'Secrets', value: 'None stored client-side' },
                    { label: 'Export', value: 'Printable / JSON trace' },
                ]}
            />

            {sessions.length === 0 ? (
                <div className="mt-8 border border-rule">
                    <div className="record-head">
                        <span className="flex items-center gap-2 text-[10px] tracking-[0.16em] uppercase">
                            <FileText
                                className="h-3.5 w-3.5"
                                strokeWidth={1.6}
                            />
                            No records yet
                        </span>
                        <span className="mono-id">empty</span>
                    </div>
                    <div className="px-5 py-10 sm:px-6">
                        <p className="max-w-[54ch] text-[15px] leading-relaxed text-ink-soft">
                            Run a decision first. The audit trail will then show
                            the request, the evidence the rules saw, and every
                            payment event that followed.
                        </p>
                        <Link href="/assistant" className="btn-primary mt-6">
                            Run a decision
                            <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                        </Link>
                    </div>
                </div>
            ) : (
                <div className="mt-8 overflow-x-auto border border-rule">
                    <table className="table-record">
                        <thead>
                            <tr>
                                <th className="pl-4 sm:pl-6">Record</th>
                                <th>Request</th>
                                <th>Verdict</th>
                                <th>Approval</th>
                                <th>Payment</th>
                                <th className="pr-4 text-right sm:pr-6"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.map((session) => (
                                <tr key={session.uuid}>
                                    <td className="pl-4 sm:pl-6">
                                        <Link
                                            href={`/audit/${session.uuid}`}
                                            className="font-mono text-[11px] underline decoration-1 underline-offset-4 hover:text-signal-deep"
                                        >
                                            DR-{shortId(session.uuid)}
                                        </Link>
                                        <span className="mt-0.5 block text-[10px] text-muted">
                                            {formatDateTime(session.created_at)}
                                        </span>
                                    </td>
                                    <td className="max-w-[26rem]">
                                        <span className="line-clamp-2 text-[12px] text-ink-soft">
                                            {session.intent}
                                        </span>
                                    </td>
                                    <td>
                                        <StatusStamp
                                            kind={session.verdict ?? 'pending'}
                                        />
                                        {session.product && (
                                            <span className="mt-1 block text-[10px] text-muted">
                                                {session.product}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {session.approval_status ? (
                                            <StatusStamp
                                                kind={session.approval_status}
                                            />
                                        ) : (
                                            <span className="text-[10px] text-muted">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {session.order_status ? (
                                            <StatusStamp
                                                kind={session.order_status}
                                            />
                                        ) : (
                                            <span className="text-[10px] text-muted">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="pr-4 text-right sm:pr-6">
                                        <Link
                                            href={`/audit/${session.uuid}`}
                                            className="inline-flex items-center gap-1 text-[11px] text-ink-soft hover:text-ink"
                                        >
                                            Open
                                            <ArrowRight
                                                className="h-3 w-3"
                                                strokeWidth={1.6}
                                            />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <p className="mt-4 text-[10px] leading-relaxed text-muted">
                Audit rows are append-only application records. This is a
                prototype: it does not claim tamper-evident storage or
                regulatory retention.
            </p>
        </>
    );
}
