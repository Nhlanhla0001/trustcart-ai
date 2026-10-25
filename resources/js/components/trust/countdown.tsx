import { useEffect, useState } from 'react';

function remaining(expiresAt: string): number {
    return Math.max(
        0,
        Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000),
    );
}

export default function Countdown({ expiresAt }: { expiresAt: string }) {
    const [seconds, setSeconds] = useState(() => remaining(expiresAt));

    useEffect(() => {
        const tick = () => setSeconds(remaining(expiresAt));
        tick();
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, [expiresAt]);

    if (seconds <= 0) {
        return (
            <span className="text-[11px] font-semibold text-fail">Expired</span>
        );
    }

    const minutes = Math.floor(seconds / 60);
    const rest = String(seconds % 60).padStart(2, '0');

    return (
        <span className="font-mono text-[12px] text-ink tabular-nums">
            {minutes}:{rest}
        </span>
    );
}
