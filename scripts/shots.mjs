import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const sessionUuid = process.env.SESSION_UUID ?? '';
const outDir = '.impeccable/review';
await mkdir(outDir, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome' });
const problems = [];

async function shoot(name, path, width, height = 1000, fullPage = true) {
    const context = await browser.newContext({ viewport: { width, height } });
    const page = await context.newPage();

    page.on('console', (message) => {
        if (message.type() === 'error') {
            problems.push(`[${name}] console: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) =>
        problems.push(`[${name}] pageerror: ${error.message}`),
    );

    const response = await page.goto(`${base}${path}`, {
        waitUntil: 'networkidle',
        timeout: 45000,
    });
    await page.waitForTimeout(900);
    await page.screenshot({ path: `${outDir}/${name}.png`, fullPage });
    console.log(`${name}: ${response?.status()} -> ${outDir}/${name}.png`);
    await context.close();
}

await shoot('desktop', '/', 1440, 1000);
await shoot('mobile', '/', 390, 844);
await shoot('assistant', '/assistant', 1440, 1000);
await shoot('catalog', '/catalog', 1440, 1000);
await shoot('audit-empty', '/audit', 1440, 900);
await shoot(
    'session-mobile',
    sessionUuid ? `/sessions/${sessionUuid}` : '/assistant',
    390,
    844,
);

if (sessionUuid) {
    await shoot('session', `/sessions/${sessionUuid}`, 1440, 1000);
}

await browser.close();

if (problems.length > 0) {
    console.log('\nPROBLEMS');
    problems.forEach((problem) => console.log(problem));
    process.exitCode = 1;
}
