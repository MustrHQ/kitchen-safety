'use strict';
const root=document.body.dataset.base||'./';
if('serviceWorker' in navigator&&(window.isSecureContext))navigator.serviceWorker.register(root+'sw.js',{scope:root}).then(reg=>{if(reg.waiting)reg.waiting.postMessage({type:'SKIP_WAITING'});}).catch(()=>{});
const status=()=>document.querySelectorAll('[data-connection]').forEach(node=>{node.textContent=navigator.onLine?'Online · ready to save':'Offline · readings cannot be saved';node.classList.toggle('offline',!navigator.onLine);});
window.addEventListener('online',status);window.addEventListener('offline',status);status();
let installPrompt=null;
window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();installPrompt=event;});
const dialog=document.querySelector('[data-install-dialog]');
document.querySelectorAll('[data-install]').forEach(button=>button.addEventListener('click',async()=>{if(installPrompt){await installPrompt.prompt();installPrompt=null;}else if(dialog?.showModal)dialog.showModal();else alert('Use your browser menu: Add to Home Screen or Install app.');}));
document.querySelector('[data-close-install]')?.addEventListener('click',()=>dialog.close());
if(window.matchMedia('(display-mode: standalone)').matches||navigator.standalone)document.querySelector('[data-install-card]')?.remove();
// Local draft only. No reading is queued or marked saved offline.
const form=document.querySelector('form:has(input[name=action][value=record])');
if(form){
 const kind=form.querySelector('input[name=kind]')?.value||'record';
 const key='ks-draft:'+location.pathname+':'+kind;
 const fields=()=>Object.fromEntries([...new FormData(form)].filter(([k,v])=>!['csrf','action'].includes(k)&&typeof v==='string'));
 const clear=()=>{try{localStorage.removeItem(key);}catch{}};
 try{
  const draft=JSON.parse(localStorage.getItem(key)||'null');
  if(draft&&Date.now()-draft.saved<7*86400000){
   const notice=document.createElement('div');notice.className='draft-note';notice.textContent='An unsaved draft is stored on this tablet. ';
   const restore=document.createElement('button');restore.type='button';restore.className='btn small';restore.textContent='Restore draft';
   const discard=document.createElement('button');discard.type='button';discard.className='btn small';discard.textContent='Discard';
   restore.addEventListener('click',()=>{for(const [k,v] of Object.entries(draft.fields)){const el=form.elements.namedItem(k);if(!el)continue;if(el.type==='checkbox')el.checked=true;else if('value' in el)el.value=v;}form.querySelector('#asset-select')?.dispatchEvent(new Event('change'));const slot=form.elements.namedItem('slot');if(slot)slot.value=draft.fields.slot||'';notice.remove();});
   discard.addEventListener('click',()=>{clear();notice.remove();});notice.append(restore,discard);form.prepend(notice);
  }
 }catch{}
 form.addEventListener('input',()=>{try{localStorage.setItem(key,JSON.stringify({saved:Date.now(),fields:fields()}));}catch{}});
 form.addEventListener('submit',event=>{if(!navigator.onLine){event.preventDefault();form.dataset.submitting='0';const submit=form.querySelector('[aria-busy]');if(submit){submit.textContent=submit.dataset.originalLabel||'Save check';submit.removeAttribute('aria-busy');}alert('You are offline. The draft stays on this tablet; reconnect and submit again.');}else try{sessionStorage.setItem('ks-pending-draft',key);}catch{}});
}
if(document.querySelector('[data-saved-check]')&&new URLSearchParams(location.search).get('p')==='record')try{const key=sessionStorage.getItem('ks-pending-draft');if(key)localStorage.removeItem(key);sessionStorage.removeItem('ks-pending-draft');}catch{}
