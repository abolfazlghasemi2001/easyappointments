#!/usr/bin/env node
/**
 * Development helper: serves the project over HTTP using php-wasm.
 *
 * Only meant for environments where a native PHP server (or Docker) is not available.
 *
 * Requirements (installed by dev/sandbox/setup.sh):
 *   npm install --no-save @php-wasm/node@3.1.56 @php-wasm/node-8-4@3.1.56
 *
 * Usage: node dev/sandbox/server.mjs [port]     (default port: 8080)
 */
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import http from 'node:http';

const REPO = process.env.EA_ROOT || process.cwd();
const PORT = Number(process.argv[2] || process.env.PORT || 8080);
const HOST = process.env.HOST || '0.0.0.0';

const runtimeId = await loadNodeRuntime('8.4', {
    emscriptenOptions: { processId: Math.floor(Math.random() * 100000) + 1 },
});

const php = new PHP(runtimeId);

await php.mount(REPO, createNodeFsMountHandler(REPO));

const handler = new PHPRequestHandler({
    php,
    documentRoot: REPO,
    absoluteUrl: `http://localhost:${PORT}`,
});

const server = http.createServer(async (request, response) => {
    const chunks = [];

    for await (const chunk of request) {
        chunks.push(chunk);
    }

    try {
        const result = await handler.request({
            url: request.url,
            method: request.method,
            headers: { ...request.headers, host: request.headers.host || `localhost:${PORT}` },
            body: Buffer.concat(chunks),
        });

        const headers = {};

        for (const [name, values] of Object.entries(result.headers || {})) {
            const lower = name.toLowerCase();

            // The development preview renders the app inside an iframe, so drop the clickjacking protection that
            // would otherwise block it.
            if (lower === 'x-frame-options') {
                continue;
            }

            headers[name] = Array.isArray(values) ? values.join(', ') : values;
        }

        response.writeHead(result.httpStatusCode || 200, headers);
        response.end(Buffer.from(await result.bytes));
    } catch (error) {
        response.writeHead(500, { 'content-type': 'text/plain' });
        response.end(String(error?.stack || error));
    }
});

server.listen(PORT, HOST, () => {
    console.log(`Easy!Appointments (php-wasm) listening on http://${HOST}:${PORT}`);
});
