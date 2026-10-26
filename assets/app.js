'use strict';
document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
const asset=document.querySelector('#asset-select');const slot=document.querySelector('#slot-select');
if(asset&&slot){
 const update=(initial=false)=>{
  const selected=asset.selectedOptions[0];let times=[];try{times=JSON.parse(selected?.dataset.times||'[]');}catch{}
  const value=initial?slot.dataset.initial:'';slot.replaceChildren(new Option('Extra / unscheduled reading',''));
  for(const time of times){const option=new Option(time,time);option.selected=time===value;slot.add(option);}
  const instructions=document.querySelector('#asset-instructions');if(instructions)instructions.textContent=selected?.dataset.instructions||'';
 };
 asset.addEventListener('change',()=>update(false));update(true);
}
// Protect against accidental duplicate submissions without preventing validation or disabling fields.
document.querySelectorAll('form[method="post"]').forEach(form=>{
 form.addEventListener('submit',event=>{
  if(form.dataset.submitting==='1'){event.preventDefault();return;}
  form.dataset.submitting='1';const button=event.submitter;if(button){button.dataset.originalLabel=button.textContent;button.setAttribute('aria-busy','true');button.textContent='Saving…';}
 });
});
window.addEventListener('pageshow',()=>document.querySelectorAll('form[data-submitting]').forEach(form=>{form.dataset.submitting='0';const button=form.querySelector('[aria-busy]');if(button){button.removeAttribute('aria-busy');button.textContent=button.dataset.originalLabel||'Submit';}}));
