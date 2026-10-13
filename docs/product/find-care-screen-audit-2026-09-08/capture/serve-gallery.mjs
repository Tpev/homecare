import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {output, galleryBase} from './environment.mjs';

const types = {'.html': 'text/html; charset=utf-8', '.md': 'text/plain; charset=utf-8', '.json': 'application/json', '.png': 'image/png', '.txt': 'text/plain; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript'};
http.createServer((request, response) => {
  let pathname;
  try { pathname = decodeURIComponent(new URL(request.url, galleryBase).pathname); } catch { response.writeHead(400); response.end('Bad path'); return; }
  const target = path.resolve(output, '.' + (pathname === '/' ? '/index.html' : pathname));
  if (target !== output && !target.startsWith(output + path.sep)) { response.writeHead(403); response.end('Forbidden'); return; }
  if (!fs.existsSync(target) || !fs.statSync(target).isFile()) { response.writeHead(404, {'Content-Type': 'text/plain'}); response.end('The post-implementation gallery is being prepared.'); return; }
  response.writeHead(200, {'Content-Type': types[path.extname(target)] || 'application/octet-stream', 'Cache-Control': 'no-store'});
  fs.createReadStream(target).pipe(response);
}).listen(Number(new URL(galleryBase).port), '127.0.0.1', () => console.log('Gallery: ' + galleryBase));
