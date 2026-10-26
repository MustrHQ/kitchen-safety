'use strict';
const CACHE='mustr-safety-static-1.1.1';
const BASE=new URL('./',self.location.href);
const FILES=['offline.html','assets/app.css','assets/frontend.css','assets/app.js','assets/pwa.js','assets/fonts/archivo-var.woff2','assets/brand/kitchen-safety.svg','assets/icons/icon-192.png','assets/icons/icon-512.png'].map(p=>new URL(p,BASE).href);
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(FILES))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('mustr-safety-static-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
 const request=event.request,url=new URL(request.url);
 if(url.origin!==BASE.origin||request.method!=='GET')return;
 // Never cache admin pages, records, POSTs or authenticated responses.
 if(request.mode==='navigate'&&!url.pathname.includes('/admin/')){
  event.respondWith(fetch(request).catch(()=>caches.match(new URL('offline.html',BASE).href)));return;
 }
 if(url.pathname.startsWith(BASE.pathname+'assets/')){
  event.respondWith(fetch(request).then(response=>{if(response.ok){const copy=response.clone();caches.open(CACHE).then(c=>c.put(request,copy));}return response;}).catch(()=>caches.match(request)));
 }
});
self.addEventListener('message',event=>{if(event.data?.type==='SKIP_WAITING')self.skipWaiting();});
