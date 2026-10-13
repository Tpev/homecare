import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const root=path.resolve('docs/product'),port=8032;
const types={'.html':'text/html; charset=utf-8','.md':'text/plain; charset=utf-8','.json':'application/json; charset=utf-8','.png':'image/png','.txt':'text/plain; charset=utf-8'};
http.createServer((req,res)=>{
 const pathname=decodeURIComponent(new URL(req.url,'http://127.0.0.1').pathname);
 if(pathname==='/'){res.writeHead(302,{Location:'/find-care-recruitment-polish-2026-09-09/'}).end();return;}
 const target=path.resolve(root,'.'+(pathname==='/'?'/find-care-recruitment-polish-2026-09-09/index.html':pathname));
 if(!target.startsWith(root+path.sep)){res.writeHead(403).end();return;}
 const file=fs.existsSync(target)&&fs.statSync(target).isDirectory()?path.join(target,'index.html'):target;
 if(!fs.existsSync(file)){res.writeHead(404).end('Not found');return;}
 res.writeHead(200,{'Content-Type':types[path.extname(file)]||'application/octet-stream','Cache-Control':'no-store'});fs.createReadStream(file).pipe(res);
}).listen(port,'127.0.0.1',()=>console.log('Polish gallery http://127.0.0.1:'+port+'/find-care-recruitment-polish-2026-09-09/'));
